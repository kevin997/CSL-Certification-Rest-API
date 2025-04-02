<?php

namespace App\Services\PaymentGateways;

use App\Models\Transaction;
use App\Models\PaymentGatewaySetting;
use Illuminate\Support\Facades\Log;
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
        
        // Set API URL based on mode
        if ($settings->mode === 'sandbox') {
            $this->apiUrl = 'https://api-m.sandbox.paypal.com';
        } else {
            $this->apiUrl = 'https://api-m.paypal.com';
        }
    }
    
    /**
     * Process a payment
     *
     * @param Transaction $transaction
     * @param array $paymentData
     * @return array
     */
    public function processPayment(Transaction $transaction, array $paymentData): array
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
            'description' => 'Accept payments with PayPal',
            'is_enabled' => true,
            'mode' => $this->settings->mode,
            'supports' => [
                'paypal' => true,
                'credit_card' => true,
                'venmo' => true,
                'pay_later' => true
            ],
            'client_side' => true,
            'client_id' => $this->clientId,
            'webhook_url' => $this->settings->webhook_url,
            'test_mode' => $this->settings->mode === 'sandbox'
        ];
    }
}
