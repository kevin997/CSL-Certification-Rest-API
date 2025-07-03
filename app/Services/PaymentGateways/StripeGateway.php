<?php

namespace App\Services\PaymentGateways;

use App\Models\Transaction;
use App\Models\PaymentGatewaySetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

// Using class aliases to avoid conflicts with Stripe SDK
use Stripe\StripeClient as StripeSDK;
use Stripe\Exception\ApiErrorException as StripeApiException;

class StripeGateway implements PaymentGatewayInterface
{
    /**
     * Stripe client
     *
     * @var \Stripe\StripeClient
     */
    protected $stripeClient;
    
    /**
     * Stripe API key
     *
     * @var string
     */
    protected $apiKey;
    
    /**
     * Stripe API version
     *
     * @var string
     */
    protected $apiVersion;
    
    /**
     * Stripe webhook secret
     *
     * @var string
     */
    protected $webhookSecret;
    
    /**
     * Gateway settings
     *
     * @var PaymentGatewaySetting
     */
    protected $settings;
    
    /**
     * Initialize the payment gateway with settings
     *
     * @param PaymentGatewaySetting $settings
     * @return void
     */
    public function initialize(PaymentGatewaySetting $settings): void
{
    $this->settings = $settings;
    
    // Extract API credentials from settings with detailed logging
    $this->apiKey = $settings->getSetting('api_key');
    $this->apiVersion = $settings->getSetting('api_version', '2023-10-16');
    $this->webhookSecret = $settings->getSetting('webhook_secret');
    
    // Check for test mode and use appropriate keys
    $isTestMode = $settings->getSetting('test_mode', false);
    if ($isTestMode) {
        // Try to get test keys if in test mode
        $testApiKey = $settings->getSetting('test_api_key');
        if (!empty($testApiKey)) {
            $this->apiKey = $testApiKey;
            Log::info('[StripeGateway] Using test API key');
        }
    }
    
    // Enhanced logging for Stripe initialization
    Log::info('[StripeGateway] Initializing Stripe client', [
        'gateway_id' => $settings->id,
        'gateway_code' => $settings->code,
        'environment_id' => $settings->environment_id,
        'api_key_present' => !empty($this->apiKey),
        'api_key_length' => $this->apiKey ? strlen($this->apiKey) : 0,
        'api_key_starts_with' => $this->apiKey ? substr($this->apiKey, 0, 3) : 'none',
        'api_version' => $this->apiVersion,
        'webhook_secret_present' => !empty($this->webhookSecret),
        'test_mode' => $isTestMode,
        'settings_type' => is_array($settings->settings) ? 'array' : (is_string($settings->settings) ? 'string' : gettype($settings->settings)),
    ]);
    
    // Check if API key is available before initializing
    if (empty($this->apiKey)) {
        Log::error('[StripeGateway] Missing API key', [
            'gateway_id' => $settings->id,
            'gateway_code' => $settings->code,
            'environment_id' => $settings->environment_id
        ]);
        throw new \Exception('Stripe API key is missing. Please check your payment gateway settings.');
    }
    
    // Initialize the Stripe client
    $this->stripeClient = new StripeSDK([
        'api_key' => $this->apiKey,
        'stripe_version' => $this->apiVersion
    ]);
    
    // Verify the API key works by making a simple API call
    try {
        $this->stripeClient->balance->retrieve();
        Log::info('[StripeGateway] API key verification successful');
    } catch (\Exception $e) {
        Log::error('[StripeGateway] API key verification failed', ['error' => $e->getMessage()]);
        // We don't throw here to allow the payment flow to continue and fail gracefully if needed
    }
}

