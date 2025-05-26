<?php

namespace App\Services\PaymentGateways;

use App\Models\Transaction;
use App\Models\PaymentGatewaySetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class PayPalGateway implements PaymentGatewayInterface
{
    /**
     * PayPal client ID
     *
     * @var string
     */
    protected $clientId;
    
    /**
     * PayPal client secret
     *
     * @var string
     */
    protected $clientSecret;
    
    /**
     * PayPal API URL
     *
     * @var string
     */
    protected $apiUrl;
    
    /**
     * PayPal environment (sandbox or live)
     * 
     * @var string
     */
    protected $environment;
    
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
        $this->clientId = $settings->getSetting('client_id');
        $this->clientSecret = $settings->getSetting('client_secret');
        $this->environment = $settings->getSetting('is_sandbox', true) ? 'sandbox' : 'live';
        $this->apiUrl = $this->environment === 'sandbox'
            ? 'https://api-m.sandbox.paypal.com' 
            : 'https://api-m.paypal.com';
    }
    
    /**
     * Create a payment session/intent
     * 
     * This method creates a PayPal order and returns the checkout URL
     * for redirecting the customer to PayPal's checkout page
     *
     * @param Transaction $transaction
     * @param array $paymentData
     * @return array
     */
    public function createPayment(Transaction $transaction, array $paymentData = []): array
    {
        try {
            // Get access token
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                return [
                    'success' => false,
                    'message' => 'Failed to authenticate with PayPal'
                ];
            }
            
            // Create return and cancel URLs
            $returnUrl = URL::to('/api/payments/paypal/return?transaction_id=' . $transaction->id);
            $cancelUrl = URL::to('/api/payments/paypal/cancel?transaction_id=' . $transaction->id);
            
            // Create PayPal order
            $response = Http::withToken($accessToken)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                ])
                ->post($this->apiUrl . '/v2/checkout/orders', [
                    'intent' => 'CAPTURE',
                    'purchase_units' => [
                        [
                            'reference_id' => (string) $transaction->id,
                            'description' => $transaction->description,
                            'amount' => [
                                'currency_code' => strtoupper($transaction->currency),
                                'value' => number_format($transaction->total_amount, 2, '.', ''),
                            ],
                        ],
                    ],
                    'application_context' => [
                        'brand_name' => config('app.name'),
                        'landing_page' => 'BILLING',
                        'shipping_preference' => 'NO_SHIPPING',
                        'user_action' => 'PAY_NOW',
                        'return_url' => $returnUrl,
                        'cancel_url' => $cancelUrl,
                    ],
                ]);
            
            if ($response->successful()) {
                $paypalOrder = $response->json();
                
                // Find the approve link
                $approveLink = null;
                foreach ($paypalOrder['links'] as $link) {
                    if ($link['rel'] === 'approve') {
                        $approveLink = $link['href'];
                        break;
                    }
                }
                
                if (!$approveLink) {
                    return [
                        'success' => false,
                        'message' => 'PayPal checkout URL not found in response'
                    ];
                }
                
                // Update transaction with PayPal order ID
                $transaction->gateway_transaction_id = $paypalOrder['id'];
                $transaction->save();
                
                // Return the checkout URL for redirect
                return [
                    'success' => true,
                    'type' => 'checkout_url',
                    'value' => $approveLink,
                    'order_id' => $paypalOrder['id']
                ];
            } else {
                Log::error('PayPal order creation failed: ' . $response->body());
                return [
                    'success' => false,
                    'message' => 'Failed to create PayPal order: ' . ($response->json()['message'] ?? 'Unknown error')
                ];
            }
        } catch (\Exception $e) {
            Log::error('PayPal payment creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Failed to create payment: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Get PayPal access token
     * 
     * @return string|null
     */
    protected function getAccessToken(): ?string
    {
        try {
            $response = Http::withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->post($this->apiUrl . '/v1/oauth2/token', [
                    'grant_type' => 'client_credentials'
                ]);
                
            if ($response->successful()) {
                return $response->json()['access_token'];
            }
            
            Log::error('Failed to get PayPal access token: ' . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error('PayPal authentication failed: ' . $e->getMessage());
            return null;
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
        if (!isset($paymentData['order_id']) && !isset($paymentData['payment_id'])) {
            return [
                'success' => false,
                'message' => 'Missing PayPal order ID or payment ID'
            ];
        }
        
        try {
            // In a real implementation, we would use the PayPal SDK to process the payment
            // For this demo, we'll simulate a successful payment
            
            // Simulate API call delay
            usleep(500000); // 0.5 seconds
            
            // Generate a PayPal-like transaction ID
            $paypalTransactionId = $paymentData['payment_id'] ?? 'PAY-' . Str::random(20);
            
            // Prepare response data
            $responseData = [
                'id' => $paypalTransactionId,
                'intent' => 'CAPTURE',
                'status' => 'COMPLETED',
                'purchase_units' => [
                    [
                        'reference_id' => $transaction->transaction_id,
                        'amount' => [
                            'currency_code' => $transaction->currency,
                            'value' => (string)$transaction->total_amount
                        ],
                        'payee' => [
                            'email_address' => 'merchant@cslcertification.com',
                            'merchant_id' => 'MERCHANT_ID'
                        ],
                        'description' => $transaction->description,
                        'payments' => [
                            'captures' => [
                                [
                                    'id' => 'CAP-' . Str::random(17),
                                    'status' => 'COMPLETED',
                                    'amount' => [
                                        'currency_code' => $transaction->currency,
                                        'value' => (string)$transaction->total_amount
                                    ],
                                    'final_capture' => true,
                                    'seller_protection' => [
                                        'status' => 'ELIGIBLE',
                                        'dispute_categories' => [
                                            'ITEM_NOT_RECEIVED',
                                            'UNAUTHORIZED_TRANSACTION'
                                        ]
                                    ],
                                    'create_time' => date('Y-m-d\TH:i:s\Z'),
                                    'update_time' => date('Y-m-d\TH:i:s\Z')
                                ]
                            ]
                        ]
                    ]
                ],
                'payer' => [
                    'name' => [
                        'given_name' => explode(' ', $transaction->customer_name)[0] ?? '',
                        'surname' => explode(' ', $transaction->customer_name)[1] ?? ''
                    ],
                    'email_address' => $transaction->customer_email,
                    'payer_id' => 'PAYER_' . Str::random(10)
                ],
                'create_time' => date('Y-m-d\TH:i:s\Z'),
                'update_time' => date('Y-m-d\TH:i:s\Z'),
                'links' => [
                    [
                        'href' => "{$this->apiUrl}/v2/checkout/orders/{$paypalTransactionId}",
                        'rel' => 'self',
                        'method' => 'GET'
                    ]
                ]
            ];
            
            return [
                'success' => true,
                'message' => 'Payment processed successfully',
                'transaction_id' => $paypalTransactionId,
                'status' => 'COMPLETED',
                'amount' => $transaction->total_amount,
                'currency' => $transaction->currency,
                'payment_method' => 'paypal',
                'created' => strtotime($responseData['create_time']),
                'response' => $responseData
            ];
        } catch (\Exception $e) {
            Log::error('PayPal payment error: ' . $e->getMessage());
            
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
            // In a real implementation, we would use the PayPal SDK to verify the payment
            // For this demo, we'll simulate a successful verification
            
            // Simulate API call delay
            usleep(300000); // 0.3 seconds
            
            return [
                'success' => true,
                'message' => 'Payment verified',
                'transaction_id' => $transactionId,
                'status' => 'COMPLETED',
                'verified' => true
            ];
        } catch (\Exception $e) {
            Log::error('PayPal verification error: ' . $e->getMessage());
            
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
            // In a real implementation, we would use the PayPal SDK to process the refund
            // For this demo, we'll simulate a successful refund
            
            // Simulate API call delay
            usleep(500000); // 0.5 seconds
            
            // If amount is not specified, refund the full amount
            if ($amount === null) {
                $amount = $transaction->total_amount;
            }
            
            // Generate a PayPal-like refund ID
            $refundId = 'REF-' . Str::random(17);
            
            // Prepare response data
            $responseData = [
                'id' => $refundId,
                'status' => 'COMPLETED',
                'amount' => [
                    'currency_code' => $transaction->currency,
                    'value' => (string)$amount
                ],
                'seller_payable_breakdown' => [
                    'gross_amount' => [
                        'currency_code' => $transaction->currency,
                        'value' => (string)$amount
                    ],
                    'paypal_fee' => [
                        'currency_code' => $transaction->currency,
                        'value' => '0.00'
                    ],
                    'net_amount' => [
                        'currency_code' => $transaction->currency,
                        'value' => (string)$amount
                    ]
                ],
                'invoice_id' => $transaction->transaction_id,
                'note_to_payer' => $reason,
                'create_time' => date('Y-m-d\TH:i:s\Z'),
                'update_time' => date('Y-m-d\TH:i:s\Z'),
                'links' => [
                    [
                        'href' => "{$this->apiUrl}/v2/payments/refunds/{$refundId}",
                        'rel' => 'self',
                        'method' => 'GET'
                    ]
                ]
            ];
            
            return [
                'success' => true,
                'message' => 'Refund processed successfully',
                'refund_id' => $refundId,
                'transaction_id' => $transaction->gateway_transaction_id,
                'amount' => $amount,
                'currency' => $transaction->currency,
                'reason' => $reason,
                'created' => strtotime($responseData['create_time']),
                'status' => 'COMPLETED',
                'response' => $responseData
            ];
        } catch (\Exception $e) {
            Log::error('PayPal refund error: ' . $e->getMessage());
            
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
            'name' => 'PayPal',
            'code' => 'paypal',
            'description' => 'Pay with PayPal',
            'is_enabled' => true,
            'mode' => $this->settings->mode,
            'supports' => [
                'paypal' => true,
                'credit_card' => true,
                'venmo' => true,
                'pay_later' => true
            ],
            'client_side' => false,
            'redirect_based' => true,
            'webhook_url' => $this->settings->webhook_url,
            'client_id' => $this->settings->getSetting('client_id'),
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
            // In a real implementation, we would use PayPal's SDK to verify the signature
            // For this demo, we'll implement a basic verification
            
            // PayPal sends multiple headers for verification
            $headers = json_decode($signature, true) ?: [];
            
            if (empty($headers)) {
                return false;
            }
            
            // Check for required headers
            if (!isset($headers['paypal-auth-algo']) || 
                !isset($headers['paypal-cert-url']) || 
                !isset($headers['paypal-transmission-id']) || 
                !isset($headers['paypal-transmission-sig']) || 
                !isset($headers['paypal-transmission-time'])) {
                return false;
            }
            
            // In a real implementation, we would:
            // 1. Get the certificate from the cert-url
            // 2. Verify the signature using the certificate
            // 3. Validate the webhook event
            
            // For this demo, we'll simulate a successful verification
            return true;
        } catch (\Exception $e) {
            // Log the error
            \Illuminate\Support\Facades\Log::error('PayPal webhook verification failed: ' . $e->getMessage());
            return false;
        }
    }
}
