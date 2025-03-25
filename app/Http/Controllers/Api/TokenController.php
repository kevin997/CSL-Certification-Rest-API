<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
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

        // Set environment ID in session if provided
        if ($request->has('environment_id')) {
            session(['current_environment_id' => $request->environment_id]);
            
            // Configure auth guard to use the environment user provider
            $guard = Auth::guard();
            if (method_exists($guard->getProvider(), 'setEnvironmentId')) {
                $guard->getProvider()->setEnvironmentId($request->environment_id);
            }
        }

        // Attempt to authenticate the user
        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = Auth::user();
        $environmentId = $request->environment_id;
        
        // Create token with environment ID in abilities if provided
        if ($environmentId) {
            $token = $user->createToken($request->device_name, ['environment_id:' . $environmentId])->plainTextToken;
        } else {
            $token = $user->createToken($request->device_name)->plainTextToken;
        }

        return response()->json([
            'token' => $token,
            'user' => $user,
            'environment_id' => $environmentId,
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