    /**
     * Create a payment session/intent
     * 
     * This method creates a Stripe PaymentIntent and returns the client_secret
     * for use with Stripe Elements on the frontend
     *
     * @param Transaction $transaction
     * @param array $paymentData
     * @return array
     */
    public function createPayment(Transaction $transaction, array $paymentData = []): array
    {
        try {
            // Log before attempting to create payment intent
            Log::info('[StripeGateway] Attempting to create payment intent', [
                'transaction_id' => $transaction->id,
                'order_id' => $transaction->order_id,
                'amount' => $transaction->total_amount,
                'currency' => $transaction->currency,
                'api_key_present' => !empty($this->apiKey),
                'api_key_starts_with' => $this->apiKey ? substr($this->apiKey, 0, 3) : 'none',
                'stripe_client_initialized' => isset($this->stripeClient),
                'gateway_id' => $this->settings->id ?? null,
                'gateway_code' => $this->settings->code ?? null
            ]);
            
            // Check if Stripe client is initialized
            if (!isset($this->stripeClient)) {
                Log::error('[StripeGateway] Stripe client not initialized');
                return [
                    'success' => false,
                    'message' => 'Stripe client not initialized. Please check payment gateway settings.'
                ];
            }
            
            // Format amount properly (ensure it's an integer in cents)
            $amountInCents = (int) round($transaction->total_amount * 100);
            
            // Prepare payment intent parameters
            $paymentIntentParams = [
                'amount' => $amountInCents,
                'currency' => strtolower($transaction->currency),
                'payment_method_types' => ['card'],
                'metadata' => [
                    'transaction_id' => $transaction->id,
                    'order_id' => $transaction->order_id,
                    'environment_id' => $transaction->environment_id ?? null
                ],
                'description' => $transaction->description ?? "Payment for order #{$transaction->order_id}",
            ];
            
            // Only include customer email in metadata and receipt_email if it's valid
            if (!empty($transaction->customer_email) && filter_var($transaction->customer_email, FILTER_VALIDATE_EMAIL)) {
                $paymentIntentParams['metadata']['customer_email'] = $transaction->customer_email;
                $paymentIntentParams['receipt_email'] = $transaction->customer_email;
                
                Log::info('[StripeGateway] Including customer email', [
                    'email' => $transaction->customer_email
                ]);
            } else {
                Log::info('[StripeGateway] No valid customer email found', [
                    'raw_email' => $transaction->customer_email ?? 'null'
                ]);
            }
            
            // Create a PaymentIntent with proper error handling
            $paymentIntent = $this->stripeClient->paymentIntents->create($paymentIntentParams);
            
            // Update transaction with payment intent ID
            $transaction->gateway_transaction_id = $paymentIntent->id;
            $transaction->payment_gateway_setting_id = $this->settings->id;
            $transaction->gateway_response = json_encode($paymentIntent);
            $transaction->save();
            
            Log::info('[StripeGateway] Payment intent created successfully', [
                'payment_intent_id' => $paymentIntent->id,
                'transaction_id' => $transaction->id,
                'order_id' => $transaction->order_id
            ]);
            
            // Return the client_secret for Stripe Elements
            return [
                'success' => true,
                'type' => 'client_secret',
                'value' => $paymentIntent->client_secret,
                'client_secret' => $paymentIntent->client_secret,
                'payment_intent_id' => $paymentIntent->id,
                'publishable_key' => $this->settings->getSetting('publishable_key')
            ];
        } catch (\Exception $e) {
            Log::error('[StripeGateway] Error creating payment intent', [
                'error' => $e->getMessage(),
                'error_type' => get_class($e),
                'transaction_id' => $transaction->id,
                'api_key_present' => !empty($this->apiKey),
                'stripe_client_initialized' => isset($this->stripeClient),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'Failed to create payment: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Process a payment
     *
     * @param Transaction $transaction
     * @param array $paymentData
     * @return array
     */
    public function processPayment(Transaction $transaction, array $paymentData = []): array
    {
        // Validate payment data
        if (!isset($paymentData['payment_method_id']) && !isset($paymentData['payment_intent_id'])) {
            return [
                'success' => false,
                'message' => 'Missing payment method ID or payment intent ID'
            ];
        }
        
        try {
            // In a real implementation, we would use the Stripe SDK to process the payment
            // For this demo, we'll simulate a successful payment
            
            // Simulate API call delay
            usleep(500000); // 0.5 seconds
            
            // Generate a Stripe-like transaction ID
            $stripeTransactionId = 'ch_' . Str::random(24);
            
            // Prepare response data
            $responseData = [
                'id' => $stripeTransactionId,
                'object' => 'charge',
                'amount' => (int)($transaction->total_amount * 100), // Convert to cents
                'amount_captured' => (int)($transaction->total_amount * 100),
                'amount_refunded' => 0,
                'application' => null,
                'application_fee' => null,
                'application_fee_amount' => null,
                'balance_transaction' => 'txn_' . Str::random(24),
                'billing_details' => [
                    'address' => [
                        'city' => null,
                        'country' => null,
                        'line1' => null,
                        'line2' => null,
                        'postal_code' => null,
                        'state' => null
                    ],
                    'email' => $transaction->customer_email,
                    'name' => $transaction->customer_name,
                    'phone' => null
                ],
                'calculated_statement_descriptor' => 'CSL CERTIFICATION',
                'captured' => true,
                'created' => time(),
                'currency' => strtolower($transaction->currency),
                'customer' => $transaction->customer_id ?? null,
                'description' => $transaction->description,
                'destination' => null,
                'dispute' => null,
                'disputed' => false,
                'failure_code' => null,
                'failure_message' => null,
                'fraud_details' => [],
                'livemode' => $this->settings->mode === 'live',
                'metadata' => [
                    'order_id' => $transaction->order_id,
                    'transaction_id' => $transaction->transaction_id
                ],
                'outcome' => [
                    'network_status' => 'approved_by_network',
                    'reason' => null,
                    'risk_level' => 'normal',
                    'risk_score' => 15,
                    'seller_message' => 'Payment complete.',
                    'type' => 'authorized'
                ],
                'paid' => true,
                'payment_intent' => 'pi_' . Str::random(24),
                'payment_method' => $paymentData['payment_method_id'] ?? 'pm_' . Str::random(24),
                'payment_method_details' => [
                    'card' => [
                        'brand' => $paymentData['card_brand'] ?? 'visa',
                        'checks' => [
                            'address_line1_check' => null,
                            'address_postal_code_check' => null,
                            'cvc_check' => 'pass'
                        ],
                        'country' => 'US',
                        'exp_month' => $paymentData['card_exp_month'] ?? 12,
                        'exp_year' => $paymentData['card_exp_year'] ?? 2025,
                        'fingerprint' => 'fingerprint_' . Str::random(16),
                        'funding' => 'credit',
                        'installments' => null,
                        'last4' => $paymentData['card_last4'] ?? '4242',
                        'network' => 'visa',
                        'three_d_secure' => null,
                        'wallet' => null
                    ],
                    'type' => 'card'
                ],
                'receipt_email' => $transaction->customer_email,
                'receipt_number' => null,
                'receipt_url' => 'https://pay.stripe.com/receipts/' . Str::random(24),
                'refunded' => false,
                'refunds' => [
                    'object' => 'list',
                    'data' => [],
                    'has_more' => false,
                    'total_count' => 0,
                    'url' => '/v1/charges/' . $stripeTransactionId . '/refunds'
                ],
                'review' => null,
                'shipping' => null,
                'source_transfer' => null,
                'statement_descriptor' => 'CSL CERTIFICATION',
                'statement_descriptor_suffix' => null,
                'status' => 'succeeded',
                'transfer_data' => null,
                'transfer_group' => null
            ];
            
            return [
                'success' => true,
                'message' => 'Payment processed successfully',
                'transaction_id' => $stripeTransactionId,
                'status' => 'succeeded',
                'amount' => $transaction->total_amount,
                'currency' => $transaction->currency,
                'payment_method' => 'card',
                'payment_method_details' => $responseData['payment_method_details'],
                'created' => $responseData['created'],
                'response' => $responseData
            ];
        } catch (\Exception $e) {
            Log::error('Stripe payment error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Payment processing failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'error_code' => $e->getCode()
            ];
        }
    }
    
    /**
     * Verify a payment
     *
     * @param string $transactionId
     * @return array
     */
    public function verifyPayment(string $transactionId): array
    {
        try {
            // In a real implementation, we would use the Stripe SDK to verify the payment
            // For this demo, we'll simulate a successful verification
            
            // Simulate API call delay
            usleep(300000); // 0.3 seconds
            
            return [
                'success' => true,
                'message' => 'Payment verified',
                'transaction_id' => $transactionId,
                'status' => 'succeeded',
                'verified' => true
            ];
        } catch (\Exception $e) {
            Log::error('Stripe verification error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Payment verification failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'error_code' => $e->getCode()
            ];
        }
    }
    
    /**
     * Process a refund
     *
     * @param Transaction $transaction
     * @param float|null $amount
     * @param string $reason
     * @return array
     */
    public function processRefund(Transaction $transaction, ?float $amount = null, string $reason = ''): array
    {
        // If no gateway transaction ID, we can't process a refund
        if (!$transaction->gateway_transaction_id) {
            return [
                'success' => false,
                'message' => 'No gateway transaction ID found'
            ];
        }
        
        try {
            // In a real implementation, we would use the Stripe SDK to process the refund
            // For this demo, we'll simulate a successful refund
            
            // Simulate API call delay
            usleep(500000); // 0.5 seconds
            
            // If amount is not specified, refund the full amount
            if ($amount === null) {
                $amount = $transaction->total_amount;
            }
            
            // Generate a Stripe-like refund ID
            $refundId = 're_' . Str::random(24);
            
            // Prepare response data
            $responseData = [
                'id' => $refundId,
                'object' => 'refund',
                'amount' => (int)($amount * 100), // Convert to cents
                'balance_transaction' => 'txn_' . Str::random(24),
                'charge' => $transaction->gateway_transaction_id,
                'created' => time(),
                'currency' => strtolower($transaction->currency),
                'metadata' => [
                    'reason' => $reason,
                    'transaction_id' => $transaction->transaction_id
                ],
                'payment_intent' => 'pi_' . Str::random(24),
                'reason' => $reason,
                'receipt_number' => null,
                'source_transfer_reversal' => null,
                'status' => 'succeeded',
                'transfer_reversal' => null
            ];
            
            return [
                'success' => true,
                'message' => 'Refund processed successfully',
                'refund_id' => $refundId,
                'transaction_id' => $transaction->gateway_transaction_id,
                'amount' => $amount,
                'currency' => $transaction->currency,
                'reason' => $reason,
                'created' => $responseData['created'],
                'status' => 'succeeded',
                'response' => $responseData
            ];
        } catch (\Exception $e) {
            Log::error('Stripe refund error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Refund processing failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'error_code' => $e->getCode()
            ];
        }
    }
    
    /**
     * Get payment gateway configuration
     *
     * @return array
     */
    public function getConfig(): array
    {
        return [
            'name' => 'Stripe',
            'code' => 'stripe',
            'description' => 'Accept payments with Stripe',
            'is_enabled' => true,
            'mode' => $this->settings->mode,
            'supports' => [
                'credit_card' => true,
                'apple_pay' => true,
                'google_pay' => true,
                'sepa' => true,
                'sofort' => true,
                'ideal' => true,
                'bancontact' => true,
                'giropay' => true,
                'p24' => true,
                'eps' => true,
                'multibanco' => true,
                'wechat' => true,
                'alipay' => true
            ],
            'client_side' => true,
            'requires_3ds' => true,
            'webhook_url' => $this->settings->webhook_url,
            'publishable_key' => $this->settings->getSetting('publishable_key'),
            'test_mode' => $this->settings->mode === 'sandbox'
        ];
    }
    
    /**
     * Verify webhook signature
     *
     * @param mixed $payload
     * @param string $signature
     * @param string $secret
     * @return bool
     */
    public function verifyWebhookSignature($payload, string $signature, string $secret): bool
    {
        try {
            // Use Stripe's SDK to verify the signature
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                $secret ?: $this->webhookSecret
            );
            
            return true;
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            Log::error('Stripe webhook error: Invalid payload: ' . $e->getMessage());
            return false;
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            Log::error('Stripe webhook error: Invalid signature: ' . $e->getMessage());
            return false;
        } catch (\Exception $e) {
            // Other error
            Log::error('Stripe webhook error: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Create a payment link for an invoice (stub implementation)
     *
     * @param \App\Models\Invoice $invoice
     * @return string
     */
    public function createInvoicePaymentLink(\App\Models\Invoice $invoice)
    {
        // TODO: Implement real Stripe invoice payment link creation
        return 'https://dummy.stripe.com/pay/' . $invoice->id;
    }
}
