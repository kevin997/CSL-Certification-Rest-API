<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\User;
use App\Support\EffectiveAuthContext;
use App\Support\Tenancy\EnvironmentResolver;
use App\Support\Tenancy\LoginBindingResolver;
use App\Support\Tenancy\NoEnvironmentException;
use App\Support\TenantDomainRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class SessionAuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'environment_id' => 'nullable|exists:environments,id',
        ]);

        $environmentId = $request->integer('environment_id');
        $user = null;
        $authenticatedViaEnvironment = false;
        $environmentUser = null;

        $user = $this->tryGlobalCredentials($request->email, $request->password);
        if ($user) {
            Log::info('Session Login: User authenticated via global password', ['user_id' => $user?->id]);
        }

        if (! $user && $environmentId) {
            $result = $this->tryEnvironmentCredentials($request->email, $request->password, $environmentId);

            if ($result) {
                $user = $result['user'];
                $environmentUser = $result['environmentUser'];
                $authenticatedViaEnvironment = true;

            }
        }

        if (! $user && ! $environmentId) {
            $result = $this->tryAnyEnvironmentCredentials($request->email, $request->password);

            if ($result) {
                $user = $result['user'];
                $environmentUser = $result['environmentUser'];
                $authenticatedViaEnvironment = true;

                $environmentId = (int) $environmentUser->environment_id;
            }
        }

        if (! $user) {
            throw ValidationException::withMessages([
                'credentials' => ['Invalid credentials provided.'],
            ]);
        }

        // Check domain-based role restrictions BEFORE actually logging in
        $userRoleCheck = $user->role instanceof UserRole ? $user->role->value : $user->role;
        $isAdminOrSalesAgent = in_array($userRoleCheck, [
            UserRole::ADMIN->value,
            UserRole::SUPER_ADMIN->value,
            UserRole::SALES_AGENT->value,
            'admin',
            'super_admin',
            'sales_agent',
        ]);

        if ($isAdminOrSalesAgent) {
            // Admin/sales agent users can ONLY login from allowed admin domains
            $frontendDomain = $request->header('X-Frontend-Domain', '');
            $origin = $request->header('Origin', '');
            $referer = $request->header('Referer', '');

            // Extract host from various headers
            $requestHost = $this->extractHostFromHeaders($frontendDomain, $origin, $referer);

            // Static baseline: known admin-capable dev + production hosts
            $staticAdminHosts = [
                'localhost', 'localhost:3001', 'localhost:3004', 'localhost:3005',
                '127.0.0.1', '127.0.0.1:3001', '127.0.0.1:3004', '127.0.0.1:3005',
            ];

            // Production root domains — subdomains are matched via str_ends_with below
            $allowedRoots = [
                'getkursa.space',
                'getkursa.app',
                'getkursa.com',
                'getkursa.net',
                'getkursa.org',
                'csl-brands.com',
            ];

            // Dynamic: all tenant custom domains registered in the DB (cached 5 min)
            $tenantHosts = TenantDomainRegistry::getAllowedHosts();

            // Check if request is from an allowed admin domain
            $isAllowedDomain = false;

            // 1. Exact match against static baseline + live tenant hosts
            $exactHosts = array_merge($staticAdminHosts, $tenantHosts);
            foreach ($exactHosts as $allowed) {
                if ($requestHost === $allowed || str_starts_with($requestHost, $allowed.':')) {
                    $isAllowedDomain = true;
                    break;
                }
            }

            // 2. Wildcard subdomain match for known production root domains
            if (! $isAllowedDomain) {
                foreach ($allowedRoots as $root) {
                    if ($requestHost === $root || str_ends_with($requestHost, '.'.$root)) {
                        $isAllowedDomain = true;
                        break;
                    }
                }
            }

            if (! $isAllowedDomain) {
                Log::warning('Admin/sales agent login attempt from unauthorized domain', [
                    'user_id' => $user->id,
                    'user_role' => $userRoleCheck,
                    'request_host' => $requestHost,
                    'frontend_domain' => $frontendDomain,
                ]);

                throw ValidationException::withMessages([
                    'credentials' => ['Access denied. Wrong password or domain not allowed.'],
                ]);
            }
        }

        $binding = null;

        try {
            $binding = app(LoginBindingResolver::class)->resolve(
                $user,
                $environmentId ? (int) $environmentId : null,
                app(EnvironmentResolver::class)->frontendHost($request),
            );
        } catch (NoEnvironmentException $e) {
            return response()->json([
                'success' => false,
                'code' => NoEnvironmentException::CODE,
                'message' => $e->getMessage(),
            ], 403);
        }

        if ($binding?->requiresSelection) {
            if ($authenticatedViaEnvironment) {
                $this->autoHealPassword($user, $request->password);
            }

            Auth::login($user);
            $request->session()->regenerate();
            $request->session()->forget('current_environment_id');

            $authContext = EffectiveAuthContext::for($user, null);
            $responseUser = $user->toArray();
            $responseUser['role'] = $authContext['role'];

            return response()->json([
                'success' => true,
                'user' => $responseUser,
                'environment_id' => null,
                ...$authContext,
                'is_account_setup' => null,
                'api_token' => null,
                'requires_environment_selection' => true,
                'environments' => $binding->environments,
            ]);
        }

        if ($binding !== null) {
            $environmentId = $binding->environmentId;
        }

        if ($environmentId) {
            $environment = Environment::find($environmentId);

            if (! $environment) {
                throw ValidationException::withMessages([
                    'credentials' => ['Invalid credentials provided.'],
                ]);
            }

            $isOwner = $environment->owner_id === $user->id;

            if (! $isOwner && ! $environmentUser) {
                $environmentUser = EnvironmentUser::where('environment_id', $environmentId)
                    ->where('user_id', $user->id)
                    ->first();

                if (! $environmentUser) {
                    throw ValidationException::withMessages([
                        'credentials' => ['Invalid credentials provided.'],
                    ]);
                }
            }
        } else {
            $userRoleValue = $user->role instanceof UserRole ? $user->role->value : $user->role;

            // Auto-resolve environment for teachers who login without explicit environment_id
            // (e.g. from the Sales Website for marketplace auth). This ensures that if they
            // navigate to their CSL-Certification panel, the session already has the right context.
            $isTeacherRole = in_array($userRoleValue, [
                UserRole::INDIVIDUAL_TEACHER->value,
                UserRole::COMPANY_TEACHER->value,
                UserRole::COMPANY_TEAM_MEMBER->value,
                'individual_teacher',
                'company_teacher',
                'company_team_member',
            ]);

            if ($isTeacherRole) {
                $ownedEnvironment = Environment::where('owner_id', $user->id)->first();
                if ($ownedEnvironment) {
                    $environmentId = $ownedEnvironment->id;
                }
            }
        }

        $authContext = EffectiveAuthContext::for($user, $environmentId);

        if ($authenticatedViaEnvironment) {
            $this->autoHealPassword($user, $request->password);
        }

        Auth::login($user);
        $request->session()->regenerate();

        if ($environmentId) {
            $request->session()->put('current_environment_id', $environmentId);
        } else {
            $request->session()->forget('current_environment_id');
        }

        $isAccountSetup = null;
        if ($environmentId) {
            $envUser = EnvironmentUser::where('environment_id', $environmentId)
                ->where('user_id', $user->id)
                ->first();
            $isAccountSetup = $envUser ? $envUser->is_account_setup : null;
        }

        $responseUser = $user->toArray();
        $responseUser['role'] = $authContext['role'];

        // For admin/sales_agent roles, issue a Sanctum API token so cross-domain
        // clients (e.g. manager.getkursa.space) can authenticate via Bearer header
        // instead of relying on session cookies, which cannot cross root domains.
        $adminRoles = [
            UserRole::ADMIN->value, UserRole::SUPER_ADMIN->value, UserRole::SALES_AGENT->value,
            'admin', 'super_admin', 'sales_agent',
        ];
        $apiToken = null;
        if (in_array($authContext['role'], $adminRoles)) {
            $apiToken = $user->createToken('admin-session')->plainTextToken;
        }

        return response()->json([
            'success' => true,
            'user' => $responseUser,
            'environment_id' => $environmentId,
            ...$authContext,
            'is_account_setup' => $isAccountSetup,
            'api_token' => $apiToken,
            'requires_environment_selection' => false,
        ]);
    }

    public function logout(Request $request)
    {
        // Revoke the current Sanctum API token if the request is token-authenticated
        if ($request->user() && $request->bearerToken()) {
            $request->user()->currentAccessToken()->delete();
        }

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out',
        ]);
    }

    private function tryGlobalCredentials(string $email, string $password): ?User
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }

    private function tryEnvironmentCredentials(string $email, string $password, int $environmentId): ?array
    {
        $environmentUser = EnvironmentUser::where('environment_id', $environmentId)
            ->where('environment_email', $email)
            ->where('use_environment_credentials', true)
            ->first();

        if (! $environmentUser) {
            return null;
        }

        if (! Hash::check($password, $environmentUser->environment_password)) {
            return null;
        }

        $user = User::find($environmentUser->user_id);
        if (! $user) {
            return null;
        }

        Log::info('Session Login: User authenticated via environment credentials', [
            'user_id' => $user->id,
            'environment_id' => $environmentId,
        ]);

        return [
            'user' => $user,
            'environmentUser' => $environmentUser,
        ];
    }

    private function tryAnyEnvironmentCredentials(string $email, string $password): ?array
    {
        $environmentUsers = EnvironmentUser::where('environment_email', $email)
            ->where('use_environment_credentials', true)
            ->get();

        foreach ($environmentUsers as $environmentUser) {
            if (Hash::check($password, $environmentUser->environment_password)) {
                $user = User::find($environmentUser->user_id);
                if ($user) {
                    Log::info('Session Login: User authenticated via any environment credentials', [
                        'user_id' => $user->id,
                        'environment_id' => $environmentUser->environment_id,
                    ]);

                    return [
                        'user' => $user,
                        'environmentUser' => $environmentUser,
                    ];
                }
            }
        }

        return null;
    }

    private function autoHealPassword(User $user, string $plainPassword): void
    {
        try {
            $user->password = Hash::make($plainPassword);
            $user->save();

            Log::info('Session Login: Auto-healed password to users table', [
                'user_id' => $user->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Session Login: Failed to auto-heal password', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Generate a short-lived Sanctum token for cross-domain marketplace auth.
     * Called after the user is already authenticated via session.
     */
    public function marketplaceToken(Request $request)
    {
        $request->validate([
            'redirect_url' => 'required|url|max:500',
        ]);

        $user = Auth::user();
        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => 'Not authenticated',
            ], 401);
        }

        $redirectUrl = $request->input('redirect_url');

        // Validate the redirect URL against allowed domains
        if (! $this->isAllowedRedirectUrl($redirectUrl)) {
            Log::warning('Marketplace token: rejected redirect URL', [
                'user_id' => $user->id,
                'redirect_url' => $redirectUrl,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Redirect URL not allowed',
            ], 403);
        }

        // Load the user's owned environment for marketplace context
        $environment = Environment::where('owner_id', $user->id)->first();

        // Create a long-lived token for ongoing marketplace API access
        $token = $user->createToken('marketplace-auth', ['marketplace'], now()->addDays(30));

        $userRole = $user->role instanceof UserRole ? $user->role->value : $user->role;

        return response()->json([
            'success' => true,
            'token' => $token->plainTextToken,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $userRole,
                'company_name' => $user->company_name,
            ],
            'environment' => $environment ? [
                'id' => $environment->id,
                'name' => $environment->name,
                'primary_domain' => $environment->primary_domain,
                'logo_url' => $environment->logo_url,
            ] : null,
        ]);
    }

    /**
     * Validate a redirect URL against allowed marketplace domains.
     */
    private function isAllowedRedirectUrl(string $url): bool
    {
        $parsed = parse_url($url);
        if (! $parsed || ! isset($parsed['host'])) {
            return false;
        }

        $host = strtolower($parsed['host']);

        $allowedPatterns = [
            'localhost',
            '127.0.0.1',
            'marketplace.getkursa.app',
            'marketplace.csl-brands.com',
            'getkursa.space',
            'www.getkursa.space',
        ];

        // Also allow any subdomain of getkursa.app, csl-brands.com, or getkursa.space
        foreach ($allowedPatterns as $pattern) {
            if ($host === $pattern) {
                return true;
            }
        }

        if (str_ends_with($host, '.getkursa.app') || str_ends_with($host, '.csl-brands.com') || str_ends_with($host, '.getkursa.space')) {
            return true;
        }

        return false;
    }

    /**
     * Extract host from request headers.
     * Priority: X-Frontend-Domain > Origin > Referer
     */
    private function extractHostFromHeaders(string $frontendDomain, string $origin, string $referer): string
    {
        // Use X-Frontend-Domain if provided (set by our frontend)
        if (! empty($frontendDomain)) {
            // Remove any scheme if accidentally included
            $frontendDomain = preg_replace('#^https?://#', '', $frontendDomain);

            return strtolower(trim($frontendDomain));
        }

        // Try Origin header
        if (! empty($origin)) {
            $parsed = parse_url($origin);
            if (isset($parsed['host'])) {
                $host = strtolower($parsed['host']);
                if (isset($parsed['port'])) {
                    $host .= ':'.$parsed['port'];
                }

                return $host;
            }
        }

        // Try Referer header
        if (! empty($referer)) {
            $parsed = parse_url($referer);
            if (isset($parsed['host'])) {
                $host = strtolower($parsed['host']);
                if (isset($parsed['port'])) {
                    $host .= ':'.$parsed['port'];
                }

                return $host;
            }
        }

        return '';
    }
}
