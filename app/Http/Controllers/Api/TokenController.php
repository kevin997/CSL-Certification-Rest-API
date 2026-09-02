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
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TokenController extends Controller
{
    /**
     * Create a new API token for the user.
     *
     * SMART LOGIN FLOW (Identity Unification):
     * 1. Try global password (users table) first
     * 2. If fails, try environment-specific password (environment_user table)
     * 3. If environment password succeeds, AUTO-HEAL by syncing to users table
     *
     * @return JsonResponse
     */
    public function createToken(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
            'device_name' => 'required',
            'environment_id' => 'nullable|exists:environments,id',
        ]);

        $environmentId = $request->environment_id;
        $user = null;
        $authenticatedViaEnvironment = false;

        // STEP 1: Try global password first (users table)
        $user = $this->tryGlobalCredentials($request->email, $request->password);
        if ($user) {
            Log::info('Smart Login: User authenticated via global password', ['user_id' => $user->id]);
        }

        // STEP 2: If global auth failed, try environment-specific credentials
        if (! $user && $environmentId) {
            $result = $this->tryEnvironmentCredentials($request->email, $request->password, $environmentId);

            if ($result) {
                $user = $result['user'];
                $authenticatedViaEnvironment = true;

            }
        }

        // STEP 2b: If still no user and no environment_id, try to find any matching environment credential
        if (! $user && ! $environmentId) {
            $result = $this->tryAnyEnvironmentCredentials($request->email, $request->password);

            if ($result) {
                $user = $result['user'];
                $authenticatedViaEnvironment = true;
                $environmentId = (int) $result['environmentUser']->environment_id;

            }
        }

        // If still no user, authentication failed
        if (! $user) {
            throw ValidationException::withMessages([
                'credentials' => ['Invalid credentials provided.'],
            ]);
        }

        // Check domain-based role restrictions BEFORE creating token
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

            // Production root domains — any subdomain is matched via str_ends_with
            $allowedRoots = [
                'getkursa.space', 'getkursa.app', 'getkursa.com',
                'getkursa.net', 'getkursa.org', 'csl-brands.com',
            ];

            // Dynamic: tenant custom domains registered in the DB
            $tenantHosts = TenantDomainRegistry::getAllowedHosts();

            $isAllowedDomain = false;

            foreach (array_merge($staticAdminHosts, $tenantHosts) as $allowed) {
                if ($requestHost === $allowed || str_starts_with($requestHost, $allowed.':')) {
                    $isAllowedDomain = true;
                    break;
                }
            }

            if (! $isAllowedDomain) {
                foreach ($allowedRoots as $root) {
                    if ($requestHost === $root || str_ends_with($requestHost, '.'.$root)) {
                        $isAllowedDomain = true;
                        break;
                    }
                }
            }

            if (! $isAllowedDomain) {
                Log::warning('Admin/sales agent token creation attempt from unauthorized domain', [
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
                'code' => NoEnvironmentException::CODE,
                'message' => $e->getMessage(),
            ], 403);
        }

        if ($binding?->requiresSelection) {
            $userRoleValue = $user->role instanceof UserRole ? $user->role->value : $user->role;
            $abilities = $userRoleValue ? ['role:'.$userRoleValue] : [];
            $token = $user->createToken($request->device_name, $abilities)->plainTextToken;

            if ($authenticatedViaEnvironment) {
                $this->autoHealPassword($user, $request->password);
            }

            $authContext = EffectiveAuthContext::for($user, null);
            $responseUser = $user->toArray();
            $responseUser['role'] = $authContext['role'];

            return response()->json([
                'token' => $token,
                'user' => $responseUser,
                'environment_id' => null,
                ...$authContext,
                'is_account_setup' => null,
                'requires_environment_selection' => true,
                'environments' => $binding->environments,
            ]);
        }

        if ($binding !== null) {
            $environmentId = $binding->environmentId;
        }

        // Check if environment ID is provided and verify user access
        if ($environmentId) {
            // Check if user is the owner of the environment or exists in environment_user table
            $environment = Environment::find($environmentId);

            if (! $environment) {
                throw ValidationException::withMessages([
                    'credentials' => ['Invalid credentials provided.'],
                ]);
            }

            // Check if user is the owner or has access to this environment
            $isOwner = $environment->owner_id === $user->id;
            $environmentUser = null;

            if (! $isOwner) {
                $environmentUser = EnvironmentUser::where('environment_id', $environmentId)
                    ->where('user_id', $user->id)
                    ->first();

                if (! $environmentUser) {
                    throw ValidationException::withMessages([
                        'credentials' => ['Invalid credentials provided.'],
                    ]);
                }
            }

            // Determine the role for token abilities
            $userRole = $user->role;
            $environmentRole = $environmentUser ? $environmentUser->role : null;

            // Convert enum values to strings if needed
            $userRoleValue = $userRole instanceof UserRole ? $userRole->value : $userRole;
            $environmentRoleValue = $environmentRole instanceof UserRole ? $environmentRole->value : $environmentRole;

            // Create abilities array for the token
            $abilities = ['environment_id:'.$environmentId];

            // Add user's system role
            if ($userRoleValue) {
                $abilities[] = 'role:'.$userRoleValue;
            }

            // Add environment-specific role if applicable
            if ($environmentRoleValue) {
                $abilities[] = 'env_role:'.$environmentRoleValue;
            }

            // Create token with abilities
            $token = $user->createToken($request->device_name, $abilities)->plainTextToken;

        } else {
            // No environment specified, regular user access
            $userRole = $user->role;
            $abilities = [];

            // Ensure we get string value from enum if needed
            $userRoleValue = $userRole instanceof UserRole ? $userRole->value : $userRole;

            if ($userRoleValue) {
                $abilities[] = 'role:'.$userRoleValue;
            }

            $token = $user->createToken($request->device_name, $abilities)->plainTextToken;
        }

        if ($authenticatedViaEnvironment) {
            $this->autoHealPassword($user, $request->password);
        }

        // Get the is_account_setup status if this is an environment login
        $isAccountSetup = null;
        if ($environmentId) {
            $envUser = EnvironmentUser::where('environment_id', $environmentId)
                ->where('user_id', $user->id)
                ->first();
            $isAccountSetup = $envUser ? $envUser->is_account_setup : null;
        }

        $authContext = EffectiveAuthContext::for($user, $environmentId ? (int) $environmentId : null);
        $responseUser = $user->toArray();
        $responseUser['role'] = $authContext['role'];

        return response()->json([
            'token' => $token,
            'user' => $responseUser,
            'environment_id' => $environmentId,
            ...$authContext,
            'is_account_setup' => $isAccountSetup,
            'requires_environment_selection' => false,
        ]);
    }

    /**
     * Authenticate a learner in an environment using environment-specific credentials
     *
     * @return JsonResponse
     */
    private function authenticateLearner(Request $request, EnvironmentUser $environmentUser)
    {
        $environmentId = $request->environment_id;

        // Get the associated user
        $user = User::find($environmentUser->user_id);

        if (! $user) {
            throw ValidationException::withMessages([
                'credentials' => ['Invalid credentials provided.'],
            ]);
        }

        // Determine the roles for token abilities
        $userRole = $user->role;  // System-level role
        $environmentRole = $environmentUser->role;  // Environment-specific role

        // Convert enum values to strings if needed
        $userRoleValue = $userRole instanceof UserRole ? $userRole->value : $userRole;
        $environmentRoleValue = $environmentRole instanceof UserRole ? $environmentRole->value : $environmentRole;

        // Create abilities array for the token
        $abilities = ['environment_id:'.$environmentId];

        // Add user's system role
        if ($userRoleValue) {
            $abilities[] = 'role:'.$userRoleValue;
        }

        // Add environment-specific role
        if ($environmentRoleValue) {
            $abilities[] = 'env_role:'.$environmentRoleValue;
        }

        // Create token with abilities
        $token = $user->createToken($request->device_name, $abilities)->plainTextToken;

        // Use environment role as primary role for the response, or fallback to system role
        $role = $environmentRoleValue ?: $userRoleValue;

        return response()->json([
            'token' => $token,
            'user' => $user,
            'environment_id' => $environmentId,
            'role' => $role,
            'user_role' => $userRoleValue,
            'environment_role' => $environmentRoleValue,
            'is_account_setup' => $environmentUser->is_account_setup,
        ]);
    }

    /**
     * Try to authenticate using environment-specific credentials for a specific environment.
     *
     * @return array|null Returns ['user' => User, 'environmentUser' => EnvironmentUser] or null
     */
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

        Log::info('Smart Login: User authenticated via environment credentials', [
            'user_id' => $user->id,
            'environment_id' => $environmentId,
        ]);

        return [
            'user' => $user,
            'environmentUser' => $environmentUser,
        ];
    }

    private function tryGlobalCredentials(string $email, string $password): ?User
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
    }

    /**
     * Try to authenticate using any environment-specific credentials (when no environment_id provided).
     *
     * @return array|null Returns ['user' => User, 'environmentUser' => EnvironmentUser] or null
     */
    private function tryAnyEnvironmentCredentials(string $email, string $password): ?array
    {
        // Find all environment_user records with this email
        $environmentUsers = EnvironmentUser::where('environment_email', $email)
            ->where('use_environment_credentials', true)
            ->get();

        foreach ($environmentUsers as $environmentUser) {
            if (Hash::check($password, $environmentUser->environment_password)) {
                $user = User::find($environmentUser->user_id);
                if ($user) {
                    Log::info('Smart Login: User authenticated via any environment credentials', [
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

    /**
     * Auto-heal: Sync the environment password to the users table.
     * This ensures the user can log in with the same password next time via the global auth.
     */
    private function autoHealPassword(User $user, string $plainPassword): void
    {
        try {
            $user->password = Hash::make($plainPassword);
            $user->save();

            Log::info('Smart Login: Auto-healed password to users table', [
                'user_id' => $user->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Smart Login: Failed to auto-heal password', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Revoke all tokens for the authenticated user.
     *
     * @return JsonResponse
     */
    public function revokeTokens(Request $request)
    {
        // Preserve marketplace tokens — they are managed separately by the marketplace frontend
        $request->user()->tokens()
            ->where('name', '!=', 'marketplace-auth')
            ->delete();

        return response()->json(['message' => 'All tokens revoked successfully']);
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
