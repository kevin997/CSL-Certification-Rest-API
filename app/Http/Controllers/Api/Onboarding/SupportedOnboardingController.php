<?php

namespace App\Http\Controllers\Api\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Tax\TaxZoneService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SupportedOnboardingController extends Controller
{
    /**
     * Onboard a new user with the supported plan.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/api/onboarding/supported",
     *     summary="Onboard a new user with the supported plan",
     *     description="Create a new user account, environment, subscription, and process payment for the supported plan",
     *     operationId="onboardSupported",
     *     tags={"Onboarding"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"name", "email", "password", "environment_name", "domain_type", "domain", "payment_method", "payment_token"},
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="password", type="string", format="password", example="password123"),
     *             @OA\Property(property="environment_name", type="string", example="John's Academy"),
     *             @OA\Property(property="domain_type", type="string", enum={"subdomain", "custom"}, example="subdomain"),
     *             @OA\Property(property="domain", type="string", example="johns-academy"),
     *             @OA\Property(property="description", type="string", example="A platform for teaching computer science"),
     *             @OA\Property(property="payment_method", type="string", enum={"stripe", "lygos"}, example="stripe"),
     *             @OA\Property(property="payment_token", type="string", example="tok_visa")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="User onboarded successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="User onboarded successfully"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="user_id", type="integer", example=1),
     *                 @OA\Property(property="environment_id", type="integer", example=1),
     *                 @OA\Property(property="subscription_id", type="integer", example=1),
     *                 @OA\Property(property="payment_id", type="integer", example=1),
     *                 @OA\Property(property="domain", type="string", example="johns-academy.csl-cert.com")
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
            'payment_method' => 'required|in:stripe,lygos',
            'payment_token' => 'required|string',
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
                // Get the supported plan
                $plan = Plan::where('type', 'supported')->firstOrFail();
                
                // Format the domain based on domain_type
                $primaryDomain = $this->formatDomain($request->domain_type, $request->domain);
                
                // Check if the domain is already taken
                if (Environment::where('primary_domain', $primaryDomain)->exists()) {
                    return response()->json([
                        'status' => 'error',
                        'errors' => ['domain' => 'This domain is already taken']
                    ], 422);
                }
                
                // Process payment
                $paymentResult = $this->processPayment(
                    $request->payment_method,
                    $request->payment_token,
                    $plan->pricing['setup_fee'] ?? 177.00, // Default to $177 if not specified in plan
                    $request->email,
                    'CSL Brands Learning Platform Setup Fee'
                );
                
                if (!$paymentResult['success']) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Payment processing failed',
                        'error' => $paymentResult['error']
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
                    'country_code' => $request->country_code ?? 'CM', // Default to Cameroon if not provided
                    'state_code' => $request->state_code, // Null by default if not provided
                ]);
                
                // Create the subscription
                $subscription = Subscription::create([
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'environment_id' => $environment->id,
                    'billing_cycle' => 'monthly',
                    'start_date' => now(),
                    'end_date' => now()->addMonth(), // Initial 1-month subscription
                    'status' => 'active',
                    'is_trial' => false,
                ]);
                
                // Record the payment
                $payment = Payment::create([
                    'user_id' => $user->id,
                    'subscription_id' => $subscription->id,
                    'amount' => $plan->setup_fee ?? 177.00,
                    'currency' => 'USD',
                    'payment_method' => $request->payment_method,
                    'transaction_id' => $paymentResult['transaction_id'],
                    'status' => Payment::STATUS_PENDING,
                    'description' => 'Initial setup fee for Supported plan',
                ]);
                
                // Assign a CSL support representative (this would be implemented in a real application)
                // SupportAssignment::create(['user_id' => $user->id, 'support_rep_id' => $availableRep->id]);
                
                // Send welcome email and notify support team (this would be implemented in a real application)
                // Mail::to($user->email)->send(new SupportedWelcomeEmail($user, $environment));
                // Mail::to('support@csl-brands.com')->send(new NewSupportedCustomerNotification($user, $environment));
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'Your supported learning environment has been created successfully!',
                    'data' => [
                        'user_id' => $user->id,
                        'environment_id' => $environment->id,
                        'subscription_id' => $subscription->id,
                        'payment_id' => $payment->id,
                        'domain' => $primaryDomain,
                    ]
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while creating your environment',
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
    
    /**
     * Process payment using the specified payment gateway.
     *
     * @param  string  $paymentMethod
     * @param  string  $paymentToken
     * @param  float  $amount
     * @param  string  $email
     * @param  string  $description
     * @return array
     */
    private function processPayment($paymentMethod, $paymentToken, $amount, $email, $description)
    {
        // In a real application, this would integrate with actual payment gateways
        // For now, we'll simulate a successful payment
        
        if ($paymentMethod === 'stripe') {
            try {
                // Simulate Stripe API call
                // In a real application, you would use the Stripe SDK
                // \Stripe\Charge::create([
                //     'amount' => $amount * 100, // Stripe uses cents
                //     'currency' => 'usd',
                //     'source' => $paymentToken,
                //     'description' => $description,
                //     'receipt_email' => $email,
                // ]);
                
                return [
                    'success' => true,
                    'transaction_id' => 'stripe_' . Str::random(16),
                ];
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        } elseif ($paymentMethod === 'lygos') {
            try {
                // Simulate Lygos payment processing
                // In a real application, you would integrate with the Lygos API
                
                return [
                    'success' => true,
                    'transaction_id' => 'lygos_' . Str::random(16),
                ];
            } catch (\Exception $e) {
                return [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return [
            'success' => false,
            'error' => 'Invalid payment method',
        ];
    }
}
