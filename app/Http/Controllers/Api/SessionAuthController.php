<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\User;
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

        if ($request->has('environment_id')) {
            session(['current_environment_id' => $environmentId]);
        }

        if (Auth::attempt($request->only('email', 'password'))) {
            $user = Auth::user();
            Log::info('Session Login: User authenticated via global password', ['user_id' => $user?->id]);
        }

        if (!$user && $environmentId) {
            $result = $this->tryEnvironmentCredentials($request->email, $request->password, $environmentId);

            if ($result) {
                $user = $result['user'];
                $environmentUser = $result['environmentUser'];
                $authenticatedViaEnvironment = true;

                $this->autoHealPassword($user, $request->password);
            }
        }

        if (!$user) {
            $result = $this->tryAnyEnvironmentCredentials($request->email, $request->password);

            if ($result) {
                $user = $result['user'];
                $environmentUser = $result['environmentUser'];
                $authenticatedViaEnvironment = true;

                $this->autoHealPassword($user, $request->password);

                // If no environment_id explicitly provided, persist the matched environment.
                session(['current_environment_id' => $environmentUser->environment_id]);
                $environmentId = (int) $environmentUser->environment_id;
            }
        }

        if (!$user) {
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
            'sales_agent'
        ]);

        if ($isAdminOrSalesAgent) {
            // Admin/sales agent users can ONLY login from allowed admin domains
            $frontendDomain = $request->header('X-Frontend-Domain', '');
            $origin = $request->header('Origin', '');
            $referer = $request->header('Referer', '');
            
            // Extract host from various headers
            $requestHost = $this->extractHostFromHeaders($frontendDomain, $origin, $referer);
            
            // List of allowed admin domains (host only, no port for production)
            $allowedAdminDomains = [
                'sales.csl-brands.com',
                'kursa.csl-brands.com',
                'localhost:3001',  // Sales Website local dev
                'localhost',       // Allow localhost without port for flexibility
                '127.0.0.1:3001',
                '127.0.0.1',
            ];
            
            // Check if request is from an allowed admin domain
            $isAllowedDomain = false;
            foreach ($allowedAdminDomains as $allowed) {
                if ($requestHost === $allowed || str_starts_with($requestHost, $allowed . ':')) {
                    $isAllowedDomain = true;
                    break;
                }
            }
            
            if (!$isAllowedDomain) {
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

        if ($authenticatedViaEnvironment) {
            Auth::login($user);
        }

        $request->session()->regenerate();

        $user = Auth::user();

        if ($environmentId) {
            $environment = Environment::find($environmentId);

            if (!$environment) {
                throw ValidationException::withMessages([
                    'credentials' => ['Invalid credentials provided.'],
                ]);
            }

            $isOwner = $environment->owner_id === $user->id;

            if (!$isOwner && !$environmentUser) {
                $environmentUser = EnvironmentUser::where('environment_id', $environmentId)
                    ->where('user_id', $user->id)
                    ->first();

                if (!$environmentUser) {
                    throw ValidationException::withMessages([
                        'credentials' => ['Invalid credentials provided.'],
                    ]);
                }
            }

            $userRole = $user->role;
            $environmentRole = $environmentUser ? $environmentUser->role : null;

            $userRoleValue = $userRole instanceof UserRole ? $userRole->value : $userRole;
            $environmentRoleValue = $environmentRole instanceof UserRole ? $environmentRole->value : $environmentRole;

            if ($isOwner) {
                $role = $userRoleValue === UserRole::COMPANY_TEACHER->value ? 'company_teacher' : 'individual_teacher';
            } else {
                $role = $environmentRoleValue ?: $userRoleValue;
            }
        } else {
            $userRoleValue = $user->role instanceof UserRole ? $user->role->value : $user->role;
            $role = $userRoleValue ?: 'user';
            $environmentRoleValue = null;
        }

        $isAccountSetup = null;
        if ($environmentId) {
            $envUser = EnvironmentUser::where('environment_id', $environmentId)
                ->where('user_id', $user->id)
                ->first();
            $isAccountSetup = $envUser ? $envUser->is_account_setup : null;
        }

        return response()->json([
            'success' => true,
            'user' => $user,
            'environment_id' => $environmentId,
            'role' => $role,
            'user_role' => $user->role instanceof UserRole ? $user->role->value : $user->role,
            'environment_role' => $environmentRoleValue,
            'is_account_setup' => $isAccountSetup,
        ]);
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
            'message' => 'Successfully logged out',
        ]);
    }

    private function tryEnvironmentCredentials(string $email, string $password, int $environmentId): ?array
    {
        $environmentUser = EnvironmentUser::where('environment_id', $environmentId)
            ->where('environment_email', $email)
            ->where('use_environment_credentials', true)
            ->first();

        if (!$environmentUser) {
            return null;
        }

        if (!Hash::check($password, $environmentUser->environment_password)) {
            return null;
        }

        $user = User::find($environmentUser->user_id);
        if (!$user) {
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
     * Extract host from request headers.
     * Priority: X-Frontend-Domain > Origin > Referer
     */
    private function extractHostFromHeaders(string $frontendDomain, string $origin, string $referer): string
    {
        // Use X-Frontend-Domain if provided (set by our frontend)
        if (!empty($frontendDomain)) {
            // Remove any scheme if accidentally included
            $frontendDomain = preg_replace('#^https?://#', '', $frontendDomain);
            return strtolower(trim($frontendDomain));
        }

        // Try Origin header
        if (!empty($origin)) {
            $parsed = parse_url($origin);
            if (isset($parsed['host'])) {
                $host = strtolower($parsed['host']);
                if (isset($parsed['port'])) {
                    $host .= ':' . $parsed['port'];
                }
                return $host;
            }
        }

        // Try Referer header
        if (!empty($referer)) {
            $parsed = parse_url($referer);
            if (isset($parsed['host'])) {
                $host = strtolower($parsed['host']);
                if (isset($parsed['port'])) {
                    $host .= ':' . $parsed['port'];
                }
                return $host;
            }
        }

        return '';
    }
}
