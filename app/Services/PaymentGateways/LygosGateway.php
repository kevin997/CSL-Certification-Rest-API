<?php

namespace App\Services\PaymentGateways;

use App\Models\Transaction;
use App\Models\PaymentGatewaySetting;
use App\Models\Environment;
use App\Services\PaymentGateways\PaymentGatewayInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Log;
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
        $this->apiUrl = $settings->getSetting('api_url', 'https://api.lygosapp.com/v1/gateway');
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
            // Get environment details
            $environment = null;
            if ($transaction->environment_id) {
                $environment = Environment::find($transaction->environment_id);
            }

            // Convert amount to XAF since Lygos requires XAF currency
            $amountInXAF = $transaction->convertToXAF();
            
            // If conversion failed, log error and use original amount
            if ($amountInXAF === null) {
                Log::warning('Currency conversion to XAF failed. Using original amount.', [
                    'transaction_id' => $transaction->transaction_id,
                    'original_currency' => $transaction->currency,
                    'original_amount' => $transaction->total_amount
                ]);
                $amountInXAF = $transaction->total_amount;
            }
            
            // Log the conversion details
            Log::info('Currency conversion for Lygos payment', [
                'transaction_id' => $transaction->transaction_id,
                'original_currency' => $transaction->currency,
                'original_amount' => $transaction->total_amount,
                'converted_amount_xaf' => $amountInXAF
            ]);
            
            // Create return and cancel URLs
            $successUrl = $paymentData['success_url'] ?? route('api.transactions.callback.success');
            $failureUrl = $paymentData['failure_url'] ?? route('api.transactions.callback.failure');
            
            // Prepare the request data with converted amount
            $requestData = [
                'amount' => (int)$amountInXAF,
                'shop_name' => $environment ? $environment->name : 'CSL Certification Platform',
                'message' => $transaction->description ?? 'Payment for certification services',
                'success_url' => $successUrl,
                'failure_url' => $failureUrl,
                'order_id' => $transaction->transaction_id
            ];

            // Make an actual API call to Lygos
            $response = Http::withHeaders([
                'api-key' => $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post($this->apiUrl, $requestData);
            
            // Check if the API call was successful
            if (!$response->successful()) {
                Log::error('Lygos payment gateway creation failed: ' . $response->body());
                return [
                    'success' => false,
                    'message' => 'Failed to create Lygos payment gateway: ' . ($response->json()['message'] ?? 'Unknown error')
                ];
            }
            
            // Get the response data from the API
            $responseData = $response->json();
            $gatewayId = $responseData['id'] ?? Str::uuid()->toString();
            $paymentLink = $responseData['link'] ?? "https://checkout.lygosapp.com/pay/{$gatewayId}";
            
            // Log successful API call
            Log::info('Lygos payment gateway created successfully', [
                'gateway_id' => $gatewayId,
                'transaction_id' => $transaction->transaction_id,
                'payment_link' => $paymentLink
            ]);

            // Update transaction with gateway ID
            $transaction->gateway_transaction_id = $gatewayId;
            $transaction->save();
            
            return [
                'success' => true,
                'message' => 'Payment gateway created successfully',
                'transaction_id' => $gatewayId,
                'checkout_url' => $paymentLink,
                'type' => 'payment_url',
                'value' => $paymentLink,
                'amount' => $transaction->total_amount,
                'currency' => $transaction->currency,
                'payment_method' => 'lygos',
                'payment_type' => 'lygos',
                'created' => time(),
                'response' => $responseData
            ];
        } catch (\Exception $e) {
            Log::error('Lygos payment creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to create payment: ' . $e->getMessage(),
                'error' => $e->getMessage(),
                'error_code' => $e->getCode()
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

            // Get environment details
            $environment = null;
            if ($transaction->environment_id) {
                $environment = Environment::find($transaction->environment_id);
            }

            // Convert amount to XAF since Lygos requires XAF currency
            $amountInXAF = $transaction->convertToXAF();
            
            // If conversion failed, log error and use original amount
            if ($amountInXAF === null) {
                Log::warning('Currency conversion to XAF failed. Using original amount.', [
                    'transaction_id' => $transaction->transaction_id,
                    'original_currency' => $transaction->currency,
                    'original_amount' => $transaction->total_amount
                ]);
                $amountInXAF = $transaction->total_amount;
            }
            
            // Log the conversion details
            Log::info('Currency conversion for Lygos payment', [
                'transaction_id' => $transaction->transaction_id,
                'original_currency' => $transaction->currency,
                'original_amount' => $transaction->total_amount,
                'converted_amount_xaf' => $amountInXAF
            ]);
            
            // Prepare the request data with converted amount
            $requestData = [
                'amount' => (int)$amountInXAF,
                'shop_name' => $environment ? $environment->name : 'CSL Certification Platform',
                'message' => $transaction->description ?? 'Payment for certification services',
                'success_url' => $paymentData['success_url'] ?? route('api.transactions.callback.success'),
                'failure_url' => $paymentData['failure_url'] ?? route('api.transactions.callback.failure'),
                'order_id' => $transaction->transaction_id
            ];

            // Make an actual API call to Lygos
            $response = Http::withHeaders([
                'api-key' => $this->apiKey,
                'Content-Type' => 'application/json'
            ])->post('https://api.lygosapp.com/v1/gateway', $requestData);
            
            // Check if the API call was successful
            if (!$response->successful()) {
                Log::error('Lygos payment gateway creation failed: ' . $response->body());
                return [
                    'success' => false,
                    'message' => 'Failed to create Lygos payment gateway: ' . ($response->json()['message'] ?? 'Unknown error')
                ];
            }
            
            // Get the response data from the API
            $responseData = $response->json();
            $gatewayId = $responseData['id'] ?? Str::uuid()->toString();
            $paymentLink = $responseData['link'] ?? "https://checkout.lygosapp.com/pay/{$gatewayId}";
            
            // Log successful API call
            Log::info('Lygos payment gateway created successfully', [
                'gateway_id' => $gatewayId,
                'transaction_id' => $transaction->transaction_id,
                'payment_link' => $paymentLink
            ]);

            // Update transaction with gateway ID
            $transaction->gateway_transaction_id = $gatewayId;
            $transaction->save();
            
            return [
                'success' => true,
                'message' => 'Payment gateway created successfully',
                'transaction_id' => $gatewayId,
                'checkout_url' => $paymentLink,
                'amount' => $transaction->total_amount,
                'currency' => $transaction->currency,
                'payment_method' => 'lygos',
                'payment_type' => 'lygos',
                'created' => time(),
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
