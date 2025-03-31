<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class RegisterController extends Controller
{
    /**
     * Register a new user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'device_name' => ['required', 'string'],
            'environment_id' => ['sometimes', 'exists:environments,id'],
            'use_environment_credentials' => ['sometimes', 'boolean'],
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        // If environment_id is provided, attach the user to that environment
        if ($request->has('environment_id')) {
            $useEnvironmentCredentials = $request->input('use_environment_credentials', false);
            
            $pivotData = [
                'role' => 'learner',
                'use_environment_credentials' => $useEnvironmentCredentials,
            ];
            
            // If using environment-specific credentials, store them
            if ($useEnvironmentCredentials) {
                $pivotData['environment_email'] = $request->email;
                $pivotData['environment_password'] = Hash::make($request->password);
            }
            
            $user->environments()->attach($request->environment_id, $pivotData);
        }

        // Create a token for the user
        $token = $user->createToken($request->device_name)->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user,
            'environment_id' => $request->environment_id,
        ], 201);
    }
}
