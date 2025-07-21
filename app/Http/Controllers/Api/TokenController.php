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
use Illuminate\Validation\ValidationException;

class TokenController extends Controller
{
    /**
     * Create a new API token for the user.
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
        
        // First check if this is a learner authentication attempt using environment credentials
        if ($environmentId) {
            // Look for the environment user record with matching environment email
            $environmentUser = EnvironmentUser::where('environment_id', $environmentId)
                ->where('environment_email', $request->email)
                ->where('use_environment_credentials', true)
                ->first();
            
            if ($environmentUser && Hash::check($request->password, $environmentUser->environment_password)) {
                // This is a learner authentication, handle it separately
                return $this->authenticateLearner($request, $environmentUser);
            }
        }

        // Set environment ID in session if provided
        if ($request->has('environment_id')) {
            session(['current_environment_id' => $request->environment_id]);
            
            // Configure auth guard to use the environment user provider
            $guard = Auth::guard();
            if (method_exists($guard->getProvider(), 'setEnvironmentId')) {
                $guard->getProvider()->setEnvironmentId($request->environment_id);
            }
        }

        // Attempt to authenticate the user as an environment owner or admin
        if (!Auth::attempt($request->only('email', 'password'))) {
            // Generic error message to prevent username enumeration
            throw ValidationException::withMessages([
                'credentials' => ['Invalid credentials provided.'],
            ]);
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
     * Revoke all tokens for the authenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function revokeTokens(Request $request)
    {
        $request->user()->tokens()->delete();

        return response()->json(['message' => 'All tokens revoked successfully']);
    }
}
