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

class AfterPlanSelectionOnboarding extends Controller
{
    /**
     * Onboard a new individual teacher.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/api/onboarding/teacher",
     *     summary="Onboard a new individual teacher",
     *     description="Create a new user account, environment, and subscription for an individual teacher",
     *     operationId="onboardTeacher",
     *     tags={"Onboarding"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "environment_name", "domain", "plan_id", "billing_cycle"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="environment_name", type="string", example="John's Academy"),
     *             @OA\Property(property="domain", type="string", example="johns-academy.csl-cert.com"),
     *             @OA\Property(property="plan_id", type="integer", example=1),
     *             @OA\Property(property="billing_cycle", type="string", enum={"monthly", "annual"}, example="monthly"),
     *             @OA\Property(property="theme_color", type="string", example="#FF5733"),
     *             @OA\Property(property="logo_url", type="string", example="https://example.com/logo.png"),
     *             @OA\Property(property="description", type="string", example="A platform for teaching computer science")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Teacher onboarded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Teacher onboarded successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="environment_id", type="integer", example=1),
     *                 @OA\Property(property="subscription_id", type="integer", example=1)
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
            'environment_name' => 'required|string|max:255',
            'domain' => 'required|string|max:255|unique:environments,primary_domain',
            'plan_id' => 'required|exists:plans,id',
            'billing_cycle' => 'required|in:monthly,annual',
            'theme_color' => 'nullable|string|max:7',
            'logo_url' => 'nullable|string|max:255',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if the plan is valid for individual teachers
        $plan = Plan::findOrFail($request->plan_id);
        if ($plan->type !== 'individual_teacher') {
            return response()->json([
                'status' => 'error',
                'message' => 'The selected plan is not valid for individual teachers'
            ], 422);
        }

        try {
            // Use a transaction to ensure all operations succeed or fail together
            return DB::transaction(function () use ($request, $plan) {
                // Create the user
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'email_verified_at' => now(), // Auto-verify for simplicity
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

                return response()->json([
                    'status' => 'success',
                    'message' => 'Teacher onboarded successfully',
                    'data' => [
                        'user_id' => (int)$user->id,
                        'environment_id' => (int)$environment->id,
                        'subscription_id' => (int)$subscription->id,
                    ]
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to onboard teacher',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
