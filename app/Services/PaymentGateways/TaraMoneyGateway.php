<?php

namespace App\Services\PaymentGateways;

use App\Models\Transaction;
use App\Models\PaymentGatewaySetting;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class TaraMoneyGateway implements PaymentGatewayInterface
{
    /**
     * TaraMoney API Key
     *
     * @var string
     */
    protected $apiKey;

    /**
     * TaraMoney Business ID
     *
     * @var string
     */
    protected $businessId;

    /**
     * TaraMoney Webhook Secret
     *
     * @var string
     */
    protected $webhookSecret;

    /**
     * TaraMoney API base URL
     *
     * @var string
     */
    protected $apiBaseUrl = 'https://www.dklo.co/api/tara';

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
        $this->businessId = $settings->getSetting('business_id');
        $this->webhookSecret = $settings->getSetting('webhook_secret');

        // Check for test mode and use appropriate keys
        $isTestMode = $settings->getSetting('test_mode', false);
        if ($isTestMode) {
            // Try to get sandbox keys if in test mode
            $testApiKey = $settings->getSetting('test_api_key');
            $testBusinessId = $settings->getSetting('test_business_id');

            if (!empty($testApiKey) && !empty($testBusinessId)) {
                $this->apiKey = $testApiKey;
                $this->businessId = $testBusinessId;
                Log::info('[TaraMoneyGateway] Using sandbox API credentials');
            }
        }

        // Enhanced logging for TaraMoney initialization
        Log::info('[TaraMoneyGateway] Initializing TaraMoney client', [
            'gateway_id' => $settings->id,
            'gateway_code' => $settings->code,
            'environment_id' => $settings->environment_id,
            'api_key_present' => !empty($this->apiKey),
            'business_id_present' => !empty($this->businessId),
            'webhook_secret_present' => !empty($this->webhookSecret),
            'test_mode' => $isTestMode
        ]);

        // Check if API credentials are available before initializing
        if (empty($this->apiKey) || empty($this->businessId)) {
            Log::error('[TaraMoneyGateway] Missing API credentials', [
                'gateway_id' => $settings->id,
                'gateway_code' => $settings->code,
                'environment_id' => $settings->environment_id
            ]);
            throw new \Exception('TaraMoney API credentials are missing. Please check your payment gateway settings.');
        }
    }

    /**
     * Create a payment session/intent
     *
     * This method creates a TaraMoney payment link and returns it for redirection
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
            Log::info('[TaraMoneyGateway] Attempting to create payment', [
                'transaction_id' => $transaction->id,
                'order_id' => $transaction->order_id,
                'amount' => $transaction->total_amount,
                'currency' => $transaction->currency,
                'api_key_present' => !empty($this->apiKey),
                'business_id_present' => !empty($this->businessId),
                'gateway_id' => $this->settings->id ?? null,
                'gateway_code' => $this->settings->code ?? null
            ]);

            // Convert amount to XAF since TaraMoney primarily works with XAF currency
            $amountInXAF = $transaction->convertToXAF();

            // If conversion failed, log error and use original amount
            if ($amountInXAF === null) {
                Log::warning('[TaraMoneyGateway] Currency conversion to XAF failed. Using original amount.', [
                    'transaction_id' => $transaction->transaction_id,
                    'original_currency' => $transaction->currency,
                    'original_amount' => $transaction->total_amount
                ]);
                $amountInXAF = $transaction->total_amount;
            }

            // Log the conversion details
            Log::info('[TaraMoneyGateway] Currency conversion for payment', [
                'transaction_id' => $transaction->transaction_id,
                'original_currency' => $transaction->currency,
                'original_amount' => $transaction->total_amount,
                'converted_amount_xaf' => $amountInXAF
            ]);

            // Create return and webhook URLs
            // For local development with HTTP, use a placeholder HTTPS URL for TaraMoney
            $appUrl = config('app.url');
            $isLocalHttp = str_starts_with($appUrl, 'http://localhost') || str_starts_with($appUrl, 'http://127.0.0.1');
            
            if ($isLocalHttp) {
                // Use production HTTPS URL for local development (TaraMoney requires HTTPS)
                $baseUrl = 'https://certification.csl-brands.com';
                Log::warning('[TaraMoneyGateway] Using production HTTPS URL for local development', [
                    'original_url' => $appUrl,
                    'production_url' => $baseUrl
                ]);
            } else {
                $baseUrl = $appUrl;
            }
            
            $returnUrl = $paymentData['return_url'] ?? $baseUrl . '/api/transactions/callback/success?environment_id=' . $environmentId;
            $webhookUrl = $baseUrl . '/api/transactions/webhook/taramoney?environment_id=' . $environmentId;

            // Get product information from order
            $order = \App\Models\Order::find($transaction->order_id);
            $productName = 'Product';
            $productDescription = $transaction->description ?? 'Payment for certification services';
            $productPictureUrl = '';

            if ($order && $order->orderItems->isNotEmpty()) {
                $firstItem = $order->orderItems->first();
                $productName = $firstItem->product->name ?? 'Product';
                $productDescription = $firstItem->product->description ?? $productDescription;
                $productPictureUrl = $firstItem->product->image_url ?? '';
            }

            // Prepare payment data for TaraMoney Order API
            $requestData = [
                'apiKey' => $this->apiKey,
                'businessId' => $this->businessId,
                'productId' => $transaction->order_id ?? 'product-' . $transaction->id,
                'productName' => $productName,
                'productPrice' => (int)$amountInXAF,
                'productDescription' => $productDescription,
                'productPictureUrl' => $productPictureUrl,
                'returnUrl' => $returnUrl,
                'webHookUrl' => $webhookUrl
            ];

            // Generate payment link using TaraMoney Order API
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($this->apiBaseUrl . '/order', $requestData);

            // Check if the request was successful
            if ($response->successful()) {
                $responseData = $response->json();

                // Check if payment links were generated
                // TaraMoney returns 'API_ORDER_SUCESSFULL' (note the typo in their API)
                $isSuccess = (isset($responseData['status']) && $responseData['status'] === 'SUCCESS') ||
                             (isset($responseData['error']) && in_array($responseData['error'], ['API_ORDER_SUCESSFULL', 'API_ORDER_SUCCESSFUL']));
                
                if ($isSuccess) {
                    // Generate a unique gateway transaction ID
                    $gatewayId = $transaction->transaction_id;

                    // Update transaction with gateway ID
                    $transaction->gateway_transaction_id = $gatewayId;
                    $transaction->payment_gateway_setting_id = $this->settings->id;
                    $transaction->gateway_response = json_encode($responseData);
                    $transaction->save();

                    Log::info('[TaraMoneyGateway] Payment links created successfully', [
                        'transaction_id' => $transaction->id,
                        'gateway_transaction_id' => $gatewayId,
                        'whatsapp_link' => $responseData['whatsappLink'] ?? null,
                        'telegram_link' => $responseData['telegramLink'] ?? null
                    ]);

                    // Return all payment links for user selection
                    // Use generalLink for direct redirect if available, otherwise show all options
                    $paymentLinks = [
                        'whatsapp' => $responseData['whatsappLink'] ?? null,
                        'telegram' => $responseData['telegramLink'] ?? null,
                        'dikalo' => $responseData['dikaloLink'] ?? null,
                        'sms' => $responseData['smsLink'] ?? null,
                    ];

                    // Filter out null links
                    $paymentLinks = array_filter($paymentLinks);

                    // Check if generalLink is available (new TaraMoney API)
                    $generalLink = $responseData['generalLink'] ?? null;
                    $hasGeneralLink = !empty($generalLink);

                    return [
                        'success' => true,
                        'message' => $hasGeneralLink 
                            ? 'Payment link created successfully.' 
                            : 'Payment links created successfully. Choose your preferred payment method.',
                        'transaction_id' => $gatewayId,
                        'type' => $hasGeneralLink ? 'redirect_url' : 'payment_links',
                        'redirect_url' => $generalLink,
                        'general_link' => $generalLink,
                        'payment_links' => $paymentLinks,
                        'whatsapp_link' => $responseData['whatsappLink'] ?? null,
                        'telegram_link' => $responseData['telegramLink'] ?? null,
                        'dikalo_link' => $responseData['dikaloLink'] ?? null,
                        'sms_link' => $responseData['smsLink'] ?? null,
                        'card_link' => $responseData['cardLink'] ?? null,
                        'amount' => $transaction->total_amount,
                        'currency' => $transaction->currency,
                        'payment_method' => 'taramoney',
                        'payment_type' => 'taramoney',
                        'created' => time(),
                        'response' => $responseData,
                        'gateway_config' => $this->getConfig(),
                        'status' => 'pending'
                    ];
                } else {
                    Log::error('[TaraMoneyGateway] Payment link generation failed', [
                        'transaction_id' => $transaction->id,
                        'response' => $responseData
                    ]);

                    return [
                        'success' => false,
                        'message' => $responseData['message'] ?? 'Failed to generate payment link',
                        'error_details' => $responseData['message'] ?? 'Unknown error'
                    ];
                }
            } else {
                Log::error('[TaraMoneyGateway] Failed to create payment', [
                    'transaction_id' => $transaction->id,
                    'status_code' => $response->status(),
                    'response' => $response->json() ?? $response->body()
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to create payment with TaraMoney',
                    'error_details' => $response->json()['message'] ?? 'API request failed with status ' . $response->status()
                ];
            }
        } catch (\Exception $e) {
            Log::error('[TaraMoneyGateway] Exception while creating payment', [
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
     * Create mobile money payment (Orange Money & MTN Money)
     *
     * @param Transaction $transaction
     * @param array $paymentData Must include 'phoneNumber'
     * @return array
     */
    public function createMobileMoneyPayment(Transaction $transaction, array $paymentData = []): array
    {
        try {
            $environmentId = session("current_environment_id");

            // Validate phone number
            if (empty($paymentData['phoneNumber'])) {
                return [
                    'success' => false,
                    'message' => 'Phone number is required for mobile money payment'
                ];
            }

            // Log before attempting to create payment
            Log::info('[TaraMoneyGateway] Attempting to create mobile money payment', [
                'transaction_id' => $transaction->id,
                'order_id' => $transaction->order_id,
                'amount' => $transaction->total_amount,
                'phone_number' => $paymentData['phoneNumber']
            ]);

            // Convert amount to XAF
            $amountInXAF = $transaction->convertToXAF();
            if ($amountInXAF === null) {
                $amountInXAF = $transaction->total_amount;
            }

            // Create webhook URL
            $webhookUrl = route('api.transactions.webhook', ['gateway' => 'taramoney', 'environment_id' => $environmentId]);

            // Get product information
            $order = \App\Models\Order::find($transaction->order_id);
            $productName = 'Product';

            if ($order && $order->orderItems->isNotEmpty()) {
                $firstItem = $order->orderItems->first();
                $productName = $firstItem->product->name ?? 'Product';
            }

            // Prepare payment data for TaraMoney Mobile Money API
            $requestData = [
                'apiKey' => $this->apiKey,
                'businessId' => $this->businessId,
                'productId' => $transaction->order_id ?? 'product-' . $transaction->id,
                'productName' => $productName,
                'productPrice' => (int)$amountInXAF,
                'phoneNumber' => $paymentData['phoneNumber'],
                'webHookUrl' => $webhookUrl
            ];

            // Initiate mobile money payment
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post($this->apiBaseUrl . '/cmmobile', $requestData);

            if ($response->successful()) {
                $responseData = $response->json();

                if (isset($responseData['status']) && $responseData['status'] === 'SUCCESS') {
                    // Update transaction with gateway ID
                    $gatewayId = $responseData['paymentId'] ?? $transaction->transaction_id;
                    $transaction->gateway_transaction_id = $gatewayId;
                    $transaction->payment_gateway_setting_id = $this->settings->id;
                    $transaction->gateway_response = json_encode($responseData);
                    $transaction->save();

                    Log::info('[TaraMoneyGateway] Mobile money payment initiated successfully', [
                        'transaction_id' => $transaction->id,
                        'gateway_transaction_id' => $gatewayId,
                        'ussd_code' => $responseData['ussdCode'] ?? null,
                        'vendor' => $responseData['vendor'] ?? null
                    ]);

                    return [
                        'success' => true,
                        'message' => $responseData['message'] ?? 'Please dial the USSD code to complete payment',
                        'transaction_id' => $gatewayId,
                        'ussd_code' => $responseData['ussdCode'] ?? null,
                        'vendor' => $responseData['vendor'] ?? null,
                        'type' => 'mobile_money',
                        'amount' => $transaction->total_amount,
                        'currency' => $transaction->currency,
                        'payment_method' => 'taramoney',
                        'payment_type' => 'mobile_money',
                        'created' => time(),
                        'response' => $responseData,
                        'gateway_config' => $this->getConfig(),
                        'status' => 'pending'
                    ];
                } else {
                    Log::error('[TaraMoneyGateway] Mobile money payment initiation failed', [
                        'transaction_id' => $transaction->id,
                        'response' => $responseData
                    ]);

                    return [
                        'success' => false,
                        'message' => $responseData['message'] ?? 'Failed to initiate mobile money payment',
                        'error_details' => $responseData
                    ];
                }
            } else {
                Log::error('[TaraMoneyGateway] Failed to create mobile money payment', [
                    'transaction_id' => $transaction->id,
                    'status_code' => $response->status(),
                    'response' => $response->json() ?? $response->body()
                ]);

                return [
                    'success' => false,
                    'message' => 'Failed to create mobile money payment with TaraMoney',
                    'error_details' => $response->json()['message'] ?? 'API request failed'
                ];
            }
        } catch (\Exception $e) {
            Log::error('[TaraMoneyGateway] Exception while creating mobile money payment', [
                'transaction_id' => $transaction->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'message' => 'An error occurred while creating mobile money payment: ' . $e->getMessage()
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
        // Check if this is a mobile money payment request
        if (!empty($paymentData['phoneNumber']) && !empty($paymentData['paymentType']) && $paymentData['paymentType'] === 'mobile_money') {
            return $this->createMobileMoneyPayment($transaction, $paymentData);
        }

        // Default to order link payment
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
            Log::info('[TaraMoneyGateway] Verifying payment', ['transaction_id' => $transactionId]);

            // Find the transaction in our database
            $transaction = Transaction::where('transaction_id', $transactionId)
                ->orWhere('gateway_transaction_id', $transactionId)
                ->first();

            if (!$transaction) {
                Log::error('[TaraMoneyGateway] Transaction not found for verification', ['transaction_id' => $transactionId]);
                return [
                    'success' => false,
                    'message' => 'Transaction not found'
                ];
            }

            // For TaraMoney, we rely on webhook callbacks for payment status
            // This method returns the current status from our database
            $status = $transaction->status;

            Log::info('[TaraMoneyGateway] Payment verification from database', [
                'transaction_id' => $transaction->id,
                'gateway_transaction_id' => $transaction->gateway_transaction_id,
                'status' => $status
            ]);

            return [
                'success' => true,
                'status' => $status === Transaction::STATUS_COMPLETED ? 'succeeded' : $status,
                'transaction_id' => $transaction->transaction_id,
                'gateway_transaction_id' => $transaction->gateway_transaction_id,
                'amount' => $transaction->total_amount,
                'currency' => $transaction->currency
            ];
        } catch (\Exception $e) {
            Log::error('[TaraMoneyGateway] Exception while verifying payment', [
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
        // TaraMoney may not support automatic refunds via API
        // This is a placeholder implementation
        Log::warning('[TaraMoneyGateway] Refund requested but not supported via API', [
            'transaction_id' => $transaction->id,
            'gateway_transaction_id' => $transaction->gateway_transaction_id,
            'amount' => $amount,
            'reason' => $reason
        ]);

        return [
            'success' => false,
            'message' => 'Refunds are not supported automatically via TaraMoney API. Please process the refund manually.'
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
            'gateway' => 'taramoney',
            'display_name' => $this->settings->getSetting('display_name', 'TaraMoney'),
            'description' => $this->settings->getSetting('description', 'Pay with TaraMoney (WhatsApp, Telegram, Mobile Money)'),
            'logo_url' => $this->settings->getSetting('logo_url', ''),
            'supported_currencies' => explode(',', $this->settings->getSetting('supported_currencies', 'XAF,XOF')),
            'supports_mobile_money' => true,
            'supports_messaging_apps' => true,
            'redirect_payment' => true
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
            // TaraMoney uses webhook secret for verification
            // The signature verification logic depends on how TaraMoney signs webhooks

            Log::info('[TaraMoneyGateway] Verifying webhook signature', [
                'payload_present' => !empty($payload),
                'signature_present' => !empty($signature),
                'secret_present' => !empty($secret)
            ]);

            // For now, we'll use the webhook secret directly
            // Update this based on TaraMoney's actual webhook signature verification method
            $webhookSecret = $secret ?: $this->webhookSecret;

            if (empty($webhookSecret)) {
                Log::warning('[TaraMoneyGateway] No webhook secret configured, skipping signature verification');
                return true; // Allow webhook if no secret is configured
            }

            // Implement actual signature verification based on TaraMoney documentation
            // This is a placeholder implementation
            return true;
        } catch (\Exception $e) {
            Log::error('[TaraMoneyGateway] Exception while verifying webhook signature', [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Create a payment link for an invoice
     *
     * @param \App\Models\Invoice $invoice
     * @return string
     */
    public function createInvoicePaymentLink(\App\Models\Invoice $invoice)
    {
        try {
            // Create a transaction for the invoice
            $transaction = new Transaction();
            $transaction->invoice_id = $invoice->id;
            $transaction->environment_id = $invoice->environment_id;
            $transaction->customer_id = $invoice->customer_id;
            $transaction->amount = $invoice->total_amount;
            $transaction->total_amount = $invoice->total_amount;
            $transaction->currency = $invoice->currency ?? 'XAF';
            $transaction->description = 'Payment for invoice #' . $invoice->invoice_number;
            $transaction->status = Transaction::STATUS_PENDING;
            $transaction->transaction_id = 'TXN_' . Str::uuid();
            $transaction->save();

            // Create payment using the transaction
            $result = $this->createPayment($transaction, []);

            if ($result['success']) {
                return $result['checkout_url'] ?? $result['dikalo_link'] ?? '';
            }

            Log::error('[TaraMoneyGateway] Failed to create invoice payment link', [
                'invoice_id' => $invoice->id,
                'result' => $result
            ]);

            return '';
        } catch (\Exception $e) {
            Log::error('[TaraMoneyGateway] Exception while creating invoice payment link', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage()
            ]);

            return '';
        }
    }
}
