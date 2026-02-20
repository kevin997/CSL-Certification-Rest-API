<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\EnvironmentUser;
use App\Models\Environment;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
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

        // Set environment ID in session if provided
        if ($request->has('environment_id')) {
            session(['current_environment_id' => $request->environment_id]);
        }

        // STEP 1: Try global password first (users table)
        if (Auth::attempt($request->only('email', 'password'))) {
            $user = Auth::user();
            Log::info('Smart Login: User authenticated via global password', ['user_id' => $user->id]);
        }

        // STEP 2: If global auth failed, try environment-specific credentials
        if (!$user && $environmentId) {
            $result = $this->tryEnvironmentCredentials($request->email, $request->password, $environmentId);

            if ($result) {
                $user = $result['user'];
                $authenticatedViaEnvironment = true;

                // STEP 3: AUTO-HEAL - Sync password to users table
                $this->autoHealPassword($user, $request->password);
            }
        }

        // STEP 2b: If still no user and no environment_id, try to find any matching environment credential
        if (!$user) {
            $result = $this->tryAnyEnvironmentCredentials($request->email, $request->password);

            if ($result) {
                $user = $result['user'];
                $authenticatedViaEnvironment = true;

                // AUTO-HEAL - Sync password to users table
                $this->autoHealPassword($user, $request->password);
            }
        }

        // If still no user, authentication failed
        if (!$user) {
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
            'sales_agent'
        ]);

        if ($isAdminOrSalesAgent) {
            // Admin/sales agent users can ONLY login from allowed admin domains
            $frontendDomain = $request->header('X-Frontend-Domain', '');
            $origin = $request->header('Origin', '');
            $referer = $request->header('Referer', '');

            // Extract host from various headers
            $requestHost = $this->extractHostFromHeaders($frontendDomain, $origin, $referer);

            // List of allowed admin domains
            $allowedAdminDomains = [
                'sales.csl-brands.com',
                'kursa.csl-brands.com',
                'localhost:3001',
                'localhost',
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

        // If authenticated via environment credentials, manually log in the user
        if ($authenticatedViaEnvironment) {
            Auth::login($user);
        }

        $user = Auth::user();

        // Check if environment ID is provided and verify user access
        if ($environmentId) {
            // Check if user is the owner of the environment or exists in environment_user table
            $environment = Environment::find($environmentId);

            if (!$environment) {
                throw ValidationException::withMessages([
                    'credentials' => ['Invalid credentials provided.'],
                ]);
            }

            // Check if user is the owner or has access to this environment
            $isOwner = $environment->owner_id === $user->id;
            $environmentUser = null;

            if (!$isOwner) {
                $environmentUser = EnvironmentUser::where('environment_id', $environmentId)
                    ->where('user_id', $user->id)
                    ->first();

                if (!$environmentUser) {
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
            $abilities = ['environment_id:' . $environmentId];

            // Add user's system role
            if ($userRoleValue) {
                $abilities[] = 'role:' . $userRoleValue;
            }

            // Add environment-specific role if applicable
            if ($environmentRoleValue) {
                $abilities[] = 'env_role:' . $environmentRoleValue;
            }

            // Create token with abilities
            $token = $user->createToken($request->device_name, $abilities)->plainTextToken;

            // Determine the primary role for the response
            if ($isOwner) {
                // Convert enum to string value before comparison
                $userRoleValue = $userRole instanceof UserRole ? $userRole->value : $userRole;
                $role = $userRoleValue === UserRole::COMPANY_TEACHER->value ? 'company_teacher' : 'individual_teacher';
            } else {
                // Ensure we're using string values
                $envRoleValue = $environmentRole instanceof UserRole ? $environmentRole->value : $environmentRole;
                $userRoleValue = $userRole instanceof UserRole ? $userRole->value : $userRole;
                $role = $envRoleValue ?: $userRoleValue;
            }
        } else {
            // No environment specified, regular user access
            $userRole = $user->role;
            $abilities = [];

            // Ensure we get string value from enum if needed
            $userRoleValue = $userRole instanceof UserRole ? $userRole->value : $userRole;

            if ($userRoleValue) {
                $abilities[] = 'role:' . $userRoleValue;
            }

            $token = $user->createToken($request->device_name, $abilities)->plainTextToken;
            $role = $userRoleValue ?: 'user';
        }

        // Ensure we're returning string values for roles in the response
        $userRoleForResponse = $user->role instanceof UserRole ? $user->role->value : $user->role;
        $environmentRoleForResponse = isset($environmentUser->role) ?
            ($environmentUser->role instanceof UserRole ? $environmentUser->role->value : $environmentUser->role) :
            null;

        // Get the is_account_setup status if this is an environment login
        $isAccountSetup = null;
        if ($environmentId) {
            $envUser = EnvironmentUser::where('environment_id', $environmentId)
                ->where('user_id', $user->id)
                ->first();
            $isAccountSetup = $envUser ? $envUser->is_account_setup : null;
        }

        return response()->json([
            'token' => $token,
            'user' => $user,
            'environment_id' => $environmentId,
            'role' => $role,
            'user_role' => $userRoleForResponse,
            'environment_role' => $environmentRoleForResponse,
            'is_account_setup' => $isAccountSetup
        ]);
    }

    /**
     * Authenticate a learner in an environment using environment-specific credentials
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\EnvironmentUser  $environmentUser
     * @return \Illuminate\Http\JsonResponse
     */
    private function authenticateLearner(Request $request, EnvironmentUser $environmentUser)
    {
        $environmentId = $request->environment_id;

        // Get the associated user
        $user = User::find($environmentUser->user_id);

        if (!$user) {
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
        $abilities = ['environment_id:' . $environmentId];

        // Add user's system role
        if ($userRoleValue) {
            $abilities[] = 'role:' . $userRoleValue;
        }

        // Add environment-specific role
        if ($environmentRoleValue) {
            $abilities[] = 'env_role:' . $environmentRoleValue;
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
            'is_account_setup' => $environmentUser->is_account_setup
        ]);
    }

    /**
     * Try to authenticate using environment-specific credentials for a specific environment.
     *
     * @param string $email
     * @param string $password
     * @param int $environmentId
     * @return array|null Returns ['user' => User, 'environmentUser' => EnvironmentUser] or null
     */
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

        Log::info('Smart Login: User authenticated via environment credentials', [
            'user_id' => $user->id,
            'environment_id' => $environmentId,
        ]);

        return [
            'user' => $user,
            'environmentUser' => $environmentUser,
        ];
    }

    /**
     * Try to authenticate using any environment-specific credentials (when no environment_id provided).
     *
     * @param string $email
     * @param string $password
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
     *
     * @param User $user
     * @param string $plainPassword
     * @return void
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
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
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
