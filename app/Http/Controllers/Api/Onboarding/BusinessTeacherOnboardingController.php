<?php

namespace App\Http\Controllers\Api\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class BusinessTeacherOnboardingController extends Controller
{
    /**
     * Onboard a new business teacher.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/api/onboarding/business-teacher",
     *     summary="Onboard a new business teacher",
     *     description="Create a new user account, environment, and subscription for a business teacher",
     *     operationId="onboardBusinessTeacher",
     *     tags={"Onboarding"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "company_name", "environment_name", "domain", "plan_id", "billing_cycle"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="company_name", type="string", example="ABC Corporation"),
     *             @OA\Property(property="environment_name", type="string", example="ABC Training Academy"),
     *             @OA\Property(property="domain", type="string", example="abc-training.csl-cert.com"),
     *             @OA\Property(property="plan_id", type="integer", example=2),
     *             @OA\Property(property="billing_cycle", type="string", enum={"monthly", "annual"}, example="annual"),
     *             @OA\Property(property="theme_color", type="string", example="#3366FF"),
     *             @OA\Property(property="logo_url", type="string", example="https://example.com/logo.png"),
     *             @OA\Property(property="description", type="string", example="Corporate training platform for ABC Corp"),
     *             @OA\Property(property="additional_admins", type="array", 
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="name", type="string", example="Jane Smith"),
     *                     @OA\Property(property="email", type="string", format="email", example="jane@example.com")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Business teacher onboarded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Business teacher onboarded successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="environment_id", type="integer", example=1),
     *                 @OA\Property(property="subscription_id", type="integer", example=1),
     *                 @OA\Property(property="additional_admins", type="array", @OA\Items(type="integer"))
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
            'company_name' => 'required|string|max:255',
            'environment_name' => 'required|string|max:255',
            'domain' => 'required|string|max:255|unique:environments,primary_domain',
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,annual',
            'theme_color' => 'nullable|string|max:7',
            'logo_url' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'additional_admins' => 'nullable|array',
            'additional_admins.*.name' => 'required|string|max:255',
            'additional_admins.*.email' => 'required|string|email|max:255|unique:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if the plan is valid for business teachers
        $plan = Plan::findOrFail($request->plan_id);
        if ($plan->type !== 'business_teacher') {
            return response()->json([
                'status' => 'error',
                'message' => 'The selected plan is not valid for business teachers'
            ], 422);
        }

        try {
            // Use a transaction to ensure all operations succeed or fail together
            return DB::transaction(function () use ($request, $plan) {
                // Create the primary user (owner)
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'email_verified_at' => now(), // Auto-verify for simplicity
                    'company_name' => $request->company_name,
                ]);

                // Create the environment
                $environment = Environment::create([
                    'name' => $request->environment_name,
                    'primary_domain' => $request->domain,
                    'theme_color' => $request->theme_color,
                    'logo_url' => $request->logo_url,
                    'favicon_url' => $request->logo_url, // Default to logo_url
                    'description' => $request->description,
                    'owner_id' => $user->id,
                    'is_active' => true,
                    'company_name' => $request->company_name,
                ]);

                // Associate the primary user with the environment as an owner
                $environment->users()->attach($user->id, [
                    'role' => 'owner',
                    'permissions' => json_encode([
                        'manage_users' => true,
                        'manage_courses' => true,
                        'manage_content' => true,
                        'manage_billing' => true,
                    ]),
                    'joined_at' => now(),
                    'is_active' => true,
                    'credentials' => json_encode([
                        'username' => $user->email,
                        'environment_specific_id' => 'BTCH-' . $user->id,
                    ]),
                    'environment_email' => $user->email,
                    'environment_password' => Hash::make($request->password),
                    'use_environment_credentials' => true,
                ]);

                // Create a subscription
                $subscription = Subscription::create([
                    'environment_id' => $environment->id,
                    'plan_id' => $plan->id,
                    'billing_cycle' => $request->billing_cycle,
                    'status' => 'active',
                    'starts_at' => now(),
                    'ends_at' => $request->billing_cycle === 'monthly' ? now()->addMonth() : now()->addYear(),
                    'setup_fee_paid' => false, // Will be handled by the payment process
                ]);

                // Process additional admins if provided
                $additionalAdminIds = [];
                if ($request->has('additional_admins') && is_array($request->additional_admins)) {
                    foreach ($request->additional_admins as $adminData) {
                        // Create a random password
                        $password = Str::random(12);
                        
                        // Create the admin user
                        $admin = User::create([
                            'name' => $adminData['name'],
                            'email' => $adminData['email'],
                            'password' => Hash::make($password),
                            'company_name' => $request->company_name,
                        ]);

                        // Associate the admin with the environment
                        $environment->users()->attach($admin->id, [
                            'role' => 'admin',
                            'permissions' => json_encode([
                                'manage_users' => true,
                                'manage_courses' => true,
                                'manage_content' => true,
                                'manage_billing' => false, // Only the owner can manage billing
                            ]),
                            'joined_at' => now(),
                            'is_active' => true,
                            'credentials' => json_encode([
                                'username' => $admin->email,
                                'environment_specific_id' => 'BADM-' . $admin->id,
                            ]),
                            'environment_email' => $admin->email,
                            'environment_password' => Hash::make($password), // Use the generated password
                            'use_environment_credentials' => true,
                        ]);

                        $additionalAdminIds[] = $admin->id;

                        // TODO: Send invitation email to the admin
                    }
                }

                return response()->json([
                    'status' => 'success',
                    'message' => 'Business teacher onboarded successfully',
                    'data' => [
                        'user_id' => (int)$user->id,
                        'environment_id' => (int)$environment->id,
                        'subscription_id' => (int)$subscription->id,
                        'additional_admins' => $additionalAdminIds,
                    ]
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to onboard business teacher',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
