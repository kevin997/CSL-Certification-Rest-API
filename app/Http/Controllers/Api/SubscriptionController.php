<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\Payment;
use App\Services\PlatformPaymentService;
use App\Services\SubscriptionManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class SubscriptionController extends Controller
{
    protected $subscriptionManager;
    protected $platformPaymentService;

    public function __construct(SubscriptionManager $subscriptionManager, PlatformPaymentService $platformPaymentService)
    {
        $this->subscriptionManager = $subscriptionManager;
        $this->platformPaymentService = $platformPaymentService;
    }

    /**
     * Get current user's subscription
     *
     * @return \Illuminate\Http\Response
     */
    public function current(Request $request)
    {
        try {
            // Try to get user from auth first (if authenticated)
            $user = Auth::user();

            // If no authenticated user, try to find user via environment domain
            if (!$user) {
                // Try to get domain from headers (same logic as EnvironmentController::status)
                $domain = null;
                $frontendDomainHeader = $request->header('X-Frontend-Domain');
                $origin = $request->header('Origin');
                $referer = $request->header('Referer');

                if ($frontendDomainHeader) {
                    $domain = $frontendDomainHeader;
                } elseif ($origin) {
                    $parsedOrigin = parse_url($origin);
                    $domain = $parsedOrigin['host'] ?? null;
                } elseif ($referer) {
                    $parsedReferer = parse_url($referer);
                    $domain = $parsedReferer['host'] ?? null;
                }

                if ($domain) {
                    // Find environment by domain
                    $environment = \App\Models\Environment::where('primary_domain', $domain)
                        ->orWhere(function ($query) use ($domain) {
                            $query->whereNotNull('additional_domains')
                                ->whereJsonContains('additional_domains', $domain);
                        })
                        ->first();

                    if ($environment) {
                        // Get the environment owner
                        $user = $environment->user;
                    }
                }
            }

            if (!$user) {
                return response()->json([
                    'status' => 'success',
                    'data' => null,
                    'message' => 'No subscription found'
                ], 200);
            }

            $subscription = Subscription::where('user_id', $user->id)
                ->with(['plan', 'payments'])
                ->orderBy('created_at', 'desc')
                ->first();

            if (!$subscription) {
                return response()->json([
                    'status' => 'success',
                    'data' => null,
                    'message' => 'No subscription found'
                ], 200);
            }

            return response()->json([
                'status' => 'success',
                'data' => $subscription
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching current subscription: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch subscription'
            ], 500);
        }
    }

    /**
     * Get subscription by ID
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }

            $subscription = Subscription::where('id', $id)
                ->where('user_id', $user->id)
                ->with(['plan', 'payments'])
                ->first();

            if (!$subscription) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Subscription not found'
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'data' => $subscription
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching subscription: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch subscription'
            ], 500);
        }
    }

    /**
     * Get payments for a subscription
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function payments($id)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }

            $subscription = Subscription::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$subscription) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Subscription not found'
                ], 404);
            }

            $payments = Payment::where('subscription_id', $id)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $payments
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching subscription payments: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch payments'
            ], 500);
        }
    }

    /**
     * Retry payment for a subscription
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function retryPayment(Request $request, $id)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }

            // Validate request
            $validator = Validator::make($request->all(), [
                'payment_method' => 'required|string',
                'payment_token' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors()
                ], 422);
            }

            // Find subscription
            $subscription = Subscription::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$subscription) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Subscription not found'
                ], 404);
            }

            // Prepare payment data
            $paymentData = [
                'payment_method' => $request->payment_method,
                'payment_token' => $request->payment_token,
                'currency' => 'USD', // Default currency
                'amount' => $subscription->plan->setup_fee ?? 177.00,
            ];

            // Use SubscriptionManager to retry payment
            $result = $this->subscriptionManager->retryPayment($subscription, $paymentData);

            if (!$result['success']) {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message']
                ], 422);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Payment retry initiated successfully',
                'data' => [
                    'subscription_id' => $subscription->id,
                    'payment_id' => $result['payment']->id,
                    'payment_type' => $request->payment_method,
                    'payment_data' => $result['payment_data'] ?? null
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error retrying subscription payment: ' . $e->getMessage(), [
                'subscription_id' => $id,
                'user_id' => Auth::id(),
                'payment_method' => $request->payment_method ?? 'unknown'
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retry payment'
            ], 500);
        }
    }

    /**
     * Cancel subscription
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function cancel($id)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }

            $subscription = Subscription::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$subscription) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Subscription not found'
                ], 404);
            }

            // Update subscription status
            $subscription->update([
                'status' => Subscription::STATUS_CANCELED,
                'canceled_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Subscription cancelled successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error cancelling subscription: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to cancel subscription'
            ], 500);
        }
    }

    /**
     * Update subscription
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User not authenticated'
                ], 401);
            }

            $subscription = Subscription::where('id', $id)
                ->where('user_id', $user->id)
                ->first();

            if (!$subscription) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Subscription not found'
                ], 404);
            }

            // Validate and update allowed fields
            $allowedFields = ['billing_cycle', 'status'];
            $updateData = $request->only($allowedFields);

            if (!empty($updateData)) {
                $subscription->update($updateData);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Subscription updated successfully',
                'data' => $subscription->fresh()
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating subscription: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update subscription'
            ], 500);
        }
    }

    /**
     * Upgrade to a new plan (for demo environments)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function upgrade(Request $request): \Illuminate\Http\JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'User not authenticated'], 401);
            }

            $validator = Validator::make($request->all(), [
                'plan_id' => 'required|integer|exists:plans,id',
                'billing_cycle' => 'required|in:monthly,annual',
                'payment_method' => 'required|string',
                'payment_token' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
            }

            // Get the selected plan
            $plan = \App\Models\Plan::findOrFail($request->plan_id);

            // Check if user already has an active subscription
            $existingSubscription = Subscription::where('user_id', $user->id)
                ->whereIn('status', ['active', 'trial', 'pending'])
                ->first();

            if ($existingSubscription) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'User already has an active subscription. Use update endpoint instead.'
                ], 422);
            }

            // Calculate pricing based on billing cycle
            $amount = $request->billing_cycle === 'annual' ? $plan->price_annual : $plan->price_monthly;
            $totalAmount = $amount + $plan->setup_fee;

            // Prepare payment data
            $paymentData = [
                'payment_method' => $request->payment_method,
                'payment_token' => $request->payment_token,
                'currency' => 'USD',
                'amount' => $totalAmount,
                'billing_cycle' => $request->billing_cycle,
                'plan_id' => $plan->id,
            ];

            // Create subscription using SubscriptionManager
            // For now, we'll use the retry payment logic adapted for new subscriptions
            // This should be replaced with proper subscription creation logic
            $subscription = new Subscription([
                'user_id' => $user->id,
                'plan_id' => $plan->id,
                'status' => 'pending',
                'billing_cycle' => $request->billing_cycle,
                'start_date' => now(),
                'next_billing_date' => $request->billing_cycle === 'annual' ? now()->addYear() : now()->addMonth(),
            ]);
            $subscription->save();

            $result = $this->subscriptionManager->retryPayment($subscription, $paymentData);

            if (!$result['success']) {
                return response()->json([
                    'status' => 'error',
                    'message' => $result['message']
                ], 422);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Plan upgrade initiated successfully',
                'data' => [
                    'subscription_id' => $result['subscription']->id,
                    'payment_id' => $result['payment']->id ?? null,
                    'payment_type' => $request->payment_method,
                    'client_secret' => $result['payment_data']['client_secret'] ?? null,
                    'publishable_key' => $result['payment_data']['publishable_key'] ?? null,
                    'payment_intent_id' => $result['payment_data']['payment_intent_id'] ?? null,
                    'redirect_url' => $result['payment_data']['redirect_url'] ?? null,
                    'converted_amount' => $result['payment_data']['converted_amount'] ?? null,
                    'converted_currency' => $result['payment_data']['converted_currency'] ?? null,
                    'total_amount' => $totalAmount,
                    'currency' => 'USD'
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error upgrading plan: ' . $e->getMessage(), [
                'user_id' => Auth::id(),
                'plan_id' => $request->plan_id ?? 'unknown',
                'payment_method' => $request->payment_method ?? 'unknown'
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to upgrade plan'
            ], 500);
        }
    }

    /**
     * Calculate proration for plan change
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function calculateProration(Request $request, $id)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'User not authenticated'], 401);
            }

            $validator = Validator::make($request->all(), [
                'new_plan_id' => 'required|integer|exists:plans,id',
                'billing_cycle' => 'required|in:monthly,annual',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
            }

            $subscription = Subscription::where('id', $id)->where('user_id', $user->id)->first();
            if (!$subscription) {
                return response()->json(['status' => 'error', 'message' => 'Subscription not found'], 404);
            }

            $newPlan = \App\Models\Plan::findOrFail($request->new_plan_id);
            $currentPlan = $subscription->plan;

            // Calculate proration based on remaining days in current period
            $currentPeriodStart = new \DateTime($subscription->current_period_start);
            $currentPeriodEnd = new \DateTime($subscription->current_period_end);
            $now = new \DateTime();

            $totalDays = $currentPeriodEnd->diff($currentPeriodStart)->days;
            $remainingDays = $currentPeriodEnd->diff($now)->days;

            if ($totalDays <= 0) {
                $proratedAmount = 0;
            } else {
                $remainingRatio = $remainingDays / $totalDays;

                // Calculate current plan refund
                $currentAmount = $request->billing_cycle === 'annual' ? $currentPlan->price_annual : $currentPlan->price_monthly;
                $refundAmount = $currentAmount * $remainingRatio;

                // Calculate new plan charge
                $newAmount = $request->billing_cycle === 'annual' ? $newPlan->price_annual : $newPlan->price_monthly;
                $newCharge = $newAmount * $remainingRatio;

                $proratedAmount = $newCharge - $refundAmount;
            }

            return response()->json([
                'status' => 'success',
                'data' => [
                    'prorated_amount' => max(0, $proratedAmount),
                    'remaining_days' => $remainingDays,
                    'total_days' => $totalDays
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error calculating proration: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Failed to calculate proration'], 500);
        }
    }

    /**
     * Change subscription plan
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function changePlan(Request $request, $id)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'User not authenticated'], 401);
            }

            $validator = Validator::make($request->all(), [
                'new_plan_id' => 'required|integer|exists:plans,id',
                'billing_cycle' => 'nullable|in:monthly,annual',
                'payment_method' => 'nullable|string',
                'payment_id' => 'nullable|string',
                'transaction_id' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
            }

            $subscription = Subscription::where('id', $id)->where('user_id', $user->id)->first();
            if (!$subscription) {
                return response()->json(['status' => 'error', 'message' => 'Subscription not found'], 404);
            }

            $newPlan = \App\Models\Plan::findOrFail($request->new_plan_id);
            $billingCycle = $request->input('billing_cycle', $subscription->billing_cycle ?: 'monthly');
            $amountDue = $this->calculatePlanChangeAmount($subscription, $newPlan, $billingCycle);

            if ($amountDue > 0 && !$request->filled('transaction_id') && !$request->filled('payment_id')) {
                $paymentMethod = $request->input('payment_method', 'taramoney');
                $taxInfo = app(\App\Services\Tax\TaxZoneService::class)->calculateTaxByEnvironment(
                    $amountDue,
                    $subscription->environment_id
                );

                $payment = Payment::create([
                    'subscription_id' => $subscription->id,
                    'user_id' => $user->id,
                    'amount' => $amountDue,
                    'fee_amount' => 0,
                    'tax_amount' => $taxInfo['tax_amount'] ?? 0,
                    'tax_rate' => $taxInfo['tax_rate'] ?? 0,
                    'tax_zone' => $taxInfo['zone_name'] ?? null,
                    'total_amount' => $amountDue + ($taxInfo['tax_amount'] ?? 0),
                    'currency' => $request->input('currency', 'USD'),
                    'payment_method' => $paymentMethod,
                    'status' => Payment::STATUS_PENDING,
                    'description' => "Plan change to {$newPlan->name}",
                    'metadata' => [
                        'type' => 'subscription_plan_change',
                        'new_plan_id' => $newPlan->id,
                        'billing_cycle' => $billingCycle,
                    ],
                ]);

                $paymentResult = $this->platformPaymentService->initiate([
                    'gateway' => $paymentMethod,
                    'environment_id' => $subscription->environment_id,
                    'customer_id' => $user->id,
                    'customer_email' => $user->email,
                    'customer_name' => $user->name,
                    'amount' => $payment->amount,
                    'fee_amount' => $payment->fee_amount,
                    'tax_amount' => $payment->tax_amount,
                    'tax_rate' => $payment->tax_rate,
                    'tax_zone' => $payment->tax_zone,
                    'total_amount' => $payment->total_amount,
                    'currency' => $payment->currency,
                    'description' => $payment->description,
                    'source_type' => 'subscription_plan_change',
                    'source_id' => (string) $subscription->id,
                    'created_by' => $user->id,
                    'metadata' => [
                        'payment_id' => $payment->id,
                        'subscription_id' => $subscription->id,
                        'new_plan_id' => $newPlan->id,
                        'billing_cycle' => $billingCycle,
                    ],
                    'success_url' => $request->input('success_url'),
                    'cancel_url' => $request->input('cancel_url'),
                ]);

                if (!($paymentResult['success'] ?? false)) {
                    $payment->markAsFailed(null, 'failed', $paymentResult);
                    return response()->json([
                        'status' => 'error',
                        'message' => $paymentResult['message'] ?? 'Failed to initiate plan change payment',
                    ], 422);
                }

                $payment->update([
                    'transaction_id' => $paymentResult['transaction']->transaction_id,
                    'gateway_transaction_id' => $paymentResult['transaction']->gateway_transaction_id,
                    'gateway_status' => $paymentResult['transaction']->gateway_status ?? 'pending',
                    'gateway_response' => $paymentResult['gateway_response'] ?? $paymentResult,
                ]);

                return response()->json([
                    'status' => 'success',
                    'requires_payment' => true,
                    'payment_data' => $paymentResult['payment_data'],
                    'data' => $subscription->load(['plan', 'payments']),
                    'message' => 'Plan change payment initiated',
                ], 200);
            }

            if ($amountDue > 0) {
                $completedPayment = Payment::where('subscription_id', $subscription->id)
                    ->where('status', Payment::STATUS_COMPLETED)
                    ->where(function ($query) use ($request) {
                        $query->when($request->filled('transaction_id'), fn ($query) => $query->where('transaction_id', $request->transaction_id)->orWhere('gateway_transaction_id', $request->transaction_id))
                            ->when($request->filled('payment_id'), fn ($query) => $query->orWhere('transaction_id', $request->payment_id)->orWhere('gateway_transaction_id', $request->payment_id));
                    })
                    ->first();

                if (!$completedPayment) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'A completed platform payment is required before this plan can be changed',
                    ], 402);
                }
            }

            $this->applyPlanChange($subscription, $newPlan, $billingCycle);

            // Create payment record if payment details provided
            if (($request->payment_id || $request->transaction_id) && $amountDue <= 0) {
                Payment::create([
                    'subscription_id' => $subscription->id,
                    'user_id' => $user->id,
                    'amount' => 0, // Proration amount would be calculated
                    'total_amount' => 0,
                    'currency' => 'USD',
                    'payment_method' => 'plan_change',
                    'status' => 'completed',
                    'description' => "Plan change to {$newPlan->name}",
                    'gateway_transaction_id' => $request->transaction_id,
                ]);
            }

            $subscription->load(['plan', 'payments']);

            return response()->json([
                'status' => 'success',
                'requires_payment' => false,
                'data' => $subscription,
                'message' => 'Plan changed successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error changing subscription plan: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Failed to change plan'], 500);
        }
    }

    private function calculatePlanChangeAmount(Subscription $subscription, \App\Models\Plan $newPlan, string $billingCycle): float
    {
        $currentPlan = $subscription->plan;
        if (!$currentPlan) {
            return 0.0;
        }

        $currentPrice = (float) ($billingCycle === 'annual' ? $currentPlan->price_annual : $currentPlan->price_monthly);
        $newPrice = (float) ($billingCycle === 'annual' ? $newPlan->price_annual : $newPlan->price_monthly);
        $priceDifference = max(0, $newPrice - $currentPrice);
        $setupFeeDifference = max(0, (float) ($newPlan->setup_fee ?? 0) - (float) ($currentPlan->setup_fee ?? 0));

        $remainingRatio = 1.0;
        if ($subscription->ends_at && $subscription->starts_at && $subscription->ends_at->isFuture()) {
            $totalSeconds = max(1, $subscription->ends_at->diffInSeconds($subscription->starts_at));
            $remainingSeconds = max(0, $subscription->ends_at->diffInSeconds(now()));
            $remainingRatio = min(1, $remainingSeconds / $totalSeconds);
        }

        return round(($priceDifference * $remainingRatio) + $setupFeeDifference, 2);
    }

    private function applyPlanChange(Subscription $subscription, \App\Models\Plan $newPlan, string $billingCycle): void
    {
        $subscription->plan_id = $newPlan->id;
        $subscription->billing_cycle = $billingCycle;
        $subscription->updated_at = now();
        $subscription->save();
    }

    /**
     * Get failed or pending payment for subscription
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFailedPayment($id)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'User not authenticated'], 401);
            }

            $subscription = Subscription::where('id', $id)->where('user_id', $user->id)->first();
            if (!$subscription) {
                return response()->json(['status' => 'error', 'message' => 'Subscription not found'], 404);
            }

            $failedPayment = Payment::where('subscription_id', $id)
                ->whereIn('status', ['failed', 'pending'])
                ->orderBy('created_at', 'desc')
                ->first();

            return response()->json([
                'status' => 'success',
                'data' => $failedPayment
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching failed payment: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Failed to fetch failed payment'], 500);
        }
    }

    /**
     * Renew subscription
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function renew($id)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'User not authenticated'], 401);
            }

            $subscription = Subscription::where('id', $id)->where('user_id', $user->id)->first();
            if (!$subscription) {
                return response()->json(['status' => 'error', 'message' => 'Subscription not found'], 404);
            }

            // Reactivate subscription
            $subscription->status = 'active';
            $subscription->cancel_at_period_end = false;

            // Extend billing period if expired
            if ($subscription->current_period_end < now()) {
                $billingCycle = $subscription->billing_cycle ?? 'monthly';
                if ($billingCycle === 'annual') {
                    $subscription->current_period_start = now();
                    $subscription->current_period_end = now()->addYear();
                    $subscription->next_billing_date = now()->addYear();
                } else {
                    $subscription->current_period_start = now();
                    $subscription->current_period_end = now()->addMonth();
                    $subscription->next_billing_date = now()->addMonth();
                }
            }

            $subscription->updated_at = now();
            $subscription->save();

            $subscription->load(['plan', 'payments']);

            return response()->json([
                'status' => 'success',
                'data' => $subscription,
                'message' => 'Subscription renewed successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error renewing subscription: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Failed to renew subscription'], 500);
        }
    }

    /**
     * Cancel subscription with options
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function cancelSubscription(Request $request, $id)
    {
        try {
            $user = Auth::user();
            if (!$user) {
                return response()->json(['status' => 'error', 'message' => 'User not authenticated'], 401);
            }

            $validator = Validator::make($request->all(), [
                'cancel_at_period_end' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json(['status' => 'error', 'errors' => $validator->errors()], 422);
            }

            $subscription = Subscription::where('id', $id)->where('user_id', $user->id)->first();
            if (!$subscription) {
                return response()->json(['status' => 'error', 'message' => 'Subscription not found'], 404);
            }

            $cancelAtPeriodEnd = $request->get('cancel_at_period_end', true);

            if ($cancelAtPeriodEnd) {
                $subscription->cancel_at_period_end = true;
                $subscription->status = 'active'; // Keep active until period end
            } else {
                $subscription->status = 'cancelled';
                $subscription->cancel_at_period_end = false;
                $subscription->current_period_end = now();
            }

            $subscription->updated_at = now();
            $subscription->save();

            $subscription->load(['plan', 'payments']);

            return response()->json([
                'status' => 'success',
                'data' => $subscription,
                'message' => $cancelAtPeriodEnd ? 'Subscription will cancel at period end' : 'Subscription cancelled immediately'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error cancelling subscription: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => 'Failed to cancel subscription'], 500);
        }
    }

    /**
     * Get all subscriptions (admin endpoint)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $query = Subscription::with(['user', 'plan']);

            // Apply filters
            if ($request->has('status') && $request->status !== '') {
                $query->where('status', $request->status);
            }

            if ($request->has('search') && $request->search !== '') {
                $search = $request->search;
                $query->whereHas('user', function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $subscriptions = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'status' => 'success',
                'data' => $subscriptions
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching subscriptions: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch subscriptions'
            ], 500);
        }
    }

    /**
     * Create new subscription (admin endpoint)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'customer_id' => 'required|integer|exists:users,id',
                'plan_id' => 'required|integer|exists:plans,id',
                'billing_cycle' => 'required|in:monthly,annual',
                'status' => 'required|in:active,trial,pending,suspended,canceled',
                'trial_days' => 'nullable|integer|min:0',
                'start_date' => 'nullable|date',
                'notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $plan = \App\Models\Plan::findOrFail($request->plan_id);
            $startDate = $request->start_date ? new \DateTime($request->start_date) : now();

            // Calculate billing dates
            $nextBillingDate = clone $startDate;
            if ($request->billing_cycle === 'annual') {
                $nextBillingDate->addYear();
            } else {
                $nextBillingDate->addMonth();
            }

            // Add trial period if specified
            if ($request->trial_days && $request->trial_days > 0) {
                $nextBillingDate->addDays($request->trial_days);
            }

            $subscription = Subscription::create([
                'user_id' => $request->customer_id,
                'plan_id' => $request->plan_id,
                'status' => $request->status,
                'billing_cycle' => $request->billing_cycle,
                'start_date' => $startDate,
                'next_billing_date' => $nextBillingDate,
                'trial_days' => $request->trial_days,
                'notes' => $request->notes,
                'current_period_start' => $startDate,
                'current_period_end' => $nextBillingDate,
            ]);

            $subscription->load(['user', 'plan']);

            return response()->json([
                'status' => 'success',
                'data' => $subscription,
                'message' => 'Subscription created successfully'
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating subscription: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create subscription'
            ], 500);
        }
    }

    /**
     * Show subscription details (admin endpoint)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function adminShow($id)
    {
        try {
            $subscription = Subscription::with(['user', 'plan', 'payments'])
                ->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $subscription
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching subscription: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Subscription not found'
            ], 404);
        }
    }

    /**
     * Update subscription (admin endpoint)
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function adminUpdate(Request $request, $id)
    {
        try {
            $subscription = Subscription::findOrFail($id);

            $validator = Validator::make($request->all(), [
                'plan_id' => 'sometimes|integer|exists:plans,id',
                'billing_cycle' => 'sometimes|in:monthly,annual',
                'status' => 'sometimes|in:active,trial,pending,suspended,canceled',
                'trial_days' => 'nullable|integer|min:0',
                'notes' => 'nullable|string|max:1000'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $subscription->update($request->only([
                'plan_id',
                'billing_cycle',
                'status',
                'trial_days',
                'notes'
            ]));

            $subscription->load(['user', 'plan']);

            return response()->json([
                'status' => 'success',
                'data' => $subscription,
                'message' => 'Subscription updated successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error updating subscription: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update subscription'
            ], 500);
        }
    }

    /**
     * Delete subscription (admin endpoint)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $subscription = Subscription::findOrFail($id);
            $subscription->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Subscription deleted successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error deleting subscription: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete subscription'
            ], 500);
        }
    }

    /**
     * Suspend subscription (admin endpoint)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function suspend($id)
    {
        try {
            $subscription = Subscription::findOrFail($id);
            $subscription->update(['status' => 'suspended']);
            $subscription->load(['user', 'plan']);

            return response()->json([
                'status' => 'success',
                'data' => $subscription,
                'message' => 'Subscription suspended successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error suspending subscription: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to suspend subscription'
            ], 500);
        }
    }

    /**
     * Reactivate subscription (admin endpoint)
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function reactivate($id)
    {
        try {
            $subscription = Subscription::findOrFail($id);
            $subscription->update(['status' => 'active']);
            $subscription->load(['user', 'plan']);

            return response()->json([
                'status' => 'success',
                'data' => $subscription,
                'message' => 'Subscription reactivated successfully'
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error reactivating subscription: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to reactivate subscription'
            ], 500);
        }
    }
}
