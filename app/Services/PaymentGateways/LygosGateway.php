<?php

namespace App\Services\PaymentGateways;

use App\Models\Transaction;
use App\Models\PaymentGatewaySetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class LygosGateway implements PaymentGatewayInterface
{
    /**
     * Lygos API key
     *
     * @var string
     */
    protected $apiKey;
    
    /**
     * Lygos API URL
     *
     * @var string
     */
    protected $apiUrl;
    
    /**
     * Lygos merchant ID
     *
     * @var string
     */
    protected $merchantId;
    
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
        
        // Extract API credentials from settings
        $this->apiKey = $settings->getSetting('api_key');
        $this->merchantId = $settings->getSetting('merchant_id');
        $this->apiUrl = $settings->getSetting('api_url', 'https://api.lygos.com/v1');
    }
    
    /**
     * Create a payment session/intent
     * 
     * This method creates a Lygos payment session and returns the payment URL
     * for redirecting the customer to Lygos's payment page
     *
     * @param Transaction $transaction
     * @param array $paymentData
     * @return array
     */
    public function createPayment(Transaction $transaction, array $paymentData = []): array
    {
        try {
            // Create return and cancel URLs
            $returnUrl = URL::to('/api/payments/lygos/return?transaction_id=' . $transaction->id);
            $cancelUrl = URL::to('/api/payments/lygos/cancel?transaction_id=' . $transaction->id);
            
            // Create Lygos payment session
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ])->post($this->apiUrl . '/payment-sessions', [
                'merchant_id' => $this->merchantId,
                'amount' => $transaction->total_amount,
                'currency' => strtoupper($transaction->currency),
                'description' => $transaction->description,
                'reference_id' => (string) $transaction->id,
                'customer' => [
                    'email' => $transaction->customer_email,
                    'name' => $transaction->customer_name,
                ],
                'success_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
                'metadata' => [
                    'transaction_id' => $transaction->id,
                    'order_id' => $transaction->order_id,
                ],
                'webhook_url' => URL::to('/api/payments/lygos/webhook'),
            ]);
            
            if ($response->successful()) {
                $paymentSession = $response->json();
                
                // Update transaction with Lygos session ID
                $transaction->gateway_transaction_id = $paymentSession['id'];
                $transaction->save();
                
                // Return the payment URL for redirect
                return [
                    'success' => true,
                    'type' => 'payment_url',
                    'value' => $paymentSession['payment_url'],
                    'session_id' => $paymentSession['id']
                ];
            } else {
                Log::error('Lygos payment session creation failed: ' . $response->body());
                return [
                    'success' => false,
                    'message' => 'Failed to create Lygos payment session: ' . ($response->json()['message'] ?? 'Unknown error')
                ];
            }
        } catch (\Exception $e) {
            Log::error('Lygos payment creation failed: ' . $e->getMessage());
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
        try {
            // In a real implementation, we would make an actual API call to Lygos
            // For this demo, we'll simulate the API call
            
            // Prepare the request data
            $requestData = [
                'amount' => (int)$transaction->total_amount,
                'shop_name' => 'CSL Certification Platform',
                'message' => $transaction->description ?? 'Payment for certification services',
                'success_url' => $paymentData['success_url'] ?? route('api.transactions.callback.success'),
                'failure_url' => $paymentData['failure_url'] ?? route('api.transactions.callback.failure'),
                'order_id' => $transaction->transaction_id
            ];
            
            // Simulate API call delay
            usleep(500000); // 0.5 seconds
            
            // Generate a Lygos-like gateway ID
            $gatewayId = Str::uuid()->toString();
            
            // Simulate the response from Lygos API
            $responseData = [
                'id' => $gatewayId,
                'amount' => $requestData['amount'],
                'currency' => $transaction->currency,
                'shop_name' => $requestData['shop_name'],
                'message' => $requestData['message'],
                'user_id' => Str::uuid()->toString(),
                'creation_date' => now()->toIso8601ZuluString(),
                'link' => "https://checkout.lygosapp.com/pay/{$gatewayId}",
                'order_id' => $requestData['order_id'],
                'success_url' => $requestData['success_url'],
                'failure_url' => $requestData['failure_url']
            ];
            
            return [
                'success' => true,
                'message' => 'Payment gateway created successfully',
                'transaction_id' => $gatewayId,
                'checkout_url' => $responseData['link'],
                'amount' => $transaction->total_amount,
                'currency' => $transaction->currency,
                'payment_method' => 'lygos',
                'created' => strtotime($responseData['creation_date']),
                'response' => $responseData
            ];
        } catch (\Exception $e) {
            Log::error('Lygos payment error: ' . $e->getMessage());
            
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
            // In a real implementation, we would make an API call to Lygos to verify the payment
            // For this demo, we'll simulate a successful verification
            
            // Simulate API call delay
            usleep(300000); // 0.3 seconds
            
            // Simulate the response from Lygos API
            $responseData = [
                'id' => $transactionId,
                'status' => 'completed',
                'payment_method' => 'mobile_money',
                'payment_provider' => 'orange_money',
                'payment_date' => now()->toIso8601ZuluString(),
                'amount' => 1000,
                'currency' => 'XOF',
                'fees' => 25,
                'net_amount' => 975
            ];
            
            return [
                'success' => true,
                'message' => 'Payment verified',
                'transaction_id' => $transactionId,
                'status' => 'completed',
                'verified' => true,
                'payment_method' => $responseData['payment_method'],
                'payment_provider' => $responseData['payment_provider'],
                'payment_date' => $responseData['payment_date'],
                'response' => $responseData
            ];
        } catch (\Exception $e) {
            Log::error('Lygos verification error: ' . $e->getMessage());
            
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
            // In a real implementation, we would make an API call to Lygos to process the refund
            // For this demo, we'll simulate a successful refund
            
            // Simulate API call delay
            usleep(500000); // 0.5 seconds
            
            // If amount is not specified, refund the full amount
            if ($amount === null) {
                $amount = $transaction->total_amount;
            }
            
            // Generate a refund ID
            $refundId = 'REF-' . Str::random(10);
            
            // Simulate the response from Lygos API
            $responseData = [
                'id' => $refundId,
                'gateway_id' => $transaction->gateway_transaction_id,
                'amount' => $amount,
                'currency' => $transaction->currency,
                'reason' => $reason,
                'status' => 'completed',
                'creation_date' => now()->toIso8601ZuluString(),
                'completion_date' => now()->toIso8601ZuluString()
            ];
            
            return [
                'success' => true,
                'message' => 'Refund processed successfully',
                'refund_id' => $refundId,
                'transaction_id' => $transaction->gateway_transaction_id,
                'amount' => $amount,
                'currency' => $transaction->currency,
                'reason' => $reason,
                'created' => strtotime($responseData['creation_date']),
                'status' => 'completed',
                'response' => $responseData
            ];
        } catch (\Exception $e) {
            Log::error('Lygos refund error: ' . $e->getMessage());
            
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
            'name' => 'Lygos',
            'code' => 'lygos',
            'description' => 'Pay with Lygos',
            'is_enabled' => true,
            'mode' => $this->settings->mode,
            'supports' => [
                'mobile_money' => true,
                'bank_transfer' => true,
                'card' => true
            ],
            'currencies' => ['XOF', 'XAF', 'GHS', 'NGN', 'USD', 'EUR'],
            'client_side' => false,
            'redirect_based' => true,
            'webhook_url' => $this->settings->webhook_url,
            'api_key' => $this->settings->getSetting('api_key'),
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
            // Based on Lygos API documentation, they use a HMAC-SHA256 signature
            // for webhook verification
            
            // In a real implementation, we would:
            // 1. Get the raw payload
            // 2. Compute the HMAC using the secret key
            // 3. Compare with the provided signature
            
            if (empty($signature) || empty($secret)) {
                return false;
            }
            
            // Convert payload to string if it's not already
            $payloadString = is_string($payload) ? $payload : json_encode($payload);
            
            // Calculate expected signature
            $expectedSignature = hash_hmac('sha256', $payloadString, $secret);
            
            // Verify signature (constant time comparison to prevent timing attacks)
            return hash_equals($expectedSignature, $signature);
        } catch (\Exception $e) {
            // Log the error
            Log::error('Lygos webhook verification failed: ' . $e->getMessage());
            return false;
        }
    }
}
