<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Transaction;
use App\Models\Environment;
use App\Models\PaymentGatewaySetting;
use App\Services\PaymentGateways\PaymentGatewayFactory;
use App\Services\PaymentGateways\PaymentGatewayInterface;
use App\Services\Commission\CommissionService;
use App\Services\Tax\TaxZoneService;
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
     * @var CommissionService
     */
    protected $commissionService;
    
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
     * @var array
     */
    protected $environmentCache = [];

    /**
     * Constructor for PaymentService
     * 
     * @param OrderService $orderService
     * @param PaymentGatewayFactory $gatewayFactory
     * @param CommissionService $commissionService
     * @param TaxZoneService $taxZoneService
     */
    public function __construct(
        OrderService $orderService, 
        PaymentGatewayFactory $gatewayFactory, 
        CommissionService $commissionService,
        TaxZoneService $taxZoneService
    ) {
        $this->orderService = $orderService;
        $this->commissionService = $commissionService;
        $this->gatewayFactory = $gatewayFactory;
        $this->taxZoneService = $taxZoneService;
    }



    /***
     * @param string|null|int $environment_id
     * @return App\Models\Environment
     */
    public function getEnvironmentById($environment_id): Environment
    {
        return Environment::find($environment_id);
    }

    /**
     * Create a payment for an order with environment-specific configuration
     *
     * @param string $orderId
     * @param string $paymentMethod
     * @param array $paymentData
     * @param string|null $environment
     * @return array
     */
    public function createPayment(string $orderId, string $paymentMethod, array $paymentData = [], ?string $environment = null): array
    {
        Log::info("createPayment method called with orderId: $orderId, paymentMethod: $paymentMethod, environment: $environment");

        
        $environmentId = session('current_environment_id');
        Log::info('Found environement Id on createPayment', [
            "env" => $environmentId
        ]);
        
        try {
            // Get the order
            $order = $this->orderService->getOrderById($orderId);
            if (!$order) {
                return [
                    'success' => false,
                    'message' => 'Order not found'
                ];
            }
            
            // Check if a transaction already exists for this order
            $existingTransaction = Transaction::where('order_id', $order->id)
            ->where("status", "pending")
            ->first();

            if ($existingTransaction) {
                Log::info('Found existing transaction for order', [
                    'order_id' => $order->id,
                    'transaction_id' => $existingTransaction->transaction_id
                ]);

                //update the transaction with a new transaction_id 'TXN_' . Str::uuid(),
                $existingTransaction->update([
                    'transaction_id' => 'TXN_' . Str::uuid(),
                    'payment_method'=> $paymentMethod,
                    'environment_id'=> $environmentId
                ]);

                // Initialize the payment gateway with environment-specific settings
                $gateway = $this->initializeGateway($paymentMethod, $environment);
                if (!$gateway['success']) {
                        return $gateway;
                }
                
                $this->currentGateway = $gateway['gateway'];
                
                // Create the payment with the gateway using existing transaction
                $response = $this->currentGateway->createPayment($existingTransaction, $paymentData);
                
                if (!$response['success']) {
                        return $response;
                }
                
                $response['transaction'] = $existingTransaction;

                return $response;
            }

            // Get environment details for tax calculation
            $environment = Environment::find($environmentId);
            $countryCode = $environment->country_code ?? $order->billing_country ?? 'CM';
            $stateCode = $environment->state_code ?? $order->billing_state ?? '';
            
            // Create transaction data array
            $transactionData = [
                'order_id' => $order->id,
                'customer_id' => $order->user_id,
                'transaction_id' => 'TXN_' . Str::uuid(),
                'payment_method' => $paymentMethod,
                'currency' => $order->currency ?? 'USD',
                'status' => 'pending',
                'description' => 'Payment for Order #' . $order->order_number,
                'amount' => $order->total_amount,
                'country_code' => $countryCode,
                'state_code' => $stateCode,
                'customer_name' => $order->billing_name,
                'customer_email' => $order->billing_email,
            ];
            
            // Create the transaction record
            $transaction = Transaction::create($transactionData);

            if($transaction) {
                Log::info("Transaction was created with", [
                    'transaction_id' => $transaction->transaction_id
                ]);
            }
            
            // Use new commission calculation method - commission is already included in product price
            $transactionAmounts = $this->commissionService->calculateTransactionAmountsWithCommissionIncluded(
                $transaction->amount, 
                $environmentId,
                $order
            );
            
            // Update transaction with extracted commission, tax information and total amount
            $transaction->update([
                'fee_amount' => $transactionAmounts['fee_amount'], // Commission extracted from product price
                'tax_zone' => $transactionAmounts['tax_zone'],
                'tax_rate' => $transactionAmounts['tax_rate'],
                'tax_amount' => $transactionAmounts['tax_amount'],
                'total_amount' => $transactionAmounts['total_amount']
            ]);
            
            Log::info('Transaction amounts calculated with commission included in product price', [
                'transaction_id' => $transaction->transaction_id,
                'original_amount' => $transaction->amount,
                'extracted_commission' => $transactionAmounts['fee_amount'],
                'tax_amount' => $transactionAmounts['tax_amount'],
                'total_amount' => $transactionAmounts['total_amount']
            ]);
            
            // Log the commission and tax application
            Log::info('Applied commission and tax to transaction for order', [
                'order_id' => $order->id,
                'base_amount' => $transaction->amount,
                'fee_amount' => $transaction->fee_amount,
                'tax_amount' => $transaction->tax_amount,
                'tax_rate' => $transaction->tax_rate,
                'tax_zone' => $transaction->tax_zone,
                'total_amount' => $transaction->total_amount
            ]);

            // Initialize the payment gateway with environment-specific settings
            $gateway = $this->initializeGateway($paymentMethod, $environment);
            if (!$gateway['success']) {
                Log::warning("Payment Gateway initialization failed", [
                    'success' => false,
                    'message' => "Payment gateway '$paymentMethod' not supported"
                ]);
                return $gateway;
            }
            
            $this->currentGateway = $gateway['gateway'];
            
            // Create the payment with the gateway
            $response = $this->currentGateway->createPayment($transaction, $paymentData);
            
            if (!$response['success']) {
                Log::warning("Payment creation failed", [
                    'success' => false,
                    'message' => "Payment gateway '$paymentMethod' not supported"
                ]);
                return $response;
            }

            if (isset($response['payment_intent_id'])) {
                $transaction->gateway_transaction_id = $response['payment_intent_id'];
                $transaction->save();
            }



            $transaction->refresh();
            $response['transaction'] = $transaction;
            return $response;
            
        } catch (\Exception $e) {
            Log::error('Payment creation failed: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Payment creation failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Initialize a payment gateway with environment-specific configuration
     *
     * @param string $gatewayCode
     * @param string|null $environment
     * @return array
     */
    protected function initializeGateway(string $gatewayCode, ?string $environment = null): array
    {
        try {
            // Get environment ID based on environment name
            $environmentId = session('current_environment_id');
            Log::info('Enviroment id in payment service '.$environmentId);
            
            // Get gateway settings for the specified environment
            $gatewaySettings = $this->getGatewaySettings($gatewayCode, $environmentId);
            
            if (!$gatewaySettings) {
                Log::warning("Payment Gateway settings retrieval failed", [
                    'success' => false,
                    'message' => "Payment gateway '$gatewayCode' not configured for the specified environment"
                ]);
                return [
                    'success' => false,
                    'message' => "Payment gateway '$gatewayCode' not configured for the specified environment"
                ];
            }
            
            // Create and initialize the gateway
            $gateway = $this->gatewayFactory->create($gatewayCode, $gatewaySettings);
            
            if (!$gateway) {
                Log::warning("Payment Gateway creation failed", [
                    'success' => false,
                    'message' => "Payment gateway '$gatewayCode' not supported"
                ]);
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
     * Process a payment with environment-specific configuration
     * Get the payment gateway settings for a specific gateway and environment
     *
     * @param string $gatewayCode
     * @param int|null $environmentId
     * @return PaymentGatewaySetting|null
     */
    private function getGatewaySettings(string $gatewayCode, ?int $environmentId): ?PaymentGatewaySetting
    {
        // If environmentId is null, try to get it from the session
        if ($environmentId === null) {
            $environmentId = session('current_environment_id');
        }
        
        return PaymentGatewaySetting::where('code', $gatewayCode)
            ->where(function ($query) use ($environmentId) {
                $query->where('environment_id', $environmentId)
                    ->orWhereNull('environment_id');
            })
            ->first();
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
        Log::info('Processing payment for order ' . $paymentData['payment_method']);
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
            case 'manual':
                return $this->processManualPayment($order, $paymentData);
                
            case 'stripe':
                return $this->processGatewayPayment($order, 'stripe', $paymentData);
                
            case 'lygos':
                return $this->processGatewayPayment($order, 'lygos', $paymentData);
            
            case 'monetbill':
                return $this->processGatewayPayment($order, 'monetbill', $paymentData);
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

        $environmentId = session('current_environment_id');

        Log::info('Processing payment for order ' . $order->id . ' using ' . $gatewayCode);
        try {
            // Get the payment gateway settings
            $gatewaySettings = PaymentGatewaySetting::where('code', $gatewayCode)
                ->where('environment_id', $paymentData['environment_id'] ?? null)
                ->where('status', true)
                ->first();
            
            if (!$gatewaySettings) {
                return [
                    'success' => false,
                    'message' => 'Payment gateway settings not found'
                ];
            }

            $environment = Environment::find($environmentId);

            // Check if a transaction already exists for this order
            $existingTransaction = Transaction::where('order_id', $order->id)
                ->whereIn("status", ["pending", "failed", "processing", "cancelled"])
                ->first();

            if ($existingTransaction) {
                Log::info('Found existing transaction for order', [
                    'order_id' => $order->id,
                    'transaction_id' => $existingTransaction->transaction_id
                ]);

                //update the transaction with a new transaction_id 'TXN_' . Str::uuid(),
                $existingTransaction->update([
                    'transaction_id' => 'TXN_' . Str::uuid(),
                    'payment_method' => $gatewayCode,
                    'environment_id' => $environmentId,
                    "status" => "pending"
                ]);

                // Initialize the payment gateway with environment-specific settings
                $gateway = $this->initializeGateway($gatewayCode, $environment->name);
                if (!$gateway['success']) {
                    return $gateway;
                }

                $this->currentGateway = $gateway['gateway'];

                // Create the payment with the gateway using existing transaction
                $response = $this->currentGateway->createPayment($existingTransaction, $paymentData);

                if (!$response['success']) {
                    return $response;
                }

                $response['transaction'] = $existingTransaction;

                return $response;
            }
            
            // Create a new transaction if one doesn't exist
            if (!$existingTransaction) {
                Log::info('Creating new transaction for order', ['order_id' => $order->id]);
                
                $transaction = new Transaction();
                $transaction->order_id = $order->id;
                $transaction->environment_id = $paymentData['environment_id'] ?? $gatewaySettings->environment_id;
                $transaction->payment_gateway_setting_id = $gatewaySettings->id;
                $transaction->payment_method = $gatewayCode;
                $transaction->transaction_id = 'TXN-' . Str::random(16);
                
                // Validate customer name and email
                $transaction->customer_name = !empty($order->billing_name) ? $order->billing_name : 'Guest Customer';
                
                // Only set customer email if it's valid
                if (!empty($order->billing_email) && filter_var($order->billing_email, FILTER_VALIDATE_EMAIL)) {
                    $transaction->customer_email = $order->billing_email;
                    Log::info('Valid customer email found for order ' . $order->id, ['email' => $order->billing_email]);
                } else {
                    $transaction->customer_email = null;
                    Log::info('Invalid or missing customer email for order ' . $order->id, ['raw_email' => $order->billing_email ?? 'null']);
                }
                
                // Set base amount (without commission)
                $transaction->amount = $order->total_amount ?? $order->total; // Fallback if total_amount is not set
                $transaction->currency = $order->currency ?? 'USD';
                $transaction->description = 'Payment for order #' . $order->order_number;
                $transaction->status = 'pending';
                $transaction->customer_id = $order->user_id;
                
                // Use new commission calculation method - commission is already included in product price
                $transactionAmounts = $this->commissionService->calculateTransactionAmountsWithCommissionIncluded(
                    $transaction->amount, 
                    $environmentId,
                    $order
                );
                
                // Update transaction with extracted commission, tax information and total amount
                $transaction->fee_amount = $transactionAmounts['fee_amount']; // Commission extracted from product price
                $transaction->tax_zone = $transactionAmounts['tax_zone'];
                $transaction->tax_rate = $transactionAmounts['tax_rate'];
                $transaction->tax_amount = $transactionAmounts['tax_amount'];
                $transaction->total_amount = $transactionAmounts['total_amount'];
                
                Log::info('Transaction amounts calculated with commission included in product price (continue payment)', [
                    'transaction_id' => $transaction->transaction_id,
                    'order_id' => $order->id,
                    'original_amount' => $transaction->amount,
                    'extracted_commission' => $transactionAmounts['fee_amount'],
                    'tax_amount' => $transactionAmounts['tax_amount'],
                    'total_amount' => $transactionAmounts['total_amount']
                ]);
                
                $transaction->save();
            } else {
                // Use the existing transaction
                $transaction = $existingTransaction;
                
                // Update the transaction with new payment details
                $transaction->transaction_id = 'TXN_' . Str::uuid();
                $transaction->payment_method = $gatewayCode;
                $transaction->environment_id = $environmentId;
                $transaction->status = "pending";
                $transaction->save();
                
                Log::info('Updated existing transaction for order', [
                    'order_id' => $order->id,
                    'transaction_id' => $transaction->transaction_id
                ]);
            }
            
            // Initialize the payment gateway with environment-specific settings
            $gateway = $this->initializeGateway($gatewayCode, $environment->name);
            if (!$gateway['success']) {
                return $gateway;
            }
            
            $this->currentGateway = $gateway['gateway'];
            
            // Create the payment with the gateway
            $paymentResponse = $this->currentGateway->createPayment($transaction, $paymentData);
            
            if (!$paymentResponse['success']) {
                return $paymentResponse;
            }
            
            // Update the transaction with the gateway response
            $transaction->gateway_transaction_id = $paymentResponse['transaction_id'] ?? null;
            $transaction->status = $paymentResponse['success'] ? ($paymentResponse['status'] ?? 'pending') : 'failed';
            $transaction->gateway_response = json_encode($paymentResponse);
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
                
                $response = [
                    'success' => true,
                    'message' => $paymentResponse['message'] ?? 'Payment processed successfully',
                    'transaction_id' => $transaction->transaction_id,
                    'gateway_transaction_id' => $transaction->gateway_transaction_id,
                    'order_number' => $order->order_number,
                    'amount' => $order->total,
                    'currency' => $transaction->currency,
                    'status' => $transaction->status,
                    'checkout_url' => $paymentResponse['checkout_url'] ?? null,
                    'payment_date' => $transaction->paid_at ? $transaction->paid_at->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s'),
                    'payment_type' => $gatewayCode,
                    'client_secret' => $paymentResponse['client_secret'] ?? null,
                    'publishable_key' => $paymentResponse['publishable_key'] ?? null
                ];
                
                $response['transaction'] = $transaction;
                return $response;
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
      return [
        'success' => false,
        'message' => 'Credit card payment is not supported'
      ];
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
      return [
        'success' => false,
        'message' => 'PayPal payment is not supported'
      ];
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
      return [
        'success' => false,
        'message' => 'Bank transfer payment is not supported'
      ];
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
            $gateway = PaymentGatewayFactory::create($transaction->payment_method, $gatewaySettings);
            
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
                        'payment_method' => $transaction->payment_method,
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
                'payment_date' => $transaction->paid_at ? $transaction->paid_at->format('Y-m-d H:i:s') : null,
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
            $gateway = PaymentGatewayFactory::create($transaction->payment_method, $gatewaySettings);
            
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
                $refundTransaction->payment_method = $transaction->payment_method;
                $refundTransaction->transaction_id = 'REF-' . Str::random(16);
                $refundTransaction->parent_transaction_id = $transaction->transaction_id;
                $refundTransaction->gateway_transaction_id = $refundResponse['refund_id'];
                $refundTransaction->customer_name = $transaction->customer_name;
                $refundTransaction->customer_email = $transaction->customer_email;
                $refundTransaction->currency = $transaction->currency;
                $refundTransaction->description = 'Refund for transaction ' . $transaction->transaction_id . ($reason ? ': ' . $reason : '');
                $refundTransaction->status = 'completed';
                $refundTransaction->type = 'refund';
                
                // For refunds, we need to calculate the proportional fee and tax amounts
                // based on the original transaction's commission rate
                $refundTransaction->amount = -$amount; // Base amount as negative for refunds
                
                // If the original transaction had commission applied, apply proportional commission to the refund
                if ($transaction->fee_amount && $transaction->tax_amount && $transaction->amount > 0) {
                    // Calculate the proportion of the refund to the original transaction
                    $proportion = $amount / $transaction->amount;
                    
                    // Apply the same proportion to the fee and tax
                    $refundTransaction->fee_amount = -($transaction->fee_amount * $proportion);
                    $refundTransaction->tax_amount = -($transaction->tax_amount * $proportion);
                    $refundTransaction->total_amount = $refundTransaction->amount + $refundTransaction->fee_amount + $refundTransaction->tax_amount;
                    
                    Log::info('Applied proportional commission to refund transaction', [
                        'original_transaction_id' => $transaction->transaction_id,
                        'refund_transaction_id' => $refundTransaction->transaction_id,
                        'refund_amount' => $amount,
                        'proportion' => $proportion,
                        'refund_fee' => $refundTransaction->fee_amount,
                        'refund_tax' => $refundTransaction->tax_amount,
                        'refund_total' => $refundTransaction->total_amount
                    ]);
                } else {
                    // If no commission on original, just set total amount equal to refund amount
                    $refundTransaction->total_amount = $refundTransaction->amount;
                    
                    Log::info('No commission applied to refund transaction', [
                        'original_transaction_id' => $transaction->transaction_id,
                        'refund_transaction_id' => $refundTransaction->transaction_id,
                        'refund_amount' => $amount
                    ]);
                }
                $refundTransaction->metadata = json_encode([
                    'original_transaction_id' => $transaction->transaction_id,
                    'reason' => $reason,
                    'refund_data' => $refundResponse
                ]);
                $refundTransaction->refunded_at = now();
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
                    'refund_date' => $refundTransaction->refunded_at ? $refundTransaction->refunded_at->format('Y-m-d H:i:s') : now()->format('Y-m-d H:i:s')
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
        if (!$environmentId) {
            return [];
        }
        
        $gateways = PaymentGatewaySetting::where('environment_id', $environmentId)
            ->where('active', true)
            ->get()
            ->mapWithKeys(function ($setting) {
                return [$setting->gateway_name => $setting->gateway_name];
            })
            ->toArray();
        
        return $gateways;
    }
    
    /**
     * Process a successful payment callback from a payment gateway
     *
     * @param string $gateway The payment gateway name
     * @param string $transactionId The transaction ID
     * @param int $environmentId The environment ID
     * @param array $callbackData The callback data received from the payment gateway
     * @return bool True if processing was successful, false otherwise
     */
    public function processSuccessCallback(string $gateway, string $transactionId, int $environmentId, array $callbackData): bool
    {
        try {
            // Find the transaction using smart lookup that handles cross-environment supported plan transactions
            $transaction = $this->findTransactionForCallback($transactionId, $environmentId);
            
            if (!$transaction) {
                Log::error('Transaction not found for success callback', [
                    'gateway' => $gateway,
                    'transaction_id' => $transactionId,
                    'environment_id' => $environmentId
                ]);
                return false;
            }
            
            // Update the transaction status
            $transaction->status = Transaction::STATUS_COMPLETED;
            $transaction->gateway_status = 'completed';
            $transaction->notes = 'Payment completed via ' . $gateway;
            $transaction->paid_at = now();
            $transaction->save();
            
            // Process any related records (orders, subscriptions, etc.)
            $this->processRelatedRecords($transaction);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Error processing payment success callback in service', [
                'error' => $e->getMessage(),
                'gateway' => $gateway,
                'transaction_id' => $transactionId
            ]);
            return false;
        }
    }
    
    /**
     * Process a failed payment callback from a payment gateway
     *
     * @param string $gateway The payment gateway name
     * @param string $transactionId The transaction ID
     * @param int $environmentId The environment ID
     * @param array $callbackData The callback data received from the payment gateway
     * @return bool True if processing was successful, false otherwise
     */
    public function processFailureCallback(string $gateway, string $transactionId, int $environmentId, array $callbackData): bool
    {
        try {
            // Find the transaction using smart lookup that handles cross-environment supported plan transactions
            $transaction = $this->findTransactionForCallback($transactionId, $environmentId);
            
            if (!$transaction) {
                Log::error('Transaction not found for failure callback', [
                    'gateway' => $gateway, 
                    'transaction_id' => $transactionId,
                    'environment_id' => $environmentId
                ]);
                return false;
            }
            
            // Update the transaction status
            $transaction->status = Transaction::STATUS_FAILED;
            $transaction->gateway_status = 'failed';
            $transaction->notes = 'Payment failed via ' . $gateway;
            $transaction->save();
            
            return true;
        } catch (\Exception $e) {
            Log::error('Error processing payment failure callback in service', [
                'error' => $e->getMessage(),
                'gateway' => $gateway,
                'transaction_id' => $transactionId
            ]);
            return false;
        }
    }

    /**
     * Process a cancelled payment callback from a payment gateway
     *
     * @param string $gateway The payment gateway name
     * @param string $transactionId The transaction ID
     * @param int $environmentId The environment ID
     * @param array $callbackData The callback data received from the payment gateway
     * @return bool True if processing was successful, false otherwise
     */
    public function processCancelledCallback(string $gateway, string $transactionId, int $environmentId, array $callbackData): bool
    {
        try {
            // Find the transaction using smart lookup that handles cross-environment supported plan transactions
            $transaction = $this->findTransactionForCallback($transactionId, $environmentId);
            
            if (!$transaction) {
                Log::error('Transaction not found for cancelled callback', [
                    'gateway' => $gateway,
                    'transaction_id' => $transactionId,
                    'environment_id' => $environmentId
                ]);
                return false;
            }
            
            // Update the transaction status
            $transaction->status = Transaction::STATUS_CANCELLED;
            $transaction->gateway_status = 'cancelled';
            $transaction->notes = 'Payment cancelled via ' . $gateway;
            $transaction->save();
            
            return true;
        } catch (\Exception $e) {
            Log::error('Error processing payment cancelled callback in service', [
                'error' => $e->getMessage(),
                'gateway' => $gateway,
                'transaction_id' => $transactionId
            ]);
            return false;
        }
    }

    
    /**
     * Process any records related to a transaction (orders, subscriptions, etc.)
     *
     * @param Transaction $transaction
     * @return void
     */
    protected function processRelatedRecords(Transaction $transaction): void
    {



        // Update related order if exists
        $order = Order::where('id', $transaction->transaction_id)->first();
        if ($order) event(new \App\Events\OrderCompleted($order));
        
        // Process subscriptions or other related records
        // Additional logic can be added here as needed
    }

    /**
     * Find transaction for callback with smart lookup logic
     * Handles cross-environment transactions for supported plans
     * 
     * @param string $transactionId
     * @param int $environment_id
     * @return Transaction|null
     */
    private function findTransactionForCallback($transactionId, $environment_id)
    {
        // First, try environment-specific lookup (existing behavior)
        $transaction = Transaction::where("transaction_id", $transactionId)
            ->where("environment_id", $environment_id)
            ->where("status", Transaction::STATUS_PENDING)
            ->whereHas("paymentGatewaySetting")
            ->first();

        if ($transaction) {
            return $transaction;
        }

        // If not found, try global lookup for supported plan transactions
        // IMPORTANT: Use withoutGlobalScopes to bypass EnvironmentScope for cross-environment lookup
        $globalTransaction = Transaction::withoutGlobalScopes()
            ->where("transaction_id", $transactionId)
            ->where("status", Transaction::STATUS_PENDING)
            ->whereHas("paymentGatewaySetting")
            ->first();

        if ($globalTransaction) {
            // Check if this is a supported plan transaction using basic detection
            $isLikelySupportedPlan = $this->isLikelySupportedPlanTransaction($globalTransaction);
            
            if ($isLikelySupportedPlan) {
                Log::info('PaymentService: Supported plan transaction found with global lookup', [
                    'transaction_id' => $transactionId,
                    'callback_environment_id' => $environment_id,
                    'transaction_environment_id' => $globalTransaction->environment_id
                ]);
                return $globalTransaction;
            }
        }

        return null;
    }

    /**
     * Basic check to identify likely supported plan transactions
     * Used by PaymentService to avoid circular dependency
     * 
     * @param Transaction $transaction
     * @return bool
     */
    private function isLikelySupportedPlanTransaction($transaction): bool
    {
        if (!$transaction) {
            return false;
        }

        // Check transaction description for supported plan keywords
        if ($transaction->description && stripos($transaction->description, 'supported plan') !== false) {
            return true;
        }

        // Check if transaction amount matches supported plan pricing ($177.00)
        if ($transaction->total_amount == 177.00) {
            return true;
        }

        // Check transaction notes for supported plan indicators
        if ($transaction->notes && stripos($transaction->notes, 'supported') !== false) {
            return true;
        }

        return false;
    }
}
