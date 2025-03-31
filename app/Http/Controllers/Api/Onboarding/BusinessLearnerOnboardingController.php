<?php

namespace App\Http\Controllers\Api\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BusinessLearnerOnboardingController extends Controller
{
    /**
     * Onboard a batch of business learners.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/api/onboarding/business-learner",
     *     summary="Onboard a batch of business learners",
     *     description="Create multiple user accounts for business learners and associate them with an environment",
     *     operationId="onboardBusinessLearners",
     *     tags={"Onboarding"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"environment_id", "learners"},
     *             @OA\Property(property="environment_id", type="integer", example=1),
     *             @OA\Property(
     *                 property="learners",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"name", "email"},
     *                     @OA\Property(property="name", type="string", example="John Doe"),
     *                     @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *                     @OA\Property(property="employee_id", type="string", example="EMP123"),
     *                     @OA\Property(property="department", type="string", example="Engineering"),
     *                     @OA\Property(property="position", type="string", example="Software Developer")
     *                 )
     *             ),
     *             @OA\Property(property="send_invitations", type="boolean", example=true),
     *             @OA\Property(property="custom_message", type="string", example="Welcome to our learning platform!")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Business learners onboarded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Business learners onboarded successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="environment_id", type="integer", example=1),
     *                 @OA\Property(property="learners_created", type="integer", example=5),
     *                 @OA\Property(property="learner_ids", type="array", @OA\Items(type="integer"))
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
            'environment_id' => 'required|exists:environments,id',
            'learners' => 'required|array|min:1',
            'learners.*.name' => 'required|string|max:255',
            'learners.*.email' => 'required|string|email|max:255|unique:users,email',
            'learners.*.employee_id' => 'nullable|string|max:50',
            'learners.*.department' => 'nullable|string|max:100',
            'learners.*.position' => 'nullable|string|max:100',
            'send_invitations' => 'nullable|boolean',
            'custom_message' => 'nullable|string|max:500',
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
                
                // Check if the environment belongs to a business subscription
                if (!$environment->owner || !$environment->owner->company_name) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'This environment is not associated with a business account'
                    ], 422);
                }

                $learnerIds = [];
                $sendInvitations = $request->input('send_invitations', true);
                $customMessage = $request->input('custom_message', 'You have been invited to join our learning platform.');

                foreach ($request->learners as $learnerData) {
                    // Create a random password
                    $password = Str::random(12);
                    
                    // Create the user
                    $user = User::create([
                        'name' => $learnerData['name'],
                        'email' => $learnerData['email'],
                        'password' => Hash::make($password),
                        'company_name' => $environment->company_name,
                        'employee_id' => $learnerData['employee_id'] ?? null,
                        'department' => $learnerData['department'] ?? null,
                        'position' => $learnerData['position'] ?? null,
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
                            'environment_specific_id' => 'BLRN-' . $user->id,
                            'employee_id' => $learnerData['employee_id'] ?? null,
                            'department' => $learnerData['department'] ?? null,
                            'position' => $learnerData['position'] ?? null,
                        ]),
                        'environment_email' => $user->email,
                        'environment_password' => Hash::make($password),
                        'use_environment_credentials' => true,
                    ]);

                    $learnerIds[] = $user->id;

                    // TODO: Send invitation email if requested
                    if ($sendInvitations) {
                        // Code to send invitation email with the password and custom message
                        // This would typically call a notification or mail class
                    }
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Business learners onboarded successfully',
                    'data' => [
                        'environment_id' => (int)$environment->id,
                        'learners_created' => count($learnerIds),
                        'learner_ids' => $learnerIds,
                    ]
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to onboard business learners',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
