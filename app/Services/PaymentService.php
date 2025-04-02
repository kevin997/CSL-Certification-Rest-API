<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Transaction;
use App\Models\PaymentGatewaySetting;
use App\Services\PaymentGateways\PaymentGatewayFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PaymentService
{
    /**
     * @var OrderService
     */
    protected $orderService;

    /**
     * Constructor
     */
    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }
    
    /**
     * Process payment for an order
     *
     * @param int $orderId
     * @param array $paymentData
     * @return array
     */
    public function processPayment(int $orderId, array $paymentData): array
    {
        $order = $this->orderService->getOrderById($orderId);
        
        if (!$order) {
            return [
                'success' => false,
                'message' => 'Order not found'
            ];
        }
        
        // Check if order is already paid
        if ($order->payment_status === 'paid') {
            return [
                'success' => false,
                'message' => 'Order is already paid'
            ];
        }
        
        // Validate payment method
        if (!isset($paymentData['payment_method'])) {
            return [
                'success' => false,
                'message' => 'Payment method is required'
            ];
        }
        
        // Process payment based on payment method
        switch ($paymentData['payment_method']) {
            case 'credit_card':
                return $this->processCreditCardPayment($order, $paymentData);
                
            case 'paypal':
                return $this->processPayPalPayment($order, $paymentData);
                
            case 'bank_transfer':
                return $this->processBankTransferPayment($order, $paymentData);
                
            case 'manual':
                return $this->processManualPayment($order, $paymentData);
                
            case 'stripe':
                return $this->processGatewayPayment($order, 'stripe', $paymentData);
                
            case 'lygos':
                return $this->processGatewayPayment($order, 'lygos', $paymentData);
                
            default:
                // Check if this is a registered gateway
                if (PaymentGatewayFactory::isSupported($paymentData['payment_method'])) {
                    return $this->processGatewayPayment($order, $paymentData['payment_method'], $paymentData);
                }
                
                return [
                    'success' => false,
                    'message' => 'Unsupported payment method'
                ];
        }
    }
    
    /**
     * Process payment using a payment gateway
     *
     * @param Order $order
     * @param string $gatewayCode
     * @param array $paymentData
     * @return array
     */
    protected function processGatewayPayment(Order $order, string $gatewayCode, array $paymentData): array
    {
        try {
            // Get the payment gateway settings
            $gatewaySettings = PaymentGatewaySetting::where('gateway_code', $gatewayCode)
                ->where('environment_id', $paymentData['environment_id'] ?? null)
                ->where('status', true)
                ->first();
            
            if (!$gatewaySettings) {
                return [
                    'success' => false,
                    'message' => 'Payment gateway settings not found'
                ];
            }
            
            // Create a new transaction
            $transaction = new Transaction();
            $transaction->order_id = $order->id;
            $transaction->environment_id = $paymentData['environment_id'] ?? $gatewaySettings->environment_id;
            $transaction->payment_gateway_setting_id = $gatewaySettings->id;
            $transaction->gateway_code = $gatewayCode;
            $transaction->transaction_id = 'TXN-' . Str::random(16);
            $transaction->customer_name = $order->customer_name;
            $transaction->customer_email = $order->customer_email;
            $transaction->total_amount = $order->total;
            $transaction->currency = $order->currency ?? 'USD';
            $transaction->description = 'Payment for order #' . $order->order_number;
            $transaction->status = 'pending';
            $transaction->metadata = json_encode([
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'payment_data' => $paymentData
            ]);
            $transaction->save();
            
            // Process the payment using the gateway
            $gateway = PaymentGatewayFactory::create($gatewayCode, $gatewaySettings);
            
            if (!$gateway) {
                return [
                    'success' => false,
                    'message' => 'Failed to initialize payment gateway'
                ];
            }
            
            $paymentResponse = $gateway->processPayment($transaction, $paymentData);
            
            // Update the transaction with the gateway response
            $transaction->gateway_transaction_id = $paymentResponse['transaction_id'] ?? null;
            $transaction->status = $paymentResponse['success'] ? ($paymentResponse['status'] ?? 'pending') : 'failed';
            $transaction->response_data = json_encode($paymentResponse);
            $transaction->processed_at = now();
            $transaction->save();
            
            // Update the order payment status if payment was successful
            if ($paymentResponse['success']) {
                // If the payment is completed immediately (not a redirect-based flow)
                if (($paymentResponse['status'] ?? '') === 'succeeded' || ($paymentResponse['status'] ?? '') === 'COMPLETED') {
                    $this->orderService->updatePaymentStatus($order->id, 'paid', [
                        'transaction_id' => $transaction->transaction_id,
                        'gateway_transaction_id' => $transaction->gateway_transaction_id,
                        'payment_method' => $gatewayCode,
                        'payment_date' => now()->format('Y-m-d H:i:s')
                    ]);
                    
                    // Update order status
                    $this->orderService->updateOrderStatus($order->id, 'processing');
                }
                
                return [
                    'success' => true,
                    'message' => $paymentResponse['message'] ?? 'Payment processed successfully',
                    'transaction_id' => $transaction->transaction_id,
                    'gateway_transaction_id' => $transaction->gateway_transaction_id,
                    'order_number' => $order->order_number,
                    'amount' => $order->total,
                    'currency' => $transaction->currency,
                    'status' => $transaction->status,
                    'checkout_url' => $paymentResponse['checkout_url'] ?? null,
                    'payment_date' => $transaction->processed_at->format('Y-m-d H:i:s')
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $paymentResponse['message'] ?? 'Payment processing failed',
                    'error' => $paymentResponse['error'] ?? null,
                    'error_code' => $paymentResponse['error_code'] ?? null
                ];
            }
        } catch (\Exception $e) {
            Log::error('Payment gateway error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Payment processing failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Process credit card payment
     *
     * @param Order $order
     * @param array $paymentData
     * @return array
     */
    protected function processCreditCardPayment(Order $order, array $paymentData): array
    {
        // Validate credit card data
        if (!isset($paymentData['card_number']) || 
            !isset($paymentData['card_expiry_month']) || 
            !isset($paymentData['card_expiry_year']) || 
            !isset($paymentData['card_cvc'])) {
            
            return [
                'success' => false,
                'message' => 'Missing credit card information'
            ];
        }
        
        // In a real application, this would integrate with a payment gateway
        // For this demo, we'll simulate a successful payment
        
        try {
            // Generate transaction ID
            $transactionId = 'TXN-' . Str::random(16);
            
            // Update order payment status
            $this->orderService->updatePaymentStatus($order->id, 'paid', [
                'transaction_id' => $transactionId,
                'payment_method' => 'credit_card',
                'card_last_four' => substr($paymentData['card_number'], -4),
                'payment_date' => now()->format('Y-m-d H:i:s')
            ]);
            
            // Update order status
            $this->orderService->updateOrderStatus($order->id, 'processing');
            
            return [
                'success' => true,
                'message' => 'Payment processed successfully',
                'transaction_id' => $transactionId,
                'order_number' => $order->order_number,
                'amount' => $order->total,
                'payment_date' => now()->format('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            Log::error('Credit card payment error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Payment processing failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Process PayPal payment
     *
     * @param Order $order
     * @param array $paymentData
     * @return array
     */
    protected function processPayPalPayment(Order $order, array $paymentData): array
    {
        // Validate PayPal data
        if (!isset($paymentData['paypal_transaction_id'])) {
            return [
                'success' => false,
                'message' => 'Missing PayPal transaction ID'
            ];
        }
        
        // In a real application, this would verify the PayPal transaction
        // For this demo, we'll simulate a successful payment
        
        try {
            // Update order payment status
            $this->orderService->updatePaymentStatus($order->id, 'paid', [
                'transaction_id' => $paymentData['paypal_transaction_id'],
                'payment_method' => 'paypal',
                'payment_date' => now()->format('Y-m-d H:i:s')
            ]);
            
            // Update order status
            $this->orderService->updateOrderStatus($order->id, 'processing');
            
            return [
                'success' => true,
                'message' => 'PayPal payment processed successfully',
                'transaction_id' => $paymentData['paypal_transaction_id'],
                'order_number' => $order->order_number,
                'amount' => $order->total,
                'payment_date' => now()->format('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            Log::error('PayPal payment error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Payment processing failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Process bank transfer payment
     *
     * @param Order $order
     * @param array $paymentData
     * @return array
     */
    protected function processBankTransferPayment(Order $order, array $paymentData): array
    {
        // For bank transfers, we'll mark the payment as pending
        // and provide bank details for the customer
        
        try {
            // Update order payment status
            $this->orderService->updatePaymentStatus($order->id, 'pending', [
                'payment_method' => 'bank_transfer',
                'reference_number' => 'BT-' . $order->order_number,
                'instructions_sent' => now()->format('Y-m-d H:i:s')
            ]);
            
            // Bank details (these would be stored in configuration in a real app)
            $bankDetails = [
                'bank_name' => 'CSL Bank',
                'account_name' => 'CSL Certification Platform',
                'account_number' => '1234567890',
                'routing_number' => '987654321',
                'swift_code' => 'CSLBANKXXX',
                'reference' => 'BT-' . $order->order_number
            ];
            
            return [
                'success' => true,
                'message' => 'Bank transfer instructions generated',
                'order_number' => $order->order_number,
                'amount' => $order->total,
                'bank_details' => $bankDetails,
                'instructions' => 'Please transfer the exact amount and include the reference number in your payment description.'
            ];
        } catch (\Exception $e) {
            Log::error('Bank transfer processing error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to process bank transfer instructions: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Process manual payment
     *
     * @param Order $order
     * @param array $paymentData
     * @return array
     */
    protected function processManualPayment(Order $order, array $paymentData): array
    {
        // Validate manual payment data
        if (!isset($paymentData['payment_reference'])) {
            return [
                'success' => false,
                'message' => 'Payment reference is required'
            ];
        }
        
        try {
            // Update order payment status
            $this->orderService->updatePaymentStatus($order->id, 'paid', [
                'transaction_id' => $paymentData['payment_reference'],
                'payment_method' => 'manual',
                'payment_date' => now()->format('Y-m-d H:i:s'),
                'notes' => $paymentData['notes'] ?? 'Manual payment'
            ]);
            
            // Update order status
            $this->orderService->updateOrderStatus($order->id, 'processing');
            
            return [
                'success' => true,
                'message' => 'Manual payment recorded successfully',
                'transaction_id' => $paymentData['payment_reference'],
                'order_number' => $order->order_number,
                'amount' => $order->total,
                'payment_date' => now()->format('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            Log::error('Manual payment error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to record manual payment: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Verify payment status
     *
     * @param string $transactionId
     * @return array
     */
    public function verifyPayment(string $transactionId): array
    {
        // Find transaction with this ID
        $transaction = Transaction::where('transaction_id', $transactionId)
            ->orWhere('gateway_transaction_id', $transactionId)
            ->first();
        
        if (!$transaction) {
            return [
                'success' => false,
                'message' => 'Transaction not found'
            ];
        }
        
        try {
            // Get the payment gateway settings
            $gatewaySettings = PaymentGatewaySetting::where('id', $transaction->payment_gateway_setting_id)->first();
            
            if (!$gatewaySettings) {
                // Fall back to legacy verification method
                return $this->verifyLegacyPayment($transactionId);
            }
            
            // Verify the payment using the gateway
            $gateway = PaymentGatewayFactory::create($transaction->gateway_code, $gatewaySettings);
            
            if (!$gateway) {
                return [
                    'success' => false,
                    'message' => 'Failed to initialize payment gateway'
                ];
            }
            
            $verificationResponse = $gateway->verifyPayment($transaction->gateway_transaction_id);
            
            // Update the transaction status based on verification
            if ($verificationResponse['success'] && ($verificationResponse['status'] === 'succeeded' || $verificationResponse['status'] === 'completed' || $verificationResponse['status'] === 'COMPLETED')) {
                $transaction->status = 'completed';
                $transaction->verified_at = now();
                $transaction->save();
                
                // Update the order if not already paid
                $order = Order::find($transaction->order_id);
                if ($order && $order->payment_status !== 'paid') {
                    $this->orderService->updatePaymentStatus($order->id, 'paid', [
                        'transaction_id' => $transaction->transaction_id,
                        'gateway_transaction_id' => $transaction->gateway_transaction_id,
                        'payment_method' => $transaction->gateway_code,
                        'payment_date' => now()->format('Y-m-d H:i:s')
                    ]);
                    
                    // Update order status
                    $this->orderService->updateOrderStatus($order->id, 'processing');
                }
            }
            
            return [
                'success' => $verificationResponse['success'],
                'message' => $verificationResponse['message'],
                'transaction_id' => $transaction->transaction_id,
                'gateway_transaction_id' => $transaction->gateway_transaction_id,
                'order_id' => $transaction->order_id,
                'payment_status' => $transaction->status,
                'amount' => $transaction->total_amount,
                'currency' => $transaction->currency,
                'payment_date' => $transaction->processed_at ? $transaction->processed_at->format('Y-m-d H:i:s') : null,
                'verification_date' => $transaction->verified_at ? $transaction->verified_at->format('Y-m-d H:i:s') : null
            ];
        } catch (\Exception $e) {
            Log::error('Payment verification error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Payment verification failed: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Verify legacy payment status (for backward compatibility)
     *
     * @param string $transactionId
     * @return array
     */
    protected function verifyLegacyPayment(string $transactionId): array
    {
        // In a real application, this would check with the payment gateway
        // For this demo, we'll simulate a verification process
        
        // Find order with this transaction ID
        $orders = Order::with(['orderItems.product'])->get();
        $matchingOrder = null;
        
        foreach ($orders as $order) {
            $metadata = json_decode($order->metadata ?? '{}', true);
            
            if (isset($metadata['payment_data']['transaction_id']) && 
                $metadata['payment_data']['transaction_id'] === $transactionId) {
                $matchingOrder = $order;
                break;
            }
        }
        
        if (!$matchingOrder) {
            return [
                'success' => false,
                'message' => 'Transaction not found'
            ];
        }
        
        return [
            'success' => true,
            'message' => 'Payment verified',
            'transaction_id' => $transactionId,
            'order_number' => $matchingOrder->order_number,
            'payment_status' => $matchingOrder->payment_status,
            'amount' => $matchingOrder->total,
            'payment_date' => json_decode($matchingOrder->metadata ?? '{}', true)['payment_data']['payment_date'] ?? null
        ];
    }
    
    /**
     * Process refund
     *
     * @param int $orderId
     * @param float $amount
     * @param string $reason
     * @return array
     */
    public function processRefund(int $orderId, float $amount = null, string $reason = ''): array
    {
        $order = $this->orderService->getOrderById($orderId);
        
        if (!$order) {
            return [
                'success' => false,
                'message' => 'Order not found'
            ];
        }
        
        // Check if order is paid
        if ($order->payment_status !== 'paid') {
            return [
                'success' => false,
                'message' => 'Order is not paid, cannot process refund'
            ];
        }
        
        // Check if order is already refunded
        if ($order->payment_status === 'refunded') {
            return [
                'success' => false,
                'message' => 'Order is already refunded'
            ];
        }
        
        // If amount is not specified, refund the full amount
        if ($amount === null) {
            $amount = $order->total;
        }
        
        // Check if refund amount is valid
        if ($amount <= 0 || $amount > $order->total) {
            return [
                'success' => false,
                'message' => 'Invalid refund amount'
            ];
        }
        
        // Find the transaction for this order
        $transaction = Transaction::where('order_id', $order->id)
            ->where('status', 'completed')
            ->first();
        
        if ($transaction && $transaction->gateway_transaction_id) {
            // Process refund through the payment gateway
            return $this->processGatewayRefund($transaction, $amount, $reason);
        } else {
            // Fall back to legacy refund method
            return $this->processLegacyRefund($order, $amount, $reason);
        }
    }
    
    /**
     * Process refund through a payment gateway
     *
     * @param Transaction $transaction
     * @param float $amount
     * @param string $reason
     * @return array
     */
    protected function processGatewayRefund(Transaction $transaction, float $amount, string $reason): array
    {
        try {
            // Get the payment gateway settings
            $gatewaySettings = PaymentGatewaySetting::where('id', $transaction->payment_gateway_setting_id)->first();
            
            if (!$gatewaySettings) {
                return [
                    'success' => false,
                    'message' => 'Payment gateway settings not found'
                ];
            }
            
            // Process the refund using the gateway
            $gateway = PaymentGatewayFactory::create($transaction->gateway_code, $gatewaySettings);
            
            if (!$gateway) {
                return [
                    'success' => false,
                    'message' => 'Failed to initialize payment gateway'
                ];
            }
            
            $refundResponse = $gateway->processRefund($transaction, $amount, $reason);
            
            if ($refundResponse['success']) {
                // Create a refund transaction
                $refundTransaction = new Transaction();
                $refundTransaction->order_id = $transaction->order_id;
                $refundTransaction->environment_id = $transaction->environment_id;
                $refundTransaction->payment_gateway_setting_id = $transaction->payment_gateway_setting_id;
                $refundTransaction->gateway_code = $transaction->gateway_code;
                $refundTransaction->transaction_id = 'REF-' . Str::random(16);
                $refundTransaction->parent_transaction_id = $transaction->transaction_id;
                $refundTransaction->gateway_transaction_id = $refundResponse['refund_id'];
                $refundTransaction->customer_name = $transaction->customer_name;
                $refundTransaction->customer_email = $transaction->customer_email;
                $refundTransaction->total_amount = -$amount; // Negative amount for refunds
                $refundTransaction->currency = $transaction->currency;
                $refundTransaction->description = 'Refund for transaction ' . $transaction->transaction_id . ($reason ? ': ' . $reason : '');
                $refundTransaction->status = 'completed';
                $refundTransaction->type = 'refund';
                $refundTransaction->metadata = json_encode([
                    'original_transaction_id' => $transaction->transaction_id,
                    'reason' => $reason,
                    'refund_data' => $refundResponse
                ]);
                $refundTransaction->processed_at = now();
                $refundTransaction->verified_at = now();
                $refundTransaction->save();
                
                // Update the order
                $order = Order::find($transaction->order_id);
                if ($order) {
                    // Get existing payment data
                    $metadata = json_decode($order->metadata ?? '{}', true);
                    $paymentData = $metadata['payment_data'] ?? [];
                    
                    // Add refund data
                    $paymentData['refund_id'] = $refundTransaction->transaction_id;
                    $paymentData['refund_amount'] = $amount;
                    $paymentData['refund_date'] = now()->format('Y-m-d H:i:s');
                    $paymentData['refund_reason'] = $reason;
                    
                    // Update metadata
                    $metadata['payment_data'] = $paymentData;
                    
                    // Update order payment status
                    $order->update([
                        'payment_status' => 'refunded',
                        'status' => 'refunded',
                        'metadata' => json_encode($metadata),
                        'notes' => $order->notes . "\nRefund processed: $amount. Reason: $reason"
                    ]);
                }
                
                return [
                    'success' => true,
                    'message' => 'Refund processed successfully',
                    'refund_id' => $refundTransaction->transaction_id,
                    'gateway_refund_id' => $refundTransaction->gateway_transaction_id,
                    'original_transaction_id' => $transaction->transaction_id,
                    'order_id' => $transaction->order_id,
                    'amount' => $amount,
                    'currency' => $transaction->currency,
                    'refund_date' => $refundTransaction->processed_at->format('Y-m-d H:i:s')
                ];
            } else {
                return [
                    'success' => false,
                    'message' => $refundResponse['message'] ?? 'Refund processing failed',
                    'error' => $refundResponse['error'] ?? null,
                    'error_code' => $refundResponse['error_code'] ?? null
                ];
            }
        } catch (\Exception $e) {
            Log::error('Refund processing error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to process refund: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Process legacy refund (for backward compatibility)
     *
     * @param Order $order
     * @param float $amount
     * @param string $reason
     * @return array
     */
    protected function processLegacyRefund(Order $order, float $amount, string $reason): array
    {
        // In a real application, this would integrate with a payment gateway
        // For this demo, we'll simulate a successful refund
        
        try {
            // Generate refund ID
            $refundId = 'REF-' . Str::random(16);
            
            // Get existing payment data
            $metadata = json_decode($order->metadata ?? '{}', true);
            $paymentData = $metadata['payment_data'] ?? [];
            
            // Add refund data
            $paymentData['refund_id'] = $refundId;
            $paymentData['refund_amount'] = $amount;
            $paymentData['refund_date'] = now()->format('Y-m-d H:i:s');
            $paymentData['refund_reason'] = $reason;
            
            // Update metadata
            $metadata['payment_data'] = $paymentData;
            
            // Update order payment status
            $order->update([
                'payment_status' => 'refunded',
                'status' => 'refunded',
                'metadata' => json_encode($metadata),
                'notes' => $order->notes . "\nRefund processed: $amount. Reason: $reason"
            ]);
            
            return [
                'success' => true,
                'message' => 'Refund processed successfully',
                'refund_id' => $refundId,
                'order_number' => $order->order_number,
                'amount' => $amount,
                'refund_date' => now()->format('Y-m-d H:i:s')
            ];
        } catch (\Exception $e) {
            Log::error('Refund processing error: ' . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to process refund: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Generate invoice
     *
     * @param int $orderId
     * @return array
     */
    public function generateInvoice(int $orderId): array
    {
        $order = $this->orderService->getOrderById($orderId);
        
        if (!$order) {
            return [
                'success' => false,
                'message' => 'Order not found'
            ];
        }
        
        // Generate invoice number
        $invoiceNumber = 'INV-' . $order->order_number;
        
        // Prepare invoice data
        $invoiceData = [
            'invoice_number' => $invoiceNumber,
            'order_number' => $order->order_number,
            'date' => now()->format('Y-m-d'),
            'due_date' => now()->addDays(30)->format('Y-m-d'),
            'customer' => [
                'name' => $order->customer_name,
                'email' => $order->customer_email,
                'billing_address' => $order->billing_address,
                'shipping_address' => $order->shipping_address
            ],
            'items' => [],
            'subtotal' => $order->subtotal,
            'discount' => $order->discount,
            'tax' => $order->tax,
            'total' => $order->total,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method
        ];
        
        // Add items to invoice
        foreach ($order->orderItems as $item) {
            $invoiceData['items'][] = [
                'name' => $item->name,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'total' => $item->total
            ];
        }
        
        // In a real application, this would generate a PDF invoice
        // For this demo, we'll just return the invoice data
        
        return [
            'success' => true,
            'message' => 'Invoice generated successfully',
            'invoice_data' => $invoiceData
        ];
    }
    
    /**
     * Get payment methods
     *
     * @param int|null $environmentId
     * @return array
     */
    public function getPaymentMethods(?int $environmentId = null): array
    {
        // Get active payment gateways from the database
        $query = PaymentGatewaySetting::where('status', true);
        
        // Filter by environment if specified
        if ($environmentId !== null) {
            $query->where(function($q) use ($environmentId) {
                $q->where('environment_id', $environmentId)
                  ->orWhereNull('environment_id');
            });
        }
        
        $gatewaySettings = $query->get();
        
        $paymentMethods = [];
        
        // Add configured payment gateways
        foreach ($gatewaySettings as $setting) {
            // Initialize the gateway to get its configuration
            $gateway = PaymentGatewayFactory::create($setting->gateway_code, $setting);
            
            if ($gateway) {
                $config = $gateway->getConfig();
                
                $paymentMethods[] = [
                    'id' => $setting->gateway_code,
                    'name' => $config['name'] ?? $setting->name,
                    'description' => $config['description'] ?? $setting->description,
                    'is_enabled' => $setting->status,
                    'environment_id' => $setting->environment_id,
                    'mode' => $setting->mode,
                    'supports' => $config['supports'] ?? [],
                    'countries' => $config['countries'] ?? [],
                    'currencies' => $config['currencies'] ?? [],
                    'requires_redirect' => $config['redirect_based'] ?? false,
                    'client_side' => $config['client_side'] ?? false,
                    'config' => $setting->is_default ? [
                        'publishable_key' => $setting->getSetting('publishable_key'),
                        'client_id' => $setting->getSetting('client_id'),
                        'webhook_url' => $setting->webhook_url
                    ] : []
                ];
            }
        }
        
        // Add legacy payment methods if no gateways are configured
        if (empty($paymentMethods)) {
            $paymentMethods = [
                [
                    'id' => 'credit_card',
                    'name' => 'Credit Card',
                    'description' => 'Pay with Visa, Mastercard, or American Express',
                    'is_enabled' => true
                ],
                [
                    'id' => 'paypal',
                    'name' => 'PayPal',
                    'description' => 'Pay with your PayPal account',
                    'is_enabled' => true
                ],
                [
                    'id' => 'bank_transfer',
                    'name' => 'Bank Transfer',
                    'description' => 'Pay directly from your bank account',
                    'is_enabled' => true
                ],
                [
                    'id' => 'manual',
                    'name' => 'Manual Payment',
                    'description' => 'Pay via other methods and provide a reference number',
                    'is_enabled' => true
                ]
            ];
        }
        
        return $paymentMethods;
    }
}
