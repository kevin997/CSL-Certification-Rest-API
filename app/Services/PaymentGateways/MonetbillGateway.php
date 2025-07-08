<?php

namespace App\Services\PaymentGateways;

use App\Models\Transaction;
use App\Models\PaymentGatewaySetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class MonetbillGateway implements PaymentGatewayInterface
{
    /**
     * Monetbill API service key
     *
     * @var string
     */
    protected $serviceKey;
    
    /**
     * Monetbill API service secret
     *
     * @var string
     */
    protected $serviceSecret;
    
    /**
     * Monetbill API base URL
     *
     * @var string
     */
    protected $apiBaseUrl = 'https://api.monetbil.com';
    
    /**
     * Monetbill widget version
     *
     * @var string
     */
    protected $widgetVersion = 'v2.1';
    
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
        $this->serviceKey = $settings->getSetting('service_key');
        $this->serviceSecret = $settings->getSetting('service_secret');
        $this->widgetVersion = $settings->getSetting('widget_version', 'v2.1');
        
        // Check for test mode and use appropriate keys
        $isTestMode = $settings->getSetting('test_mode', false);
        if ($isTestMode) {
            // Try to get test keys if in test mode
            $testServiceKey = $settings->getSetting('test_service_key');
            $testServiceSecret = $settings->getSetting('test_service_secret');
            
            if (!empty($testServiceKey) && !empty($testServiceSecret)) {
                $this->serviceKey = $testServiceKey;
                $this->serviceSecret = $testServiceSecret;
                Log::info('[MonetbillGateway] Using test API credentials');
            }
        }
        
        // Enhanced logging for Monetbill initialization
        Log::info('[MonetbillGateway] Initializing Monetbill client', [
            'gateway_id' => $settings->id,
            'gateway_code' => $settings->code,
            'environment_id' => $settings->environment_id,
            'service_key_present' => !empty($this->serviceKey),
            'service_secret_present' => !empty($this->serviceSecret),
            'widget_version' => $this->widgetVersion,
            'test_mode' => $isTestMode
        ]);
        
        // Check if API credentials are available before initializing
        if (empty($this->serviceKey) || empty($this->serviceSecret)) {
            Log::error('[MonetbillGateway] Missing API credentials', [
                'gateway_id' => $settings->id,
                'gateway_code' => $settings->code,
                'environment_id' => $settings->environment_id
            ]);
            throw new \Exception('Monetbill API credentials are missing. Please check your payment gateway settings.');
        }
    }
    
    /**
     * Create a payment session/intent
     * 
     * This method creates a Monetbill payment URL and returns it for redirection
     *
     * @param Transaction $transaction
     * @param array $paymentData
     * @return array
     */
    public function createPayment(Transaction $transaction, array $paymentData = []): array
    {
        try {

            $environmentId = session("current_environment_id");

            // Get environment details
            $environment = null;
            if ($environmentId) {
                $environment = \App\Models\Environment::find($environmentId);
            }
            
            // Log before attempting to create payment
            Log::info('[MonetbillGateway] Attempting to create payment', [
                'transaction_id' => $transaction->id,
                'order_id' => $transaction->order_id,
                'amount' => $transaction->total_amount,
                'currency' => $transaction->currency,
                'service_key_present' => !empty($this->serviceKey),
                'service_secret_present' => !empty($this->serviceSecret),
                'gateway_id' => $this->settings->id ?? null,
                'gateway_code' => $this->settings->code ?? null
            ]);
            
            // Convert amount to XAF since Monetbill primarily works with XAF currency
            $amountInXAF = $transaction->convertToXAF();
            
            // If conversion failed, log error and use original amount
            if ($amountInXAF === null) {
                Log::warning('[MonetbillGateway] Currency conversion to XAF failed. Using original amount.', [
                    'transaction_id' => $transaction->transaction_id,
                    'original_currency' => $transaction->currency,
                    'original_amount' => $transaction->total_amount
                ]);
                $amountInXAF = $transaction->total_amount;
            }
            
            // Log the conversion details
            Log::info('[MonetbillGateway] Currency conversion for payment', [
                'transaction_id' => $transaction->transaction_id,
                'original_currency' => $transaction->currency,
                'original_amount' => $transaction->total_amount,
                'converted_amount_xaf' => $amountInXAF
            ]);
            
            // Create return and cancel URLs
            $successUrl = $paymentData['success_url'] ?? route('api.transactions.callback.success', ['environment_id' => $environmentId]);
            $failureUrl = $paymentData['failure_url'] ?? route('api.transactions.callback.failure', ['environment_id' => $environmentId]);
            
            // Prepare payment data for Monetbill
            $paymentData = [
                'amount' => (int)$amountInXAF,
                'currency' => 'XAF', // Monetbill primarily works with XAF
                'payment_ref' => $transaction->transaction_id,
                'item_ref' => $transaction->order_id,
                'user' => $transaction->customer_id ?? null,
                'first_name' => $transaction->customer_name ? explode(' ', $transaction->customer_name)[0] : '',
                'last_name' => $transaction->customer_name ? (strpos($transaction->customer_name, ' ') ? substr($transaction->customer_name, strpos($transaction->customer_name, ' ') + 1) : '') : '',
                'email' => $transaction->customer_email ?? '',
                'phone' => $paymentData['phone'] ?? '',
                'country' => $paymentData['country'] ?? '',
                'locale' => $paymentData['locale'] ?? 'en',
                'return_url' => $successUrl,
                'notify_url' => route('api.transactions.webhook', ['gateway' => 'monetbill', 'environment_id' => $environmentId]),
                'logo' => $this->settings->getSetting('logo_url', ''),
                'shop_name' => $environment ? $environment->name : 'CSL Certification Platform',
                'message' => $transaction->description ?? 'Payment for certification services'
            ];
            
            // Generate signature for the payment request
            $paymentData['sign'] = $this->monetbill_sign($this->serviceSecret, $paymentData);
            
            // Generate payment URL using Monetbill API
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($this->apiBaseUrl . '/widget/v2.1/'. $this->serviceKey, $paymentData);
            
            // Check if the request was successful
            if ($response->successful()) {
                $responseData = $response->json();
                
                // Check if payment URL was generated
                if (isset($responseData['payment_url'])) {
                    // Generate a unique gateway transaction ID
                    $gatewayId = $transaction->transaction_id;
                    
                    // Update transaction with gateway ID
                    $transaction->gateway_transaction_id = $gatewayId;
                    $transaction->payment_gateway_setting_id = $this->settings->id;
                    $transaction->gateway_response = json_encode($responseData);
                    $transaction->save();
                    
                    Log::info('[MonetbillGateway] Payment URL created successfully', [
                        'transaction_id' => $transaction->id,
                        'gateway_transaction_id' => $gatewayId,
                        'payment_url' => $responseData['payment_url']
                    ]);
                    
                    return [
                        'success' => true,
                        'message' => 'Payment gateway created successfully',
                        'transaction_id' => $gatewayId,
                        'checkout_url' => $responseData['payment_url'],
                        'type' => 'payment_url',
                        'value' => $responseData['payment_url'],
                        'amount' => $transaction->total_amount,
                        'currency' => $transaction->currency,
                        'payment_method' => 'monetbill',
                        'payment_type' => 'monetbill',
                        'created' => time(),
                        'response' => $responseData,
                        'gateway_config' => $this->getConfig(),
                        'status' => 'pending'
                    ];
                } else {
                    Log::error('[MonetbillGateway] Payment URL not found in response', [
                        'transaction_id' => $transaction->id,
                        'response' => $responseData
                    ]);
                    
                    return [
                        'success' => false,
                        'message' => 'Failed to generate payment URL',
                        'error_details' => $responseData['message'] ?? 'Unknown error'
                    ];
                }
            } else {
                Log::error('[MonetbillGateway] Failed to create payment', [
                    'transaction_id' => $transaction->id,
                    'status_code' => $response->status(),
                    'response' => $response->json() ?? $response->body()
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Failed to create payment with Monetbill',
                    'error_details' => $response->json()['message'] ?? 'API request failed with status ' . $response->status()
                ];
            }
        } catch (\Exception $e) {
            Log::error('[MonetbillGateway] Exception while creating payment', [
                'transaction_id' => $transaction->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while creating payment: ' . $e->getMessage()
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
        // For Monetbill, payment processing is handled via redirect to their payment page
        // This method just creates the payment URL and returns it
        return $this->createPayment($transaction, $paymentData);
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
            Log::info('[MonetbillGateway] Verifying payment', ['transaction_id' => $transactionId]);
            
            // Find the transaction in our database
            $transaction = Transaction::where('transaction_id', $transactionId)
                ->orWhere('gateway_transaction_id', $transactionId)
                ->first();
            
            if (!$transaction) {
                Log::error('[MonetbillGateway] Transaction not found for verification', ['transaction_id' => $transactionId]);
                return [
                    'success' => false,
                    'message' => 'Transaction not found'
                ];
            }
            
            // Extract payment reference from transaction
            $paymentRef = $transaction->transaction_id;
            
            // Check payment status with Monetbill API
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($this->apiBaseUrl . '/payment/v1/checkPayment', [
                'service_key' => $this->serviceKey,
                'service_secret' => $this->serviceSecret,
                'payment_ref' => $paymentRef
            ]);
            
            Log::info('[MonetbillGateway] Verifying payment', [
                'transaction_id' => $transactionId,
                'payment_ref' => $paymentRef
            ]);

            if ($response->successful()) {
                $responseData = $response->json();
                
                // Update transaction with the latest status
                $transaction->gateway_response = json_encode($responseData);
                
                // Check payment status
                if (isset($responseData['status']) && $responseData['status'] === 'success') {
                    $transaction->status = 'succeeded';
                    $transaction->save();
                    
                    Log::info('[MonetbillGateway] Payment verified successfully', [
                        'transaction_id' => $transaction->id,
                        'gateway_transaction_id' => $transaction->gateway_transaction_id,
                        'status' => 'succeeded'
                    ]);
                    
                    return [
                        'success' => true,
                        'status' => 'succeeded',
                        'transaction_id' => $transaction->transaction_id,
                        'gateway_transaction_id' => $transaction->gateway_transaction_id,
                        'amount' => $transaction->total_amount,
                        'currency' => $transaction->currency
                    ];
                } else {
                    // Map Monetbill status to our status
                    $status = 'pending';
                    if (isset($responseData['status'])) {
                        switch ($responseData['status']) {
                            case 'cancelled':
                            case 'failed':
                                $status = 'failed';
                                break;
                            case 'pending':
                                $status = 'pending';
                                break;
                        }
                    }
                    
                    $transaction->status = $status;
                    $transaction->save();
                    
                    Log::info('[MonetbillGateway] Payment verification status', [
                        'transaction_id' => $transaction->id,
                        'gateway_transaction_id' => $transaction->gateway_transaction_id,
                        'status' => $status
                    ]);
                    
                    return [
                        'success' => true,
                        'status' => $status,
                        'transaction_id' => $transaction->transaction_id,
                        'gateway_transaction_id' => $transaction->gateway_transaction_id,
                        'amount' => $transaction->total_amount,
                        'currency' => $transaction->currency
                    ];
                }
            } else {
                Log::error('[MonetbillGateway] Failed to verify payment', [
                    'transaction_id' => $transaction->id,
                    'status_code' => $response->status(),
                    'response' => $response->json() ?? $response->body()
                ]);
                
                return [
                    'success' => false,
                    'message' => 'Failed to verify payment with Monetbill',
                    'error_details' => $response->json()['message'] ?? 'API request failed with status ' . $response->status()
                ];
            }
        } catch (\Exception $e) {
            Log::error('[MonetbillGateway] Exception while verifying payment', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => 'An error occurred while verifying payment: ' . $e->getMessage()
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
        // Monetbill may not support automatic refunds via API
        // This is a placeholder implementation
        Log::warning('[MonetbillGateway] Refund requested but not supported via API', [
            'transaction_id' => $transaction->id,
            'gateway_transaction_id' => $transaction->gateway_transaction_id,
            'amount' => $amount,
            'reason' => $reason
        ]);
        
        return [
            'success' => false,
            'message' => 'Refunds are not supported automatically via Monetbill API. Please process the refund manually.'
        ];
    }
    
    /**
     * Get payment gateway configuration for the current environment
     *
     * @return array
     */
    public function getConfig(): array
    {
        return [
            'gateway' => 'monetbill',
            'display_name' => $this->settings->getSetting('display_name', 'Monetbill Mobile Money'),
            'description' => $this->settings->getSetting('description', 'Pay with Mobile Money'),
            'logo_url' => $this->settings->getSetting('logo_url', ''),
            'supported_currencies' => explode(',', $this->settings->getSetting('supported_currencies', 'XAF,XOF')),
            'widget_version' => $this->widgetVersion,
            'redirect_payment' => true
        ];
    }
    
    /**
     * Generate a signature for Monetbill API requests
     *
     * @param string $service_secret Your service secret
     * @param array $params Request parameters
     * @return string
     */
    public function monetbill_sign(string $service_secret, array $params): string
    {
        // Sort parameters alphabetically by key
        ksort($params);
        
        // Create signature by concatenating service_secret with parameters
        $signature = md5($service_secret . implode('', $params));
        
        Log::debug('[MonetbillGateway] Generated signature', [
            'params_count' => count($params),
            'signature' => $signature
        ]);
        
        return $signature;
    }
    
    /**
     * Verify a signature from Monetbill
     *
     * @param string $service_secret Your service secret
     * @param array $params Request parameters
     * @return bool
     */
    public function monetbill_check_sign(string $service_secret, array $params): bool
    {
        // Check if sign parameter exists
        if (!array_key_exists('sign', $params)) {
            Log::error('[MonetbillGateway] Missing signature in parameters');
            return false;
        }
        
        // Extract the sign parameter
        $sign = $params['sign'];
        
        // Remove sign from parameters before generating comparison signature
        unset($params['sign']);
        
        // Generate signature for comparison
        $signature = $this->monetbill_sign($service_secret, $params);
        
        // Compare signatures
        $result = ($sign === $signature);
        
        if (!$result) {
            Log::error('[MonetbillGateway] Invalid signature', [
                'received' => $sign,
                'calculated' => $signature
            ]);
        }
        
        return $result;
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
            // Check if we have the necessary data
            if (empty($payload) || empty($signature) || empty($secret)) {
                Log::error('[MonetbillGateway] Missing data for webhook verification', [
                    'payload_present' => !empty($payload),
                    'signature_present' => !empty($signature),
                    'secret_present' => !empty($secret)
                ]);
                return false;
            }
            
            // Convert payload to array if it's not already
            $params = is_array($payload) ? $payload : (array)$payload;
            
            // If signature is provided separately, add it to params
            if (!empty($signature) && !isset($params['sign'])) {
                $params['sign'] = $signature;
            }
            
            // Use monetbill_check_sign to verify the signature
            return $this->monetbill_check_sign($secret ?: $this->serviceSecret, $params);
        } catch (\Exception $e) {
            Log::error('[MonetbillGateway] Exception while verifying webhook signature', [
                'error' => $e->getMessage()
            ]);
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
        // TODO: Implement real Monetbill invoice payment link creation
        return 'https://dummy.monetbill.com/pay/' . $invoice->id;
    }
}
