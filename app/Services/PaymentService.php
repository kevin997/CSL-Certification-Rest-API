<?php

namespace App\Services;

use App\Models\Order;
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
                
            default:
                return [
                    'success' => false,
                    'message' => 'Unsupported payment method'
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
     * @return array
     */
    public function getPaymentMethods(): array
    {
        // In a real application, this would be configured in the database or config
        return [
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
}
