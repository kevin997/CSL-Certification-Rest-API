<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\Payment;
use App\Models\Transaction;
use App\Models\Environment;
use App\Models\Plan;
use App\Models\User;
use App\Models\PaymentGatewaySetting;
use App\Services\PaymentGateways\PaymentGatewayFactory;
use App\Services\PaymentGateways\PaymentGatewayInterface;
use App\Services\Tax\TaxZoneService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubscriptionManager
{
    /**
     * @var PaymentGatewayFactory
     */
    protected $gatewayFactory;
    
    /**
     * @var TaxZoneService
     */
    protected $taxZoneService;
    
    /**
     * @var PaymentGatewayInterface
     */
    protected $currentGateway;

    /**
     * Constructor for SubscriptionManager
     */
    public function __construct(
        PaymentGatewayFactory $gatewayFactory,
        TaxZoneService $taxZoneService
    ) {
        $this->gatewayFactory = $gatewayFactory;
        $this->taxZoneService = $taxZoneService;
    }

    /**
     * Create a subscription with payment processing
     *
     * @param array $subscriptionData
     * @param array $paymentData
     * @return array
     */
    public function createSubscriptionWithPayment(array $subscriptionData, array $paymentData): array
    {
        try {
            return DB::transaction(function () use ($subscriptionData, $paymentData) {
                // Create the subscription
                $subscription = Subscription::create($subscriptionData);
                
                // Get the plan for pricing information
                $plan = Plan::find($subscription->plan_id);
                if (!$plan) {
                    throw new \Exception('Plan not found');
                }
                
                // Get environment for tax calculation
                $environment = Environment::find(1);
                if (!$environment) {
                    throw new \Exception('Environment not found');
                }
                
                // Calculate tax
                $taxInfo = $this->taxZoneService->calculateTaxByEnvironment(
                    $plan->setup_fee ?? 0,
                    $environment->id
                );
                
                // Create payment record
                $payment = Payment::create([
                    'user_id' => $subscriptionData['user_id'],
                    'subscription_id' => $subscription->id,
                    'amount' => $plan->setup_fee ?? 0,
                    'fee_amount' => 0, // No commission for setup fees
                    'tax_amount' => $taxInfo['tax_amount'],
                    'tax_rate' => $taxInfo['tax_rate'],
                    'tax_zone' => $taxInfo['zone_name'],
                    'total_amount' => ($plan->setup_fee ?? 0) + $taxInfo['tax_amount'],
                    'currency' => $paymentData['currency'] ?? 'USD',
                    'payment_method' => $paymentData['payment_method'],
                    'status' => Payment::STATUS_PENDING,
                    'description' => 'Setup fee for ' . $plan->name . ' plan',
                ]);
                
                // Process payment based on payment method
                $paymentResult = $this->processPayment($payment, $paymentData, $environment);
                
                return [
                    'success' => $paymentResult['success'],
                    'message' => $paymentResult['message'],
                    'subscription' => $subscription,
                    'payment' => $payment,
                    'payment_data' => $paymentResult['payment_data'] ?? null,
                ];
            });
        } catch (\Exception $e) {
            Log::error('Subscription creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Subscription creation failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process payment for a subscription
     *
     * @param Payment $payment
     * @param array $paymentData
     * @param Environment $environment
     * @return array
     */
    protected function processPayment(Payment $payment, array $paymentData, Environment $environment): array
    {
        $paymentMethod = $paymentData['payment_method'];
        
        try {
            // Initialize the payment gateway
            $gatewayResult = $this->initializeGateway($paymentMethod, $environment->id);
            if (!$gatewayResult['success']) {
                return $gatewayResult;
            }
            
            $this->currentGateway = $gatewayResult['gateway'];
            
            // Handle Monetbill payments with currency conversion
            if ($paymentMethod === 'monetbill') {
                return $this->processMonetbillPayment($payment, $paymentData);
            }
            
            // Handle Stripe payments
            if ($paymentMethod === 'stripe') {
                return $this->processStripePayment($payment, $paymentData);
            }
            
            // Handle TaraMoney payments with payment links
            if ($paymentMethod === 'taramoney') {
                return $this->processTaraMoneyPayment($payment, $paymentData);
            }
            
            // Handle other payment methods
            return $this->processGenericPayment($payment, $paymentData);
            
        } catch (\Exception $e) {
            Log::error('Payment processing failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Payment processing failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Create a Transaction from a Payment for gateway processing
     *
     * @param Payment $payment
     * @param Environment $environment
     * @return Transaction
     */
    protected function createTransactionFromPayment(Payment $payment, Environment $environment): Transaction
    {
        // Get user information
        $user = $payment->user;
        
        // Check if payment already has a transaction_id and try to reuse existing transaction
        if ($payment->transaction_id) {
            $existingTransaction = Transaction::where('transaction_id', $payment->transaction_id)->first();
            
            if ($existingTransaction) {
                // Check if transaction is in pending or cancelled status - we can reuse it
                if (in_array($existingTransaction->status, [Transaction::STATUS_PENDING, Transaction::STATUS_CANCELLED])) {
                    // Reset the transaction to pending and update relevant fields
                    $existingTransaction->update([
                        'status' => Transaction::STATUS_PENDING,
                        'payment_method' => $payment->payment_method,
                        'amount' => $payment->amount,
                        'fee_amount' => $payment->fee_amount ?? 0,
                        'tax_amount' => $payment->tax_amount ?? 0,
                        'tax_rate' => $payment->tax_rate ?? 0,
                        'tax_zone' => $payment->tax_zone,
                        'total_amount' => $payment->total_amount,
                        'currency' => $payment->currency,
                        'description' => $payment->description,
                        'country_code' => $environment->country_code,
                        'state_code' => $environment->state_code,
                        'updated_at' => now()
                    ]);
                    
                    Log::info('Reusing existing transaction for payment retry', [
                        'payment_id' => $payment->id,
                        'transaction_id' => $existingTransaction->transaction_id,
                        'previous_status' => $existingTransaction->getOriginal('status')
                    ]);
                    
                    return $existingTransaction;
                } else {
                    // Transaction is in completed/failed state, create a new one
                    Log::info('Existing transaction cannot be reused, creating new one', [
                        'payment_id' => $payment->id,
                        'existing_transaction_id' => $existingTransaction->transaction_id,
                        'existing_status' => $existingTransaction->status
                    ]);
                }
            }
        }
        
        // Create a new transaction
        $transaction = Transaction::create([
            'transaction_id' => 'PXN_' . (string) Str::uuid(),
            'environment_id' => $environment->id,
            'customer_id' => $user->id,
            'customer_email' => $user->email,
            'customer_name' => $user->name,
            'amount' => $payment->amount,
            'fee_amount' => $payment->fee_amount ?? 0,
            'tax_amount' => $payment->tax_amount ?? 0,
            'tax_rate' => $payment->tax_rate ?? 0,
            'tax_zone' => $payment->tax_zone,
            'total_amount' => $payment->total_amount,
            'currency' => $payment->currency,
            'status' => Transaction::STATUS_PENDING,
            'payment_method' => $payment->payment_method,
            'description' => $payment->description,
            'country_code' => $environment->country_code,
            'state_code' => $environment->state_code,
            'created_by' => $user->id,
        ]);
        
        // Link the payment to the transaction
        $payment->update(['transaction_id' => $transaction->transaction_id]);
        
        Log::info('Created new transaction for payment', [
            'payment_id' => $payment->id,
            'transaction_id' => $transaction->transaction_id
        ]);
        
        return $transaction;
    }

    /**
     * Process Monetbill payment with currency conversion
     *
     * @param Payment $payment
     * @param array $paymentData
     * @return array
     */
    protected function processMonetbillPayment(Payment $payment, array $paymentData): array
    {
        try {
            // Convert amount to XAF for Monetbill
            $xafAmount = $payment->convertToXAF($payment->total_amount, true);
            
            if ($xafAmount === null) {
                return [
                    'success' => false,
                    'message' => 'Currency conversion failed'
                ];
            }
            
            // Get environment for transaction creation
            $environment = Environment::find($payment->subscription->environment_id);
            if (!$environment) {
                return [
                    'success' => false,
                    'message' => 'Environment not found'
                ];
            }
            
            // Create transaction from payment
            $transaction = $this->createTransactionFromPayment($payment, $environment);
            
            // Update transaction with converted amount
            $transaction->update([
                'converted_amount' => $xafAmount,
                'target_currency' => 'XAF',
                'exchange_rate' => $payment->exchange_rate,
                'source_currency' => $payment->currency,
                'original_amount' => $payment->total_amount,
                'conversion_date' => now(),
                'conversion_provider' => $payment->conversion_provider,
                'conversion_meta' => $payment->conversion_meta,
            ]);
            
            // Update payment data with converted amount
            $monetbillData = array_merge($paymentData, [
                'amount' => $xafAmount,
                'currency' => 'XAF'
            ]);
            
            // Create payment with gateway
            $response = $this->currentGateway->createPayment($transaction, $monetbillData);
            
            if ($response['success']) {
                $payment->update([
                    'gateway_transaction_id' => $response['transaction_id'] ?? null,
                    'gateway_status' => $response['status'] ?? 'pending',
                    'gateway_response' => $response
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Payment initiated successfully',
                    'payment_data' => [
                        'checkout_url' => $response['checkout_url'] ?? null,
                        'transaction_id' => $payment->transaction_id,
                        'gateway_transaction_id' => $response['transaction_id'] ?? null,
                        'amount' => $payment->total_amount,
                        'currency' => $payment->currency,
                        'converted_amount' => $xafAmount,
                        'converted_currency' => 'XAF',
                        'payment_method' => 'monetbill'
                    ]
                ];
            }
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error('Monetbill payment processing failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Monetbill payment processing failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process Stripe payment
     *
     * @param Payment $payment
     * @param array $paymentData
     * @return array
     */
    protected function processStripePayment(Payment $payment, array $paymentData): array
    {
        try {
            // Get environment for transaction creation
            $environment = Environment::find($payment->subscription->environment_id);
            if (!$environment) {
                return [
                    'success' => false,
                    'message' => 'Environment not found'
                ];
            }
            
            // Create transaction from payment
            $transaction = $this->createTransactionFromPayment($payment, $environment);
            
            // Create payment with gateway
            $response = $this->currentGateway->createPayment($transaction, $paymentData);
            
            if ($response['success']) {
                $payment->update([
                    'gateway_transaction_id' => $response['payment_intent_id'] ?? null,
                    'gateway_status' => $response['status'] ?? 'pending',
                    'gateway_response' => $response
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Payment initiated successfully',
                    'payment_data' => [
                        'client_secret' => $response['client_secret'] ?? null,
                        'publishable_key' => $response['publishable_key'] ?? null,
                        'payment_intent_id' => $response['payment_intent_id'] ?? null,
                        'transaction_id' => $payment->transaction_id,
                        'amount' => $payment->total_amount,
                        'currency' => $payment->currency,
                        'payment_method' => 'stripe'
                    ]
                ];
            }
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error('Stripe payment processing failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Stripe payment processing failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process TaraMoney payment with payment links
     *
     * @param Payment $payment
     * @param array $paymentData
     * @return array
     */
    protected function processTaraMoneyPayment(Payment $payment, array $paymentData): array
    {
        try {
            // Get environment for transaction creation
            $environment = Environment::find($payment->subscription->environment_id);
            if (!$environment) {
                return [
                    'success' => false,
                    'message' => 'Environment not found'
                ];
            }
            
            // Create transaction from payment
            $transaction = $this->createTransactionFromPayment($payment, $environment);
            
            // Create payment with TaraMoney gateway
            $response = $this->currentGateway->createPayment($transaction, $paymentData);
            
            if ($response['success']) {
                $payment->update([
                    'gateway_transaction_id' => $response['transaction_id'] ?? null,
                    'gateway_status' => $response['status'] ?? 'pending',
                    'gateway_response' => $response
                ]);
                
                Log::info('[SubscriptionManager] TaraMoney payment links created', [
                    'payment_id' => $payment->id,
                    'transaction_id' => $transaction->transaction_id,
                    'has_whatsapp' => isset($response['whatsapp_link']),
                    'has_telegram' => isset($response['telegram_link'])
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Payment links created successfully',
                    'payment_data' => [
                        'payment_links' => $response['payment_links'] ?? [],
                        'whatsapp_link' => $response['whatsapp_link'] ?? null,
                        'telegram_link' => $response['telegram_link'] ?? null,
                        'dikalo_link' => $response['dikalo_link'] ?? null,
                        'sms_link' => $response['sms_link'] ?? null,
                        'transaction_id' => $payment->transaction_id,
                        'amount' => $payment->total_amount,
                        'currency' => $payment->currency,
                        'payment_method' => 'taramoney',
                        'payment_type' => 'taramoney'
                    ]
                ];
            }
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error('TaraMoney payment processing failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'TaraMoney payment processing failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Process generic payment
     *
     * @param Payment $payment
     * @param array $paymentData
     * @return array
     */
    protected function processGenericPayment(Payment $payment, array $paymentData): array
    {
        try {
            // Get environment for transaction creation
            $environment = Environment::find($payment->subscription->environment_id);
            if (!$environment) {
                return [
                    'success' => false,
                    'message' => 'Environment not found'
                ];
            }
            
            // Create transaction from payment
            $transaction = $this->createTransactionFromPayment($payment, $environment);
            
            // Create payment with gateway
            $response = $this->currentGateway->createPayment($transaction, $paymentData);
            
            if ($response['success']) {
                $payment->update([
                    'gateway_transaction_id' => $response['transaction_id'] ?? null,
                    'gateway_status' => $response['status'] ?? 'pending',
                    'gateway_response' => $response
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Payment initiated successfully',
                    'payment_data' => [
                        'checkout_url' => $response['checkout_url'] ?? null,
                        'transaction_id' => $payment->transaction_id,
                        'gateway_transaction_id' => $response['transaction_id'] ?? null,
                        'amount' => $payment->total_amount,
                        'currency' => $payment->currency,
                        'payment_method' => $payment->payment_method
                    ]
                ];
            }
            
            return $response;
            
        } catch (\Exception $e) {
            Log::error('Generic payment processing failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Generic payment processing failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Initialize a payment gateway
     *
     * @param string $gatewayCode
     * @param int $environmentId
     * @return array
     */
    protected function initializeGateway(string $gatewayCode, int $environmentId): array
    {
        try {
            // Get gateway settings for the environment
            $gatewaySettings = PaymentGatewaySetting::where('code', $gatewayCode)
                ->where('environment_id', $environmentId)
                ->where('status', true)
                ->first();
            
            if (!$gatewaySettings) {
                return [
                    'success' => false,
                    'message' => "Payment gateway '$gatewayCode' not configured for this environment"
                ];
            }
            
            // Create and initialize the gateway
            $gateway = $this->gatewayFactory->create($gatewayCode, $gatewaySettings);
            
            if (!$gateway) {
                return [
                    'success' => false,
                    'message' => "Payment gateway '$gatewayCode' not supported"
                ];
            }
            
            return [
                'success' => true,
                'gateway' => $gateway,
                'settings' => $gatewaySettings
            ];
            
        } catch (\Exception $e) {
            Log::error('Gateway initialization failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Gateway initialization failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Update subscription status
     *
     * @param int $subscriptionId
     * @param string $status
     * @return bool
     */
    public function updateSubscriptionStatus(int $subscriptionId, string $status): bool
    {
        try {
            $subscription = Subscription::find($subscriptionId);
            if (!$subscription) {
                return false;
            }
            
            $subscription->update(['status' => $status]);
            return true;
            
        } catch (\Exception $e) {
            Log::error('Subscription status update failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Process payment success callback
     *
     * @param string $paymentId
     * @param array $callbackData
     * @return bool
     */
    public function processPaymentSuccess(string $paymentId, array $callbackData): bool
    {
        try {
            $payment = Payment::where('transaction_id', $paymentId)
                ->orWhere('gateway_transaction_id', $paymentId)
                ->first();
            
            if (!$payment) {
                Log::error('Payment not found for success callback', ['payment_id' => $paymentId]);
                return false;
            }
            
            // Update payment status
            $payment->markAsCompleted(
                $callbackData['gateway_transaction_id'] ?? null,
                $callbackData['gateway_status'] ?? 'completed',
                $callbackData
            );
            
            // Update subscription status
            if ($payment->subscription_id) {
                $this->updateSubscriptionStatus($payment->subscription_id, Subscription::STATUS_ACTIVE);
            }
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Payment success callback processing failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Process payment failure callback
     *
     * @param string $paymentId
     * @param array $callbackData
     * @return bool
     */
    public function processPaymentFailure(string $paymentId, array $callbackData): bool
    {
        try {
            $payment = Payment::where('transaction_id', $paymentId)
                ->orWhere('gateway_transaction_id', $paymentId)
                ->first();
            
            if (!$payment) {
                Log::error('Payment not found for failure callback', ['payment_id' => $paymentId]);
                return false;
            }
            
            // Update payment status
            $payment->markAsFailed(
                $callbackData['gateway_transaction_id'] ?? null,
                $callbackData['gateway_status'] ?? 'failed',
                $callbackData
            );
            
            // Update subscription status
            if ($payment->subscription_id) {
                $this->updateSubscriptionStatus($payment->subscription_id, Subscription::STATUS_CANCELED);
            }
            
            return true;
            
        } catch (\Exception $e) {
            Log::error('Payment failure callback processing failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Retry payment for an existing subscription
     *
     * @param Subscription $subscription
     * @param array $paymentData
     * @return array
     */
    public function retryPayment(Subscription $subscription, array $paymentData): array
    {
        try {
            return DB::transaction(function () use ($subscription, $paymentData) {
                // Get the plan for pricing information
                $plan = Plan::find($subscription->plan_id);
                if (!$plan) {
                    throw new \Exception('Plan not found for subscription');
                }
                
                // Get environment for payment processing
                $environment = Environment::find(1);
                if (!$environment) {
                    throw new \Exception('Environment not found for subscription');
                }
                
                // Find the most recent failed/pending payment for this subscription
                $existingPayment = Payment::where('subscription_id', $subscription->id)
                    ->whereIn('status', ['failed', 'pending', 'processing'])
                    ->orderBy('created_at', 'desc')
                    ->first();
                
                if (!$existingPayment) {
                    // No existing payment found, create a new one
                    $taxInfo = $this->taxZoneService->calculateTaxByEnvironment(
                        $plan->setup_fee ?? 0,
                        $subscription->environment_id
                    );
                    
                    $existingPayment = Payment::create([
                        'user_id' => $subscription->user_id,
                        'subscription_id' => $subscription->id,
                        'amount' => $plan->setup_fee ?? 0,
                        'fee_amount' => 0,
                        'tax_amount' => $taxInfo['tax_amount'],
                        'tax_rate' => $taxInfo['tax_rate'],
                        'tax_zone' => $taxInfo['zone_name'],
                        'total_amount' => ($plan->setup_fee ?? 0) + $taxInfo['tax_amount'],
                        'currency' => $paymentData['currency'] ?? 'USD',
                        'payment_method' => $paymentData['payment_method'],
                        'status' => Payment::STATUS_PENDING,
                        'description' => 'Setup fee retry for ' . $plan->name . ' plan',
                    ]);
                } else {
                    // Update existing payment with new payment method and reset status
                    $existingPayment->update([
                        'payment_method' => $paymentData['payment_method'],
                        'status' => Payment::STATUS_PENDING,
                        'gateway_transaction_id' => null,
                        'gateway_status' => null,
                        'gateway_response' => null,
                        'updated_at' => now()
                    ]);
                }
                
                // Process the payment using existing payment processing logic
                $paymentResult = $this->processPayment($existingPayment, $paymentData, $environment);
                
                // Update subscription status if payment was successful
                if ($paymentResult['success']) {
                    $subscription->update([
                        'status' => Subscription::STATUS_ACTIVE,
                        'updated_at' => now()
                    ]);
                }
                
                return [
                    'success' => $paymentResult['success'],
                    'message' => $paymentResult['message'],
                    'subscription' => $subscription,
                    'payment' => $existingPayment,
                    'payment_data' => $paymentResult['payment_data'] ?? null,
                ];
            });
        } catch (\Exception $e) {
            Log::error('Payment retry failed: ' . $e->getMessage(), [
                'subscription_id' => $subscription->id,
                'payment_method' => $paymentData['payment_method'] ?? 'unknown'
            ]);
            return [
                'success' => false,
                'message' => 'Payment retry failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get payment methods available for an environment
     *
     * @param int $environmentId
     * @return array
     */
    public function getPaymentMethods(int $environmentId): array
    {
        try {
            $gateways = PaymentGatewaySetting::where('environment_id', $environmentId)
                ->where('status', true)
                ->get()
                ->map(function ($setting) {
                    return [
                        'code' => $setting->code,
                        'name' => $setting->name,
                        'description' => $setting->description,
                        'supports_subscription' => $setting->supports_subscription ?? true
                    ];
                });
            
            return $gateways->toArray();
            
        } catch (\Exception $e) {
            Log::error('Failed to get payment methods: ' . $e->getMessage());
            return [];
        }
    }
}