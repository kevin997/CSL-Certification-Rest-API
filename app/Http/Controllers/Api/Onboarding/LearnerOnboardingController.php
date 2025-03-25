<?php

namespace App\Http\Controllers\Api\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class LearnerOnboardingController extends Controller
{
    /**
     * Onboard a new individual learner.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/api/onboarding/learner",
     *     summary="Onboard a new individual learner",
     *     description="Create a new user account for an individual learner and associate them with an environment",
     *     operationId="onboardLearner",
     *     tags={"Onboarding"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "environment_id"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="environment_id", type="integer", example=1),
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Learner onboarded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Learner onboarded successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="environment_id", type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Server error"
     *     )
     * )
     */
    public function store(Request $request)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'environment_id' => 'required|exists:environments,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Use a transaction to ensure all operations succeed or fail together
            return DB::transaction(function () use ($request) {
                // Get the environment
                $environment = Environment::findOrFail($request->environment_id);

                // Create the user
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'email_verified_at' => now(), // Auto-verify for simplicity
                ]);

                // Associate the user with the environment as a learner
                $environment->users()->attach($user->id, [
                    'role' => 'learner',
                    'permissions' => json_encode([
                        'access_courses' => true,
                    ]),
                    'joined_at' => now(),
                    'is_active' => true,
                    'credentials' => json_encode([
                        'username' => $user->email,
                        'environment_specific_id' => 'LRN-' . $user->id,
                    ]),
                    'environment_email' => $user->email,
                    'environment_password' => Hash::make($request->password),
                    'use_environment_credentials' => true,
                ]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Learner onboarded successfully',
                    'data' => [
                        'user_id' => (int)$user->id,
                        'environment_id' => (int)$environment->id,
                    ]
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to onboard learner',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
