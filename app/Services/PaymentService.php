<?php

namespace App\Services;

use App\Events\OrderCompleted;
use App\Models\Environment;
use App\Models\InstructorCommission;
use App\Models\Invoice;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentGatewaySetting;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Services\Commission\CommissionService;
use App\Services\PaymentGateways\PaymentGatewayFactory;
use App\Services\PaymentGateways\PaymentGatewayInterface;
use App\Services\Payments\RefundService;
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
     * @var EnvironmentPaymentConfigService
     */
    protected $environmentPaymentConfigService;

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
     */
    public function __construct(
        OrderService $orderService,
        PaymentGatewayFactory $gatewayFactory,
        CommissionService $commissionService,
        TaxZoneService $taxZoneService,
        EnvironmentPaymentConfigService $environmentPaymentConfigService
    ) {
        $this->orderService = $orderService;
        $this->commissionService = $commissionService;
        $this->gatewayFactory = $gatewayFactory;
        $this->taxZoneService = $taxZoneService;
        $this->environmentPaymentConfigService = $environmentPaymentConfigService;
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
     */
    public function createPayment(string $orderId, string $paymentMethod, array $paymentData = [], ?string $environment = null): array
    {
        Log::info("createPayment method called with orderId: $orderId, paymentMethod: $paymentMethod, environment: $environment");

        $environmentId = session('current_environment_id');

        // Get effective environment ID (routes to the centralized environment if enabled)
        $effectiveEnvironmentId = $this->environmentPaymentConfigService->getEffectiveEnvironmentId($environmentId);

        if ($effectiveEnvironmentId !== $environmentId) {
            Log::info('Using centralized payment gateway in createPayment', [
                'original_environment_id' => $environmentId,
                'effective_environment_id' => $effectiveEnvironmentId,
                'order_id' => $orderId,
            ]);
        }

        Log::info('Found environement Id on createPayment', [
            'env' => $environmentId,
            'effective_env' => $effectiveEnvironmentId,
        ]);

        try {
            // Get the order
            $order = $this->orderService->getOrderById($orderId);
            if (! $order) {
                return [
                    'success' => false,
                    'message' => 'Order not found',
                ];
            }

            // Check if a transaction already exists for this order
            $existingTransaction = Transaction::where('order_id', $order->id)
                ->where('status', 'pending')
                ->first();

            if ($existingTransaction) {
                Log::info('Found existing transaction for order', [
                    'order_id' => $order->id,
                    'transaction_id' => $existingTransaction->transaction_id,
                ]);

                // update the transaction with a new transaction_id 'TXN_' . Str::uuid(),
                $existingTransaction->update([
                    'transaction_id' => 'TXN_'.Str::uuid(),
                    'payment_method' => $paymentMethod,
                    'environment_id' => $effectiveEnvironmentId,
                ]);

                // Initialize the payment gateway with environment-specific settings
                $gateway = $this->initializeGateway($paymentMethod, $environment);
                if (! $gateway['success']) {
                    return $gateway;
                }

                $this->currentGateway = $gateway['gateway'];

                // Create the payment with the gateway using existing transaction
                $response = $this->currentGateway->createPayment($existingTransaction, $paymentData);

                if (! $response['success']) {
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
                'transaction_id' => 'TXN_'.Str::uuid(),
                'payment_method' => $paymentMethod,
                'currency' => $order->currency ?? 'USD',
                'status' => 'pending',
                'description' => 'Payment for Order #'.$order->order_number,
                'amount' => $order->total_amount,
                'country_code' => $countryCode,
                'state_code' => $stateCode,
                'customer_name' => $order->billing_name,
                'customer_email' => $order->billing_email,
                'purpose' => (($order->total_amount ?? 0) == 0 ? Transaction::PURPOSE_FREE_ENROLLMENT : Transaction::PURPOSE_COURSE_SALE),
                'source_type' => 'order',
                'source_id' => $order->id,
                'expected_amount' => $order->total_amount,
                'expected_currency' => $order->currency ?? 'USD',
                'platform_fee_amount' => 0,
            ];

            // Create the transaction record
            $transaction = Transaction::create($transactionData);

            if ($transaction) {
                Log::info('Transaction was created with', [
                    'transaction_id' => $transaction->transaction_id,
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
                'total_amount' => $transactionAmounts['total_amount'],
            ]);

            Log::info('Transaction amounts calculated with commission included in product price', [
                'transaction_id' => $transaction->transaction_id,
                'original_amount' => $transaction->amount,
                'extracted_commission' => $transactionAmounts['fee_amount'],
                'tax_amount' => $transactionAmounts['tax_amount'],
                'total_amount' => $transactionAmounts['total_amount'],
            ]);

            // Log the commission and tax application
            Log::info('Applied commission and tax to transaction for order', [
                'order_id' => $order->id,
                'base_amount' => $transaction->amount,
                'fee_amount' => $transaction->fee_amount,
                'tax_amount' => $transaction->tax_amount,
                'tax_rate' => $transaction->tax_rate,
                'tax_zone' => $transaction->tax_zone,
                'total_amount' => $transaction->total_amount,
            ]);

            // Initialize the payment gateway with environment-specific settings
            $gateway = $this->initializeGateway($paymentMethod, $environment);
            if (! $gateway['success']) {
                Log::warning('Payment Gateway initialization failed', [
                    'success' => false,
                    'message' => "Payment gateway '$paymentMethod' not supported",
                ]);

                return $gateway;
            }

            $this->currentGateway = $gateway['gateway'];

            // Create the payment with the gateway
            $response = $this->currentGateway->createPayment($transaction, $paymentData);

            if (! $response['success']) {
                Log::warning('Payment creation failed', [
                    'success' => false,
                    'message' => "Payment gateway '$paymentMethod' not supported",
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
            Log::error('Payment creation failed: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Payment creation failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Initialize a payment gateway with environment-specific configuration
     */
    protected function initializeGateway(string $gatewayCode, ?string $environment = null): array
    {
        try {
            // Get environment ID based on environment name
            $environmentId = session('current_environment_id');

            // Get effective environment ID (routes to the centralized environment if enabled)
            $effectiveEnvironmentId = $this->environmentPaymentConfigService->getEffectiveEnvironmentId($environmentId);

            if ($effectiveEnvironmentId !== $environmentId) {
                Log::info('Using centralized payment gateway in initializeGateway', [
                    'original_environment_id' => $environmentId,
                    'effective_environment_id' => $effectiveEnvironmentId,
                    'gateway_code' => $gatewayCode,
                ]);
            }

            Log::info('Environment id in payment service', [
                'environment_id' => $environmentId,
                'effective_environment_id' => $effectiveEnvironmentId,
            ]);

            // Get gateway settings using effective environment ID
            $gatewaySettings = $this->getGatewaySettings($gatewayCode, $effectiveEnvironmentId);

            if (! $gatewaySettings) {
                Log::warning('Payment Gateway settings retrieval failed', [
                    'success' => false,
                    'message' => "Payment gateway '$gatewayCode' not configured for the specified environment",
                    'environment_id' => $environmentId,
                    'effective_environment_id' => $effectiveEnvironmentId,
                ]);

                return [
                    'success' => false,
                    'message' => "Payment gateway '$gatewayCode' not configured for the specified environment",
                ];
            }

            // Create and initialize the gateway
            $gateway = $this->gatewayFactory->create($gatewayCode, $gatewaySettings);

            if (! $gateway) {
                Log::warning('Payment Gateway creation failed', [
                    'success' => false,
                    'message' => "Payment gateway '$gatewayCode' not supported",
                ]);

                return [
                    'success' => false,
                    'message' => "Payment gateway '$gatewayCode' not supported",
                ];
            }

            return [
                'success' => true,
                'gateway' => $gateway,
                'settings' => $gatewaySettings,
            ];
        } catch (\Exception $e) {
            Log::error('Gateway initialization failed: '.$e->getMessage(), [
                'environment_id' => $environmentId ?? null,
                'gateway_code' => $gatewayCode,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Gateway initialization failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Process a payment with environment-specific configuration
     * Get the payment gateway settings for a specific gateway and environment
     */
    private function getGatewaySettings(string $gatewayCode, ?int $environmentId): ?PaymentGatewaySetting
    {
        // If environmentId is null, try to get it from the session
        if ($environmentId === null) {
            $environmentId = session('current_environment_id');
        }

        // Get effective environment ID (routes to the centralized environment if enabled)
        $effectiveEnvironmentId = $this->environmentPaymentConfigService->getEffectiveEnvironmentId($environmentId);

        return PaymentGatewaySetting::where('code', $gatewayCode)
            ->where(function ($query) use ($effectiveEnvironmentId) {
                $query->where('environment_id', $effectiveEnvironmentId)
                    ->orWhereNull('environment_id');
            })
            ->first();
    }

    /**
     * Process payment for an order
     */
    public function processPayment(int $orderId, array $paymentData): array
    {
        Log::info('Processing payment for order '.$paymentData['payment_method']);
        $order = $this->orderService->getOrderById($orderId);

        if (! $order) {
            return [
                'success' => false,
                'message' => 'Order not found',
            ];
        }

        // Check if order is already paid
        if ($order->payment_status === 'paid') {
            return [
                'success' => false,
                'message' => 'Order is already paid',
            ];
        }

        // Validate payment method
        if (! isset($paymentData['payment_method'])) {
            return [
                'success' => false,
                'message' => 'Payment method is required',
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
                    'message' => 'Unsupported payment method',
                ];
        }
    }

    /**
     * Process payment using a payment gateway
     */
    protected function processGatewayPayment(Order $order, string $gatewayCode, array $paymentData): array
    {

        $environmentId = session('current_environment_id');

        // Get effective environment ID (routes to the centralized environment if enabled)
        $effectiveEnvironmentId = $this->environmentPaymentConfigService->getEffectiveEnvironmentId($environmentId);

        if ($effectiveEnvironmentId !== $environmentId) {
            Log::info('Using centralized payment gateway', [
                'original_environment_id' => $environmentId,
                'effective_environment_id' => $effectiveEnvironmentId,
                'order_id' => $order->id,
            ]);
        }

        Log::info('Processing payment for order '.$order->id.' using '.$gatewayCode);
        try {
            // Get the payment gateway settings using effective environment ID
            $gatewaySettings = PaymentGatewaySetting::where('code', $gatewayCode)
                ->where('environment_id', $effectiveEnvironmentId)
                ->where('status', true)
                ->first();

            if (! $gatewaySettings) {
                return [
                    'success' => false,
                    'message' => 'Payment gateway settings not found',
                ];
            }

            $environment = Environment::find($environmentId);

            // Check if a transaction already exists for this order
            $existingTransaction = Transaction::where('order_id', $order->id)
                ->whereIn('status', ['pending', 'failed', 'processing', 'cancelled'])
                ->first();

            if ($existingTransaction) {
                Log::info('Found existing transaction for order', [
                    'order_id' => $order->id,
                    'transaction_id' => $existingTransaction->transaction_id,
                ]);

                // update the transaction with a new transaction_id 'TXN_' . Str::uuid(),
                $existingTransaction->update([
                    'transaction_id' => 'TXN_'.Str::uuid(),
                    'payment_method' => $gatewayCode,
                    'environment_id' => $environmentId,
                    'status' => 'pending',
                ]);

                // Initialize the payment gateway with environment-specific settings
                $gateway = $this->initializeGateway($gatewayCode, $environment->name);
                if (! $gateway['success']) {
                    return $gateway;
                }

                $this->currentGateway = $gateway['gateway'];

                // Create the payment with the gateway using existing transaction
                $response = $this->currentGateway->createPayment($existingTransaction, $paymentData);

                if (! $response['success']) {
                    return $response;
                }

                $response['transaction'] = $existingTransaction;

                return $response;
            }

            // Create a new transaction if one doesn't exist
            if (! $existingTransaction) {
                Log::info('Creating new transaction for order', ['order_id' => $order->id]);

                $transaction = new Transaction;
                $transaction->order_id = $order->id;
                $transaction->environment_id = $paymentData['environment_id'] ?? $gatewaySettings->environment_id;
                $transaction->payment_gateway_setting_id = $gatewaySettings->id;
                $transaction->payment_method = $gatewayCode;
                $transaction->transaction_id = 'TXN-'.Str::random(16);

                // Validate customer name and email
                $transaction->customer_name = ! empty($order->billing_name) ? $order->billing_name : 'Guest Customer';

                // Only set customer email if it's valid
                if (! empty($order->billing_email) && filter_var($order->billing_email, FILTER_VALIDATE_EMAIL)) {
                    $transaction->customer_email = $order->billing_email;
                    Log::info('Valid customer email found for order '.$order->id, ['email' => $order->billing_email]);
                } else {
                    $transaction->customer_email = null;
                    Log::info('Invalid or missing customer email for order '.$order->id, ['raw_email' => $order->billing_email ?? 'null']);
                }

                // Set base amount (without commission)
                $transaction->amount = $order->total_amount ?? $order->total; // Fallback if total_amount is not set
                $transaction->currency = $order->currency ?? 'USD';
                $transaction->description = 'Payment for order #'.$order->order_number;
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
                    'total_amount' => $transactionAmounts['total_amount'],
                ]);

                $transaction->purpose = (($order->total_amount ?? 0) == 0 ? Transaction::PURPOSE_FREE_ENROLLMENT : Transaction::PURPOSE_COURSE_SALE);
                $transaction->source_type = 'order';
                $transaction->source_id = $order->id;
                $transaction->expected_amount = $order->total_amount;
                $transaction->expected_currency = $order->currency ?? 'USD';
                $transaction->platform_fee_amount = 0;

                $transaction->save();
            } else {
                // Use the existing transaction
                $transaction = $existingTransaction;

                // Update the transaction with new payment details
                $transaction->transaction_id = 'TXN_'.Str::uuid();
                $transaction->payment_method = $gatewayCode;
                $transaction->environment_id = $environmentId;
                $transaction->status = 'pending';
                $transaction->save();

                Log::info('Updated existing transaction for order', [
                    'order_id' => $order->id,
                    'transaction_id' => $transaction->transaction_id,
                ]);
            }

            // Initialize the payment gateway with environment-specific settings
            $gateway = $this->initializeGateway($gatewayCode, $environment->name);
            if (! $gateway['success']) {
                return $gateway;
            }

            $this->currentGateway = $gateway['gateway'];

            // Create the payment with the gateway
            $paymentResponse = $this->currentGateway->createPayment($transaction, $paymentData);

            if (! $paymentResponse['success']) {
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
                        'payment_date' => now()->format('Y-m-d H:i:s'),
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
                    'publishable_key' => $paymentResponse['publishable_key'] ?? null,
                    // TaraMoney payment links (if present)
                    'payment_links' => $paymentResponse['payment_links'] ?? null,
                    'general_link' => $paymentResponse['general_link'] ?? null,
                    'redirect_url' => $paymentResponse['redirect_url'] ?? null,
                    'whatsapp_link' => $paymentResponse['whatsapp_link'] ?? null,
                    'telegram_link' => $paymentResponse['telegram_link'] ?? null,
                    'dikalo_link' => $paymentResponse['dikalo_link'] ?? null,
                    'sms_link' => $paymentResponse['sms_link'] ?? null,
                    'card_link' => $paymentResponse['card_link'] ?? null,
                ];

                $response['transaction'] = $transaction;

                return $response;
            } else {
                return [
                    'success' => false,
                    'message' => $paymentResponse['message'] ?? 'Payment processing failed',
                    'error' => $paymentResponse['error'] ?? null,
                    'error_code' => $paymentResponse['error_code'] ?? null,
                ];
            }
        } catch (\Exception $e) {
            Log::error('Payment gateway error: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Payment processing failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Process credit card payment
     */
    protected function processCreditCardPayment(Order $order, array $paymentData): array
    {
        return [
            'success' => false,
            'message' => 'Credit card payment is not supported',
        ];
    }

    /**
     * Process PayPal payment
     */
    protected function processPayPalPayment(Order $order, array $paymentData): array
    {
        return [
            'success' => false,
            'message' => 'PayPal payment is not supported',
        ];
    }

    /**
     * Process bank transfer payment
     */
    protected function processBankTransferPayment(Order $order, array $paymentData): array
    {
        return [
            'success' => false,
            'message' => 'Bank transfer payment is not supported',
        ];
    }

    /**
     * Process manual payment
     */
    protected function processManualPayment(Order $order, array $paymentData): array
    {
        // Validate manual payment data
        if (! isset($paymentData['payment_reference'])) {
            return [
                'success' => false,
                'message' => 'Payment reference is required',
            ];
        }

        try {
            // Update order payment status
            $this->orderService->updatePaymentStatus($order->id, 'paid', [
                'transaction_id' => $paymentData['payment_reference'],
                'payment_method' => 'manual',
                'payment_date' => now()->format('Y-m-d H:i:s'),
                'notes' => $paymentData['notes'] ?? 'Manual payment',
            ]);

            // Update order status
            $this->orderService->updateOrderStatus($order->id, 'processing');

            return [
                'success' => true,
                'message' => 'Manual payment recorded successfully',
                'transaction_id' => $paymentData['payment_reference'],
                'order_number' => $order->order_number,
                'amount' => $order->total,
                'payment_date' => now()->format('Y-m-d H:i:s'),
            ];
        } catch (\Exception $e) {
            Log::error('Manual payment error: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Failed to record manual payment: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Verify payment status
     */
    public function verifyPayment(string $transactionId): array
    {
        // Find transaction with this ID
        $transaction = Transaction::where('transaction_id', $transactionId)
            ->orWhere('gateway_transaction_id', $transactionId)
            ->first();

        if (! $transaction) {
            return [
                'success' => false,
                'message' => 'Transaction not found',
            ];
        }

        try {
            // Get the payment gateway settings
            $gatewaySettings = PaymentGatewaySetting::where('id', $transaction->payment_gateway_setting_id)->first();

            if (! $gatewaySettings) {
                // Fall back to legacy verification method
                return $this->verifyLegacyPayment($transactionId);
            }

            // Verify the payment using the gateway
            $gateway = PaymentGatewayFactory::create($transaction->payment_method, $gatewaySettings);

            if (! $gateway) {
                return [
                    'success' => false,
                    'message' => 'Failed to initialize payment gateway',
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
                        'payment_date' => now()->format('Y-m-d H:i:s'),
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
                'verification_date' => $transaction->verified_at ? $transaction->verified_at->format('Y-m-d H:i:s') : null,
            ];
        } catch (\Exception $e) {
            Log::error('Payment verification error: '.$e->getMessage());

            return [
                'success' => false,
                'message' => 'Payment verification failed: '.$e->getMessage(),
            ];
        }
    }

    /**
     * Verify legacy payment status (for backward compatibility)
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

            if (
                isset($metadata['payment_data']['transaction_id']) &&
                $metadata['payment_data']['transaction_id'] === $transactionId
            ) {
                $matchingOrder = $order;
                break;
            }
        }

        if (! $matchingOrder) {
            return [
                'success' => false,
                'message' => 'Transaction not found',
            ];
        }

        return [
            'success' => true,
            'message' => 'Payment verified',
            'transaction_id' => $transactionId,
            'order_number' => $matchingOrder->order_number,
            'payment_status' => $matchingOrder->payment_status,
            'amount' => $matchingOrder->total,
            'payment_date' => json_decode($matchingOrder->metadata ?? '{}', true)['payment_data']['payment_date'] ?? null,
        ];
    }

    /**
     * Process a refund for an order.
     *
     * KURSA licensing transition (Phase 5, doc §9.9): this now delegates to the
     * authoritative {@see RefundService}, which writes the
     * Phase 3 transaction schema (child refund transaction with purpose=refund,
     * parent_transaction_id, negative amounts, verified_at on gateway
     * confirmation) and applies the ledger effects. The previous implementation
     * wrote non-existent columns (`type`, `metadata`) and simulated success — both
     * are removed. The legacy simulated fallback is gone; unsupported gateways are
     * handled through the manual refund path (RefundService::recordManualRefund).
     */
    public function processRefund(int $orderId, ?float $amount = null, string $reason = ''): array
    {
        $order = $this->orderService->getOrderById($orderId);

        if (! $order) {
            return ['success' => false, 'message' => 'Order not found'];
        }

        // Resolve the settled parent transaction for this order.
        $transaction = Transaction::where('order_id', $order->id)
            ->whereIn('status', [Transaction::STATUS_COMPLETED, Transaction::STATUS_PARTIALLY_REFUNDED])
            ->where(function ($q) {
                $q->whereNull('purpose')->orWhere('purpose', '!=', Transaction::PURPOSE_REFUND);
            })
            ->orderByDesc('id')
            ->first();

        if (! $transaction) {
            return ['success' => false, 'message' => 'No settled transaction found for this order to refund'];
        }

        $result = app(RefundService::class)
            ->initiateRefund($transaction, $amount, $reason);

        if ($result['status'] === 'ok') {
            return [
                'success' => true,
                'message' => $result['confirmed']
                    ? 'Refund processed successfully'
                    : 'Refund initiated; awaiting gateway confirmation',
                'confirmed' => $result['confirmed'],
                'refund_transaction_id' => $result['transaction']->transaction_id,
                'order_id' => $order->id,
                'amount' => abs((float) $result['transaction']->amount),
                'currency' => $result['transaction']->currency,
                'effects' => $result['effects'] ?? null,
            ];
        }

        if ($result['status'] === 'unsupported') {
            return [
                'success' => false,
                'unsupported' => true,
                'message' => $result['message'],
                'manual_instructions' => $result['manual_instructions'] ?? null,
            ];
        }

        return ['success' => false, 'message' => $result['message'] ?? 'Refund failed'];
    }

    /**
     * Generate invoice
     */
    public function generateInvoice(int $orderId): array
    {
        $order = $this->orderService->getOrderById($orderId);

        if (! $order) {
            return [
                'success' => false,
                'message' => 'Order not found',
            ];
        }

        // Generate invoice number
        $invoiceNumber = 'INV-'.$order->order_number;

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
                'shipping_address' => $order->shipping_address,
            ],
            'items' => [],
            'subtotal' => $order->subtotal,
            'discount' => $order->discount,
            'tax' => $order->tax,
            'total' => $order->total,
            'payment_status' => $order->payment_status,
            'payment_method' => $order->payment_method,
        ];

        // Add items to invoice
        foreach ($order->orderItems as $item) {
            $invoiceData['items'][] = [
                'name' => $item->name,
                'quantity' => $item->quantity,
                'price' => $item->price,
                'total' => $item->total,
            ];
        }

        // In a real application, this would generate a PDF invoice
        // For this demo, we'll just return the invoice data

        return [
            'success' => true,
            'message' => 'Invoice generated successfully',
            'invoice_data' => $invoiceData,
        ];
    }

    /**
     * Get payment methods
     */
    public function getPaymentMethods(?int $environmentId = null): array
    {
        if (! $environmentId) {
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
     * @param  string  $gateway  The payment gateway name
     * @param  string  $transactionId  The transaction ID
     * @param  int  $environmentId  The environment ID
     * @param  array  $callbackData  The callback data received from the payment gateway
     * @return bool True if processing was successful, false otherwise
     */
    public function processSuccessCallback(string $gateway, string $transactionId, $environmentId, array $callbackData): bool
    {
        try {
            // Find the transaction using smart lookup that handles cross-environment supported plan transactions
            $transaction = $this->findTransactionForCallback($transactionId, $environmentId);

            if (! $transaction) {
                Log::error('Transaction not found for success callback', [
                    'gateway' => $gateway,
                    'transaction_id' => $transactionId,
                    'environment_id' => $environmentId,
                ]);

                return false;
            }

            DB::transaction(function () use ($transaction, $callbackData) {
                $transaction = Transaction::withoutGlobalScopes()
                    ->whereKey($transaction->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->applySettlement($transaction, 'completed', $callbackData);
            });

            return true;
        } catch (\Exception $e) {
            Log::error('Error processing payment success callback in service', [
                'error' => $e->getMessage(),
                'gateway' => $gateway,
                'transaction_id' => $transactionId,
            ]);

            return false;
        }
    }

    /**
     * Process a failed payment callback from a payment gateway
     *
     * @param  string  $gateway  The payment gateway name
     * @param  string  $transactionId  The transaction ID
     * @param  int  $environmentId  The environment ID
     * @param  array  $callbackData  The callback data received from the payment gateway
     * @return bool True if processing was successful, false otherwise
     */
    public function processFailureCallback(string $gateway, string $transactionId, $environmentId, array $callbackData): bool
    {
        try {
            // Find the transaction using smart lookup that handles cross-environment supported plan transactions
            $transaction = $this->findTransactionForCallback($transactionId, $environmentId);

            if (! $transaction) {
                Log::error('Transaction not found for failure callback', [
                    'gateway' => $gateway,
                    'transaction_id' => $transactionId,
                    'environment_id' => $environmentId,
                ]);

                return false;
            }

            DB::transaction(function () use ($transaction, $gateway, $callbackData) {
                $transaction = Transaction::withoutGlobalScopes()
                    ->whereKey($transaction->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->applyFailure($transaction, 'failed', $callbackData, 'Payment failed via '.$gateway);
            });

            return true;
        } catch (\Exception $e) {
            Log::error('Error processing payment failure callback in service', [
                'error' => $e->getMessage(),
                'gateway' => $gateway,
                'transaction_id' => $transactionId,
            ]);

            return false;
        }
    }

    /**
     * Process a cancelled payment callback from a payment gateway
     *
     * @param  string  $gateway  The payment gateway name
     * @param  string  $transactionId  The transaction ID
     * @param  int  $environmentId  The environment ID
     * @param  array  $callbackData  The callback data received from the payment gateway
     * @return bool True if processing was successful, false otherwise
     */
    public function processCancelledCallback(string $gateway, string $transactionId, $environmentId, array $callbackData): bool
    {
        try {
            // Find the transaction using smart lookup that handles cross-environment supported plan transactions
            $transaction = $this->findTransactionForCallback($transactionId, $environmentId);

            if (! $transaction) {
                Log::error('Transaction not found for cancelled callback', [
                    'gateway' => $gateway,
                    'transaction_id' => $transactionId,
                    'environment_id' => $environmentId,
                ]);

                return false;
            }

            DB::transaction(function () use ($transaction, $gateway, $callbackData) {
                $transaction = Transaction::withoutGlobalScopes()
                    ->whereKey($transaction->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($transaction->status === Transaction::STATUS_COMPLETED) {
                    Log::warning('Ignoring cancellation callback for already completed transaction', [
                        'transaction_id' => $transaction->transaction_id,
                        'gateway' => $gateway,
                    ]);

                    return;
                }

                $transaction->status = Transaction::STATUS_CANCELLED;
                $transaction->gateway_status = 'cancelled';
                $transaction->gateway_response = $callbackData;
                $transaction->notes = 'Payment cancelled via '.$gateway;
                $transaction->save();
            });

            return true;
        } catch (\Exception $e) {
            Log::error('Error processing payment cancelled callback in service', [
                'error' => $e->getMessage(),
                'gateway' => $gateway,
                'transaction_id' => $transactionId,
            ]);

            return false;
        }
    }

    /**
     * Reusable settlement core (KURSA plan §11 step 8). Marks a transaction
     * completed and fulfils related records (order/subscription/invoice/payment).
     *
     * IMPORTANT: the caller MUST already hold a FOR UPDATE lock on the row and be
     * inside a DB transaction. This is the single fulfilment path shared by the
     * legacy callback service methods and the new WebhookProcessor — do NOT
     * duplicate this logic elsewhere.
     *
     * @param  Transaction  $transaction  Locked transaction row.
     * @param  string  $gatewayStatus  Raw gateway status for auditing.
     * @param  array  $payload  Verified gateway payload.
     */
    public function applySettlement(Transaction $transaction, string $gatewayStatus, array $payload): void
    {
        $alreadyCompleted = $transaction->status === Transaction::STATUS_COMPLETED;

        $transaction->status = Transaction::STATUS_COMPLETED;
        $transaction->gateway_status = $gatewayStatus;
        $transaction->gateway_response = $payload;
        $transaction->paid_at = $transaction->paid_at ?: now();
        $transaction->verified_at = $transaction->verified_at ?: now();
        $transaction->save();
        $transaction->refresh();

        $this->processRelatedRecords($transaction, ! $alreadyCompleted);
    }

    /**
     * Reusable failure core. Marks a transaction failed without regressing an
     * already-completed one. Caller MUST hold the row lock inside a DB transaction.
     *
     * @param  Transaction  $transaction  Locked transaction row.
     * @param  string  $gatewayStatus  Raw gateway status for auditing.
     * @param  array  $payload  Verified gateway payload.
     * @param  string|null  $notes  Optional note.
     */
    public function applyFailure(Transaction $transaction, string $gatewayStatus, array $payload, ?string $notes = null): void
    {
        if ($transaction->status === Transaction::STATUS_COMPLETED) {
            Log::warning('Ignoring failure for already completed transaction', [
                'transaction_id' => $transaction->transaction_id,
            ]);

            return;
        }

        $transaction->status = Transaction::STATUS_FAILED;
        $transaction->gateway_status = $gatewayStatus;
        $transaction->gateway_response = $payload;
        if ($notes !== null) {
            $transaction->notes = $notes;
        }
        $transaction->save();

        $payment = Payment::where('transaction_id', $transaction->transaction_id)->first();
        if ($payment && $payment->status !== Payment::STATUS_COMPLETED) {
            $payment->markAsFailed(
                $transaction->gateway_transaction_id,
                $gatewayStatus,
                $payload
            );
        }
    }

    /**
     * Process any records related to a transaction (orders, subscriptions, etc.)
     */
    protected function processRelatedRecords(Transaction $transaction, bool $shouldDispatchOrderEvent = true): void
    {
        $this->createCommissionRecordIfNeeded($transaction);

        if ($shouldDispatchOrderEvent) {
            $order = Order::where('id', $transaction->order_id)->first();
            if ($order && $order->status !== Order::STATUS_COMPLETED) {
                event(new OrderCompleted($order));
            }
        }

        if ($transaction->invoice_id) {
            Invoice::where('id', $transaction->invoice_id)->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_gateway' => $transaction->payment_method,
                'payment_link' => null,
            ]);
        }

        $details = is_array($transaction->payment_method_details)
            ? $transaction->payment_method_details
            : json_decode($transaction->payment_method_details ?: '[]', true);

        $payment = Payment::where('transaction_id', $transaction->transaction_id)->first();
        if ($payment) {
            $paymentAlreadyCompleted = $payment->status === Payment::STATUS_COMPLETED;

            $payment->markAsCompleted(
                $transaction->gateway_transaction_id,
                $transaction->gateway_status,
                $transaction->gateway_response
            );

            if ($paymentAlreadyCompleted) {
                return;
            }
        }

        $metadata = $details['metadata'] ?? [];
        if (($details['source_type'] ?? null) === 'subscription_plan_change') {
            $subscription = Subscription::find($metadata['subscription_id'] ?? $details['source_id'] ?? null);
            $plan = Plan::find($metadata['new_plan_id'] ?? null);

            if ($subscription && $plan) {
                $subscription->update([
                    'plan_id' => $plan->id,
                    'billing_cycle' => $metadata['billing_cycle'] ?? $subscription->billing_cycle,
                    'status' => Subscription::STATUS_ACTIVE,
                    'last_payment_at' => now(),
                    'next_payment_at' => ($metadata['billing_cycle'] ?? $subscription->billing_cycle) === 'annual'
                        ? now()->addYear()
                        : now()->addMonth(),
                    'ends_at' => ($metadata['billing_cycle'] ?? $subscription->billing_cycle) === 'annual'
                        ? now()->addYear()
                        : now()->addMonth(),
                ]);
            }
        }
    }

    protected function createCommissionRecordIfNeeded(Transaction $transaction): void
    {
        // KURSA licensing transition (Phase 2): course sales carry 0% platform commission,
        // so no InstructorCommission (payout liability) records are created for course
        // transactions anymore. This is intentionally a no-op; the call site is preserved
        // so historical InstructorCommission read/approval/withdrawal paths keep working.

    }

    /**
     * Find transaction for callback with smart lookup logic
     * Handles cross-environment transactions for supported plans
     *
     * @param  string  $transactionId
     * @param  int  $environment_id
     * @return Transaction|null
     */
    private function findTransactionForCallback($transactionId, $environment_id)
    {
        // First, try environment-specific lookup (existing behavior)
        $transaction = Transaction::where(function ($query) use ($transactionId) {
            $query->where('transaction_id', $transactionId)
                ->orWhere('gateway_transaction_id', $transactionId);
        })
            ->when(is_numeric($environment_id), fn ($query) => $query->where('environment_id', $environment_id))
            ->whereHas('paymentGatewaySetting')
            ->first();

        if ($transaction) {
            return $transaction;
        }

        // If not found, try global lookup for supported plan transactions
        // IMPORTANT: Use withoutGlobalScopes to bypass EnvironmentScope for cross-environment lookup
        $globalTransaction = Transaction::withoutGlobalScopes()
            ->where(function ($query) use ($transactionId) {
                $query->where('transaction_id', $transactionId)
                    ->orWhere('gateway_transaction_id', $transactionId);
            })
            ->whereHas('paymentGatewaySetting')
            ->first();

        if ($globalTransaction) {
            // KURSA licensing transition (Phase 3): cross-environment lookup is now
            // driven by the explicit transaction purpose (environment licences are
            // billed centrally and can therefore be resolved outside their own
            // environment scope), replacing the old amount/description heuristics.
            if (in_array($globalTransaction->purpose, Transaction::LICENCE_PURPOSES, true)) {
                Log::info('PaymentService: Environment-licence transaction found with global lookup', [
                    'transaction_id' => $transactionId,
                    'callback_environment_id' => $environment_id,
                    'transaction_environment_id' => $globalTransaction->environment_id,
                    'purpose' => $globalTransaction->purpose,
                ]);

                return $globalTransaction;
            }
        }

        return null;
    }
}
