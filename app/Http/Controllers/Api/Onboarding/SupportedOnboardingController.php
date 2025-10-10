<?php

namespace App\Http\Controllers\Api\Onboarding;

use App\Http\Controllers\Controller;
use App\Mail\EnvironmentSetupMail;
use App\Models\Environment;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\EnvironmentCreatedNotification;
use App\Services\SubscriptionManager;
use App\Services\Tax\TaxZoneService;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
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
     *             @OA\Property(property="payment_method", type="string", enum={"stripe", "lygos", "paypal", "monetbill"}, example="stripe"),
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
     *                 @OA\Property(property="domain", type="string", example="johns-academy.csl-brands.com")
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
        // First check if this is a retry for an existing user with failed payment
        $existingUser = User::where('email', $request->email)->first();
        $isRetryAttempt = false;
        
        if ($existingUser) {
            // Check if user has a supported plan subscription with failed/pending payment
            $failedSubscription = Subscription::where('user_id', $existingUser->id)
                ->whereHas('plan', function($query) {
                    $query->where('type', 'supported');
                })
                ->whereHas('payments', function($query) {
                    $query->whereIn('status', ['failed', 'pending', 'processing']);
                })
                ->first();
            
            if ($failedSubscription) {
                $isRetryAttempt = true;
                Log::info('Retry attempt detected for supported onboarding', [
                    'email' => $request->email,
                    'user_id' => $existingUser->id,
                    'subscription_id' => $failedSubscription->id
                ]);
            }
        }
        
        // Validate the request with conditional email uniqueness
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => $isRetryAttempt ? 'required|string|email|max:255' : 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
            'whatsapp_number' => 'required|string|max:20',
            'environment_name' => 'required|string|max:255',
            'domain_type' => 'required|in:subdomain,custom',
            'domain' => 'required|string|max:255',
            'description' => 'nullable|string',
            'country_code' => 'nullable|string|size:2',
            'state_code' => 'nullable|string',
            'payment_method' => 'required|in:stripe,lygos,paypal,monetbill,taramoney',
            'payment_token' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // If this is a retry attempt, handle it differently
        if ($isRetryAttempt) {
            return $this->handleRetryPayment($request, $existingUser);
        }

        try {
            // Start a database transaction with explicit handling
            DB::beginTransaction();
            
            try {
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
                
                // Create the user
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'whatsapp_number' => $request->whatsapp_number,
                    'role' => 'company_teacher',
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
                
                // Use SubscriptionManager to handle subscription and payment creation
                $subscriptionManager = app(SubscriptionManager::class);
                
                $subscriptionData = [
                    'user_id' => $user->id,
                    'plan_id' => $plan->id,
                    'environment_id' => $environment->id,
                    'billing_cycle' => 'monthly',
                    'start_date' => now(),
                    'end_date' => now()->addMonth(),
                    'status' => Subscription::PENDING,
                    'is_trial' => false,
                ];
                
                $paymentData = [
                    'payment_method' => $request->payment_method,
                    'payment_token' => $request->payment_token,
                    'currency' => 'USD',
                    'amount' => $plan->setup_fee ?? 177.00,
                ];
                
                $subscriptionResult = $subscriptionManager->createSubscriptionWithPayment(
                    $subscriptionData,
                    $paymentData
                );
                
                if (!$subscriptionResult['success']) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Payment processing failed',
                        'error' => $subscriptionResult['message']
                    ], 422);
                }
                
                $subscription = $subscriptionResult['subscription'];
                $payment = $subscriptionResult['payment'];
                $paymentResponseData = $subscriptionResult['payment_data'] ?? [];
                
                // Assign a CSL support representative (this would be implemented in a real application)
                // SupportAssignment::create(['user_id' => $user->id, 'support_rep_id' => $availableRep->id]);
                
                // Generate admin credentials for the environment
                $adminEmail = $user->email;
                $adminPassword = $request->password;
                
                // Send environment setup mail
                Mail::to($user->email)->send(new EnvironmentSetupMail(
                    $environment,
                    $user,
                    $adminEmail,
                    $adminPassword
                ));
                
                // Send Telegram notification
                try {
                    $telegramService = app(TelegramService::class);
                    $notification = 
                        new EnvironmentCreatedNotification(
                            $environment,
                            $user,
                            $adminEmail,
                            $adminPassword,
                            $telegramService
                        );
                   $notification->toTelegram($notification); 
                } catch (\Exception $e) {
                    // Log the error but don't fail the entire process
                    Log::error('Failed to send Telegram notification for supported environment creation: ' . $e->getMessage());
                }
                
                // Prepare response data with payment information
                $responseData = [
                    'user_id' => $user->id,
                    'environment_id' => $environment->id,
                    'subscription_id' => $subscription->id,
                    'payment_id' => $payment->id,
                    'domain' => $primaryDomain,
                    'total_amount' => $payment->total_amount,
                    'currency' => $payment->currency,
                    'payment_method' => $payment->payment_method,
                    'transaction_id' => $payment->transaction_id,
                ];
                
                // Add payment-specific data based on payment method
                if ($paymentResponseData) {
                    if ($request->payment_method === 'stripe') {
                        $responseData['payment_type'] = 'stripe';
                        $responseData['client_secret'] = $paymentResponseData['client_secret'] ?? null;
                        $responseData['publishable_key'] = $paymentResponseData['publishable_key'] ?? null;
                        $responseData['payment_intent_id'] = $paymentResponseData['payment_intent_id'] ?? null;
                    } elseif ($request->payment_method === 'monetbill') {
                        $responseData['payment_type'] = 'monetbill';
                        $responseData['redirect_url'] = $paymentResponseData['checkout_url'] ?? null;
                        $responseData['converted_amount'] = $paymentResponseData['converted_amount'] ?? null;
                        $responseData['converted_currency'] = $paymentResponseData['converted_currency'] ?? null;
                    } elseif ($request->payment_method === 'taramoney') {
                        $responseData['payment_type'] = 'taramoney';
                        $responseData['payment_links'] = $paymentResponseData['payment_links'] ?? [];
                        $responseData['whatsapp_link'] = $paymentResponseData['whatsapp_link'] ?? null;
                        $responseData['telegram_link'] = $paymentResponseData['telegram_link'] ?? null;
                        $responseData['dikalo_link'] = $paymentResponseData['dikalo_link'] ?? null;
                        $responseData['sms_link'] = $paymentResponseData['sms_link'] ?? null;
                    } else {
                        $responseData['payment_type'] = 'standard';
                        $responseData['redirect_url'] = $paymentResponseData['checkout_url'] ?? null;
                    }
                } else {
                    $responseData['payment_type'] = 'standard';
                }
                
                DB::commit();
                
                return response()->json([
                    'status' => 'success',
                    'message' => 'Your supported learning environment has been created successfully!',
                    'data' => $responseData
                ], 201);
                
            } catch (\Exception $innerException) {
                // Explicitly roll back the transaction if anything fails
                DB::rollBack();
                
                Log::error('Failed to create supported environment', [
                    'error' => $innerException->getMessage(),
                    'trace' => $innerException->getTraceAsString(),
                    'request_data' => $request->except(['password', 'payment_token'])
                ]);
                
                throw $innerException; // Re-throw to be caught by outer catch
            }
        } catch (\Exception $e) {
            // Ensure transaction is rolled back in case the inner catch didn't execute
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }
            
            Log::error('Exception in supported environment creation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_email' => $request->email ?? null,
                'request_domain' => $request->domain ?? null
            ]);
            
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
            // Remove http:// or https:// if present
            $domain = preg_replace('#^https?://#', '', $domain);
            
            // Convert to lowercase
            $domain = strtolower($domain);
            
            // Remove any special characters not allowed in domains
            $domain = preg_replace('/[^a-z0-9.-]/', '-', $domain);
            
            return $domain;
        } else {
            // For custom domains, return as is after removing protocol
            return preg_replace('#^https?://#', '', $domain);
        }
    }
    
    /**
     * Handle retry payment for existing user with failed/pending payment
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\User  $existingUser
     * @return \Illuminate\Http\Response
     */
    private function handleRetryPayment(Request $request, User $existingUser)
    {
        try {
            DB::beginTransaction();
            
            // Get the supported plan
            $plan = Plan::where('type', 'supported')->firstOrFail();
            
            // Find the existing subscription with failed/pending payment
            $subscription = Subscription::where('user_id', $existingUser->id)
                ->whereHas('plan', function($query) {
                    $query->where('type', 'supported');
                })
                ->whereHas('payments', function($query) {
                    $query->whereIn('status', ['failed', 'pending', 'processing']);
                })
                ->first();
            
            if (!$subscription) {
                throw new \Exception('No pending subscription found for retry');
            }
            
            // Get the environment associated with this subscription
            $environment = Environment::where('id', $subscription->environment_id)->first();
            
            if (!$environment) {
                throw new \Exception('Environment not found for existing subscription');
            }
            
            // Find the most recent failed/pending payment for this subscription
            $existingPayment = Payment::where('subscription_id', $subscription->id)
                ->whereIn('status', ['failed', 'pending', 'processing'])
                ->orderBy('created_at', 'desc')
                ->first();
            
            if ($existingPayment) {
                // Update the existing payment record with new payment attempt
                $existingPayment->update([
                    'payment_method' => $request->payment_method,
                    'status' => 'processing',
                    'updated_at' => now()
                ]);
                
                Log::info('Retrying payment for existing subscription', [
                    'user_id' => $existingUser->id,
                    'subscription_id' => $subscription->id,
                    'payment_id' => $existingPayment->id,
                    'payment_method' => $request->payment_method
                ]);
            }
            
            // Use SubscriptionManager to retry the payment
            $subscriptionManager = app(SubscriptionManager::class);
            
            $paymentData = [
                'payment_method' => $request->payment_method,
                'payment_token' => $request->payment_token,
                'currency' => 'USD',
                'amount' => $plan->setup_fee ?? 177.00,
            ];
            
            // Try to process the payment using the existing subscription
            $subscriptionResult = $subscriptionManager->retryPayment($subscription, $paymentData);
            
            if (!$subscriptionResult['success']) {
                // Payment failed again - log and return error
                Log::error('Payment retry failed for supported onboarding', [
                    'user_id' => $existingUser->id,
                    'subscription_id' => $subscription->id,
                    'error' => $subscriptionResult['message']
                ]);
                
                return response()->json([
                    'status' => 'error',
                    'message' => 'Payment processing failed',
                    'error' => $subscriptionResult['message']
                ], 422);
            }
            
            $payment = $subscriptionResult['payment'];
            $paymentResponseData = $subscriptionResult['payment_data'] ?? [];
            
            // Update subscription status to active if payment succeeded
            $subscription->update([
                'status' => Subscription::PENDING,
                'updated_at' => now()
            ]);
            
            // Update environment to active if not already
            $environment->update([
                'is_active' => true,
                'updated_at' => now()
            ]);
            
            
            // Prepare response data
            $responseData = [
                'user_id' => $existingUser->id,
                'environment_id' => $environment->id,
                'subscription_id' => $subscription->id,
                'payment_id' => $payment->id,
                'domain' => $environment->primary_domain,
                'total_amount' => $payment->total_amount,
                'currency' => $payment->currency,
                'payment_method' => $payment->payment_method,
                'transaction_id' => $payment->transaction_id,
                'retry_attempt' => true,
            ];
            
            // Add payment-specific data based on payment method
            if ($paymentResponseData) {
                if ($request->payment_method === 'stripe') {
                    $responseData['payment_type'] = 'stripe';
                    $responseData['client_secret'] = $paymentResponseData['client_secret'] ?? null;
                    $responseData['publishable_key'] = $paymentResponseData['publishable_key'] ?? null;
                    $responseData['payment_intent_id'] = $paymentResponseData['payment_intent_id'] ?? null;
                } elseif ($request->payment_method === 'monetbill') {
                    $responseData['payment_type'] = 'monetbill';
                    $responseData['redirect_url'] = $paymentResponseData['checkout_url'] ?? null;
                    $responseData['converted_amount'] = $paymentResponseData['converted_amount'] ?? null;
                    $responseData['converted_currency'] = $paymentResponseData['converted_currency'] ?? null;
                } else {
                    $responseData['payment_type'] = 'standard';
                    $responseData['redirect_url'] = $paymentResponseData['checkout_url'] ?? null;
                }
            } else {
                $responseData['payment_type'] = 'standard';
            }
            
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Payment retry successful! Your supported learning environment is now active.',
                'data' => $responseData
            ], 200);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Exception in supported onboarding retry', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => $existingUser->id,
                'request_email' => $request->email
            ]);
            
            return response()->json([
                'status' => 'error',
                'message' => 'An error occurred while retrying your payment',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
}
