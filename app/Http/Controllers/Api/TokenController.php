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
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = Auth::user();
        
        // Check if environment ID is provided and verify user access
        if ($environmentId) {
            // Check if user is the owner of the environment or exists in environment_user table
            $environment = Environment::find($environmentId);
            
            if (!$environment) {
                throw ValidationException::withMessages([
                    'environment_id' => ['The specified environment does not exist.'],
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
                        'environment_id' => ['You do not have access to this environment.'],
                    ]);
                }
            }
            
            // Determine the role for token abilities
            $userRole = $user->role;
            $environmentRole = $environmentUser ? $environmentUser->role : null;
            
            // Create abilities array for the token
            $abilities = ['environment_id:' . $environmentId];
            
            // Add user's system role
            if ($userRole) {
                $abilities[] = 'role:' . $userRole;
            }
            
            // Add environment-specific role if applicable
            if ($environmentRole) {
                $abilities[] = 'env_role:' . $environmentRole;
            }
            
            // Create token with abilities
            $token = $user->createToken($request->device_name, $abilities)->plainTextToken;
            
            // Determine the primary role for the response
            if ($isOwner) {
                $role = $userRole === UserRole::COMPANY_TEACHER->value ? 'company_teacher' : 'individual_teacher';
            } else {
                $role = $environmentRole ?: $userRole;
            }
        } else {
            // No environment specified, regular user access
            $userRole = $user->role;
            $abilities = [];
            
            if ($userRole) {
                $abilities[] = 'role:' . $userRole;
            }
            
            $token = $user->createToken($request->device_name, $abilities)->plainTextToken;
            $role = $userRole ?: 'user';
        }

        return response()->json([
            'token' => $token,
            'user' => $user,
            'environment_id' => $environmentId,
            'role' => $role,
            'user_role' => $user->role,
            'environment_role' => $environmentUser->role ?? null
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
                'email' => ['User account not found.'],
            ]);
        }
        
        // Determine the roles for token abilities
        $userRole = $user->role;  // System-level role
        $environmentRole = $environmentUser->role;  // Environment-specific role
        
        // Create abilities array for the token
        $abilities = ['environment_id:' . $environmentId];
        
        // Add user's system role
        if ($userRole) {
            $abilities[] = 'role:' . $userRole;
        }
        
        // Add environment-specific role
        if ($environmentRole) {
            $abilities[] = 'env_role:' . $environmentRole;
        }
        
        // Create token with abilities
        $token = $user->createToken($request->device_name, $abilities)->plainTextToken;
        
        // Use environment role as primary role for the response, or fallback to system role
        $role = $environmentRole ?: $userRole;
        
        return response()->json([
            'token' => $token,
            'user' => $user,
            'environment_id' => $environmentId,
            'role' => $role,
            'user_role' => $userRole,
            'environment_role' => $environmentRole
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
