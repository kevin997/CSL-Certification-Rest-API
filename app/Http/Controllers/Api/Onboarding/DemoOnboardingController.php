<?php

namespace App\Http\Controllers\Api\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Tax\TaxZoneService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class DemoOnboardingController extends Controller
{
    /**
     * Onboard a new user with the demo plan.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/api/onboarding/demo",
     *     summary="Onboard a new user with the demo plan",
     *     description="Create a new user account, environment, and subscription for the demo plan",
     *     operationId="onboardDemo",
     *     tags={"Onboarding"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "environment_name", "domain_type", "domain"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="environment_name", type="string", example="John's Academy"),
     *             @OA\Property(property="domain_type", type="string", enum={"subdomain", "custom"}, example="subdomain"),
     *             @OA\Property(property="domain", type="string", example="johns-academy"),
     *             @OA\Property(property="description", type="string", example="A platform for teaching computer science"),
     *             @OA\Property(property="referral_code", type="string", example="FRIEND50")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User onboarded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Your demo environment has been created successfully!"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="environment_id", type="integer", example=1),
     *                 @OA\Property(property="subscription_id", type="integer", example=1),
     *                 @OA\Property(property="domain", type="string", example="johns-academy.csl-cert.com"),
     *                 @OA\Property(property="expires_at", type="string", format="date-time", example="2023-01-15T00:00:00Z")
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
            'domain_type' => 'required|in:subdomain,custom',
            'domain' => 'required|string|max:255',
            'description' => 'nullable|string',
            'country_code' => 'nullable|string|size:2',
            'state_code' => 'nullable|string',
            'referral_code' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Start a database transaction
            return DB::transaction(function () use ($request) {
                // Get the demo plan
                $plan = Plan::where('type', 'demo')->firstOrFail();
                
                // Format the domain based on domain_type
                $primaryDomain = $this->formatDomain($request->domain_type, $request->domain);
                
                // Check if the domain is already taken
                if (Environment::where('primary_domain', $primaryDomain)->exists()) {
                    return response()->json([
                        'status' => 'error',
                        'errors' => ['domain' => 'This domain is already taken']
                    ], 422);
                }
                
                // Create the user
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'role' => 'admin',
                    'email_verified_at' => now(),
                ]);
                
                // Create the environment
                $environment = Environment::create([
                    'name' => $request->environment_name,
                    'primary_domain' => $primaryDomain,
                    'description' => $request->description,
                    'owner_id' => $user->id,
                    'theme_color' => '#1C692F', // CSL Brands green
                    'is_active' => true,
                    'is_demo' => true, // Mark this as a demo environment
                    'country_code' => $request->country_code ?? 'CM', // Default to Cameroon if not provided
                    'state_code' => $request->state_code // Null by default if not provided
                ]);
                
                // Calculate expiration date (14 days from now)
                $expiresAt = Carbon::now()->addDays(14);
                
                // Create the subscription
                $subscription = Subscription::create([
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'environment_id' => $environment->id,
                    'billing_cycle' => 'monthly',
                    'start_date' => now(),
                    'end_date' => $expiresAt, // Demo expires after 14 days
                    'status' => 'active',
                    'is_trial' => true,
                    'referral_code' => $request->referral_code,
                ]);
                
                // Send welcome email (this would be implemented in a real application)
                // Mail::to($user->email)->send(new DemoWelcomeEmail($user, $environment, $expiresAt));
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'Your demo environment has been created successfully!',
                    'data' => [
                        'user_id' => $user->id,
                        'environment_id' => $environment->id,
                        'subscription_id' => $subscription->id,
                        'domain' => $primaryDomain,
                        'expires_at' => $expiresAt->toISOString(),
                    ]
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while creating your demo environment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Format the domain based on the domain type.
     *
     * @param  string  $domainType
     * @param  string  $domain
     * @return string
     */
    private function formatDomain($domainType, $domain)
    {
        if ($domainType === 'subdomain') {
            // Sanitize subdomain (remove special characters, convert to lowercase)
            $sanitizedDomain = Str::slug($domain);
            return $sanitizedDomain . '.csl-cert.com';
        } else {
            // For custom domains, return as is
            return $domain;
        }
    }
}
