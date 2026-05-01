<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Payment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Environment;
use App\Models\PaymentGatewaySetting;
use App\Models\Subscription;
use App\Models\Plan;
use App\Models\Invoice;
use App\Models\InstructorCommission;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Redirect;
use App\Mail\OrderConfirmation;
use Illuminate\Support\Str;
use App\Models\AuditLog;
use App\Models\Branding;

/**
 * @OA\Schema(
 *     schema="Transaction",
 *     required={"environment_id", "amount", "total_amount", "currency", "status"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="transaction_id", type="string", format="uuid", example="f47ac10b-58cc-4372-a567-0e02b2c3d479"),
 *     @OA\Property(property="environment_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="payment_gateway_setting_id", type="integer", format="int64", example=1, nullable=true),
 *     @OA\Property(property="order_id", type="string", example="ORD-2025-0001", nullable=true),
 *     @OA\Property(property="invoice_id", type="string", example="INV-2025-0001", nullable=true),
 *     @OA\Property(property="customer_id", type="string", example="cus_123456", nullable=true),
 *     @OA\Property(property="customer_email", type="string", example="john@example.com", nullable=true),
 *     @OA\Property(property="customer_name", type="string", example="John Doe", nullable=true),
 *     @OA\Property(property="amount", type="number", format="float", example=99.99),
 *     @OA\Property(property="fee_amount", type="number", format="float", example=2.9),
 *     @OA\Property(property="tax_amount", type="number", format="float", example=5.0),
 *     @OA\Property(property="total_amount", type="number", format="float", example=107.89),
 *     @OA\Property(property="currency", type="string", example="USD"),
 *     @OA\Property(property="status", type="string", enum={"pending", "processing", "completed", "failed", "refunded", "partially_refunded"}, example="completed"),
 *     @OA\Property(property="payment_method", type="string", example="credit_card", nullable=true),
 *     @OA\Property(property="payment_method_details", type="string", example="Visa **** 4242", nullable=true),
 *     @OA\Property(property="gateway_transaction_id", type="string", example="ch_123456789", nullable=true),
 *     @OA\Property(property="gateway_status", type="string", example="succeeded", nullable=true),
 *     @OA\Property(property="description", type="string", example="Payment for order #ORD-2025-0001", nullable=true),
 *     @OA\Property(property="notes", type="string", example="Customer requested express shipping", nullable=true),
 *     @OA\Property(property="paid_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="refunded_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */

class TransactionController extends Controller
{
    /**
     * Handle successful payment callback
     *
     * @OA\Get(
     *     path="/api/payments/transactions/callback/success/{gateway}/{environment_id}",
     *     summary="Handle successful payment callback",
     *     description="Receives callback from payment gateway after successful payment and redirects user",
     *     operationId="callbackSuccess",
     *     tags={"Transactions"},
     *     @OA\Parameter(
     *         name="gateway",
     *         in="path",
     *         required=true,
     *         description="Payment gateway identifier (stripe, paypal, etc)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="environment_id",
     *         in="path",
     *         required=true,
     *         description="Environment ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="transaction_id",
     *         in="query",
     *         required=true,
     *         description="Transaction ID (UUID)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=302,
     *         description="Redirects to success page"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Transaction ID not provided"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Transaction not found"
     *     )
     * )
     */
    public function callbackSuccess(Request $request, $environment_id)
    {
        $auditEnvironmentId = is_numeric($environment_id) ? (int) $environment_id : null;

        Log::error('Received Success CallBack ', [
            'environment_id' => $environment_id,
            "data" => $request->all(),
            "headers" => $request->headers->all(),
            "content" => $request->getContent()
        ]);

        // we have monetbil and lygos who are active now for callbacks
        //we shall focus on these two
        //we can't determine the gateway from the request, but from the $transasction->transaction_id,
        //we can get the gateway from the $transasction->gateway

        /**
         * Monetbill callback success/cancelled
         * http://localhost:8000/api/payments/transactions/callback/success/1?email=kevinliboire%40gmail.com&first_name=Kevin&item_ref=8&last_name=Li&payment_ref=TXN_b9b2a180-74e4-495e-90ac-7c8180324721&status=cancelled&transaction_id=25062714073048724384&user=7&sign=cb81483ce5868d04e31ff15cc0996091
         */

        /**
         * Lygos CallBack
         * Not yet implemented, will do when we get one 
         * call back from Lygos
         */

        $environment = null;
        $protocol = app()->environment('production') ? 'https' : 'http';
        $transactionId = $request->get('payment_ref')
            ?? $request->get('transaction_id')
            ?? $request->get('reference')
            ?? $request->get('id')
            ?? $request->get("order_id")
            ?? null;

        Log::info('Looking for transaction', [
            'gateway_transaction_id' => $transactionId,
            'environment_id' => $environment_id
        ]);

        // Check if transaction ID exists first
        if (!$transactionId) {
            Log::error('Transaction ID not provided in success callback', [
                'environment_id' => $environment_id
            ]);
            return response()->json(['error' => 'Transaction ID is required'], 400);
        }

        // Use smart transaction lookup that handles cross-environment supported plan transactions
        $transaction = $this->findTransactionForCallback($transactionId, $environment_id);
        
        // Handle completed transaction case (kept for backward compatibility)
        $completedTransaction = null;
        if (!$transaction) {
            // This shouldn't happen with the new lookup, but kept as fallback
            $completedTransaction = Transaction::where("transaction_id", $transactionId)
                ->where("status", Transaction::STATUS_COMPLETED)
                ->whereHas("paymentGatewaySetting")
                ->first();
            
            if ($completedTransaction && ($completedTransaction->environment_id == $environment_id || $this->isSupportedPlanPayment($completedTransaction))) {
                $transaction = $completedTransaction;
                $completedTransaction = $transaction; // For consistency with existing logic
            }
        }

        //Get The Environment with relationship to Branding
        $environment = $auditEnvironmentId ? Environment::where("id", $auditEnvironmentId)->first() : null;
        $branding = $auditEnvironmentId ? Branding::where("environment_id", $auditEnvironmentId)->first() : null;

        // Check if transaction exists (either pending or completed)
        if (!$transaction && !$completedTransaction) {
            Log::error('Transaction not found for callback', [
                'transaction_id' => $transactionId,
                'environment_id' => $environment_id
            ]);
            return view('payment.error', [
                'transaction' => null,
                'environment' => $environment,
                "branding" => $branding,
                "protocol" => $protocol
            ]);
        }
        
        // Handle completed transaction with pending order
        if ($completedTransaction) {
            Log::info('Transaction already completed, checking order status', [
                'transaction_id' => $transactionId,
                'environment_id' => $environment_id
            ]);
            
            // Check if the order is still pending
            $order = Order::where('id', $completedTransaction->order_id)->first();
            if ($order && $order->status === Order::STATUS_PENDING) {
                Log::info('Order is pending for completed transaction, regularizing', [
                    'transaction_id' => $transactionId,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number
                ]);
                
                // Process the success callback for the completed transaction
                $status = $request->get("status", "");
                $successStatuses = ["success", "successful", "1", 1];
                
                if (in_array($status, $successStatuses, true)) {
                    $paymentService = app(PaymentService::class);
                    $gateway = $completedTransaction->paymentGatewaySetting->code;
                    
                    try {
                        $result = $paymentService->processSuccessCallback($gateway, $transactionId, $environment_id, $request->all());
                        
                        Log::info('Order regularized successfully for completed transaction', [
                            'transaction_id' => $transactionId,
                            'order_id' => $order->id
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Error regularizing order for completed transaction', [
                            'transaction_id' => $transactionId,
                            'order_id' => $order->id,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }
            
            // Return success view for completed transaction
            return view('payment.success', [
                'transaction' => $completedTransaction,
                'environment' => $environment,
                "branding" => $branding,
                "protocol" => $protocol
            ]);
        }


        // Get gateway settings from transaction
        $gatewaySettings = $transaction->paymentGatewaySetting;

        // Check if gateway settings exist
        if (!$gatewaySettings) {
            Log::error('Payment gateway settings not found for transaction', [
                'transaction_id' => $transactionId,
                'environment_id' => $environment_id
            ]);
            return response()->json(['error' => 'Payment gateway settings not found'], 404);
        }

        $gateway = $gatewaySettings->code;



        // Log the callback to AuditLog
        $auditLog = AuditLog::logCallback(
            $gateway,
            'success',
            $request->all(),
            'Transaction',
            $transactionId,
            $auditEnvironmentId,
            'Payment success callback received',
            AuditLog::STATUS_SUCCESS
        );

        try {
            // Process payment only if status indicates success
            $result = null;
            $status = strtolower((string) $request->get("status", ""));
            $successStatuses = ["success", "successful", "1", 1];
            $failedStatuses = ["failed", "failure", "0", 0, "error"];
            $cancelledStatuses = ["cancelled", "cancelled_by_user", "cancel"];


            $paymentService = app(PaymentService::class);

            if (in_array($status, $successStatuses, true)) {
                // Update transaction status through PaymentService

                $result = $paymentService->processSuccessCallback($gateway, $transactionId, $environment_id, $request->all());
            }

            if (in_array($status, $failedStatuses, true)) {
                $result = $paymentService->processFailureCallback($gateway, $transactionId, $environment_id, $request->all());
                return view('payment.callback-failed', [
                    'transaction' => $transaction,
                    'environment' => $environment,
                    "branding" => $branding,
                    "protocol" => $protocol
                ]);
            }

            if (in_array($status, $cancelledStatuses, true)) {
                $result = $paymentService->processCancelledCallback($gateway, $transactionId, $environment_id, $request->all());
                return view('payment.callback-cancelled', [
                    'transaction' => $transaction,
                    'environment' => $environment,
                    "branding" => $branding,
                    "protocol" => $protocol
                ]);
            }

            if (!$result) {
                Log::error('Failed to process payment success callback', [
                    'gateway' => $gateway,
                    'environment_id' => $environment,
                    'transaction_id' => $transactionId
                ]);

                // Update audit log with failure
                $auditLog->update([
                    'status' => AuditLog::STATUS_FAILURE,
                    'notes' => 'Failed to process payment success callback'
                ]);

                // Check if this is a supported plan payment (environment setup)
                $isSupportedPlan = $this->isSupportedPlanPayment($transaction);
                $viewPath = $isSupportedPlan ? 'payment.environment-setup.error' : 'payment.error';

                return view($viewPath, [
                    'transaction' => $transaction,
                    'environment' => $environment,
                    "branding" => $branding,
                    "protocol" => $protocol
                ]);
            }

            $transaction->refresh();

            // Check if this is a supported plan payment (environment setup)
            $isSupportedPlan = $this->isSupportedPlanPayment($transaction);
            
            $viewPath = $isSupportedPlan ? 'payment.environment-setup.callback-success' : 'payment.callback-success';
            
            return view($viewPath, [
                'transaction' => $transaction,
                'environment' => $environment,
                "branding" => $branding,
                "protocol" => $protocol
            ]);
        } catch (\Exception $e) {
            Log::error('Error in payment success callback', [
                'error' => $e->getMessage(),
                'gateway' => $gateway,
                'environment_id' => $environment,
                'transaction_id' => $transactionId
            ]);

            // Update audit log with error
            $auditLog->update([
                'status' => AuditLog::STATUS_ERROR,
                'notes' => 'Exception: ' . $e->getMessage()
            ]);

            // Fallback redirect to dashboard with error
            return view('payment.error', [
                'transaction' => $transaction,
                'environment' => $environment,
                "branding" => $branding,
                "protocol" => $protocol
            ]);
        }
    }

    public function callbackFailure(Request $request, $environment_id)
    {
        $auditEnvironmentId = is_numeric($environment_id) ? (int) $environment_id : null;

        Log::error('Received Failure CallBack ', [
            'environment_id' => $environment_id,
            "data" => $request->all(),
            "headers" => $request->headers->all(),
            "content" => $request->getContent()
        ]);

        $environment = null;
        $transactionId = $request->get('payment_ref')
            ?? $request->get('transaction_id')
            ?? $request->get('reference')
            ?? $request->get('id')
            ?? $request->get("order_id")
            ?? null;
        $protocol = app()->environment('production') ? 'https' : 'http';

        Log::info('Looking for transaction', [
            'gateway_transaction_id' => $transactionId,
            'environment_id' => $environment_id
        ]);

        // Check if transaction ID exists first
        if (!$transactionId) {
            Log::error('Transaction ID not provided in success callback', [
                'environment_id' => $environment_id
            ]);
            return response()->json(['error' => 'Transaction ID is required'], 400);
        }

        // Use smart transaction lookup that handles cross-environment supported plan transactions
        $transaction = $this->findTransactionForCallback($transactionId, $environment_id);
        $environment = $auditEnvironmentId ? Environment::find($auditEnvironmentId) : null;
        $branding = $auditEnvironmentId ? Branding::where("environment_id", $auditEnvironmentId)->first() : null;

        // Check if transaction exists
        if (!$transaction) {
            Log::error('Transaction not found for callback', [
                'transaction_id' => $transactionId,
                'environment_id' => $environment_id
            ]);
            return view('payment.error', [
                'transaction' => $transaction,
                'environment' => $environment,
                "branding" => $branding,
                "protocol" => $protocol
            ]);
        }


        // Get gateway settings from transaction
        $gatewaySettings = $transaction->paymentGatewaySetting;

        // Check if gateway settings exist
        if (!$gatewaySettings) {
            Log::error('Payment gateway settings not found for transaction', [
                'transaction_id' => $transactionId,
                'environment_id' => $environment_id
            ]);
            return response()->json(['error' => 'Payment gateway settings not found'], 404);
        }

        $gateway = $gatewaySettings->code;

        // Log the callback to AuditLog
        $auditLog = AuditLog::logCallback(
            $gateway,
            'failure',
            $request->all(),
            'Transaction',
            $transactionId,
            $auditEnvironmentId,
            'Payment failure callback received',
            AuditLog::STATUS_SUCCESS
        );

        Log::info('Payment failure callback received', [
            'gateway' => $gateway,
            'environment_id' => $environment_id,
            'transaction_id' => $transactionId,
            'audit_log_id' => $auditLog->id
        ]);

        try {
            // Process payment only if status indicates success
            $result = null;
            $status = strtolower((string) $request->get("status", ""));
            $failed = ["failed", "failure", "0", 0, "error"];
            $cancelledStatuses = ["cancelled", "cancelled_by_user", "cancel"];


            $paymentService = app(PaymentService::class);

            if (in_array($status, $failed, true)) {
                // Update transaction status through PaymentService

                $result = $paymentService->processFailureCallback($gateway, $transactionId, $environment_id, $request->all());
            } elseif (in_array($status, $cancelledStatuses, true)) {
                $result = $paymentService->processCancelledCallback($gateway, $transactionId, $environment_id, $request->all());
                
                // Check if this is a supported plan payment (environment setup)
                $isSupportedPlan = $this->isSupportedPlanPayment($transaction);
                $viewPath = $isSupportedPlan ? 'payment.environment-setup.callback-cancelled' : 'payment.callback-cancelled';
                
                return view($viewPath, [
                    'transaction' => $transaction,
                    'environment' => $environment,
                    "branding" => $branding,
                    "protocol" => $protocol
                ]);
            }

            if (!$result) {
                Log::error('Failed to process payment failure callback', [
                    'gateway' => $gateway,
                    'environment_id' => $environment,
                    'transaction_id' => $transactionId
                ]);

                // Update audit log with failure
                $auditLog->update([
                    'status' => AuditLog::STATUS_FAILURE,
                    'notes' => 'Failed to process payment failure callback'
                ]);

                // Check if this is a supported plan payment (environment setup)
                $isSupportedPlan = $this->isSupportedPlanPayment($transaction);
                $viewPath = $isSupportedPlan ? 'payment.environment-setup.error' : 'payment.error';

                return view($viewPath, [
                    'transaction' => $transaction,
                    'environment' => $environment,
                    "branding" => $branding,
                    "protocol" => $protocol
                ]);
            }

            $transaction->refresh();

            // Check if this is a supported plan payment (environment setup)
            $isSupportedPlan = $this->isSupportedPlanPayment($transaction);
            $viewPath = $isSupportedPlan ? 'payment.environment-setup.callback-failed' : 'payment.callback-failed';

            return view($viewPath, [
                'transaction' => $transaction,
                'environment' => $environment,
                "branding" => $branding,
                "protocol" => $protocol
            ]);
        } catch (\Exception $e) {
            Log::error('Error in payment failure callback', [
                'error' => $e->getMessage(),
                'gateway' => $gateway,
                'environment_id' => $environment,
                'transaction_id' => $transactionId
            ]);

            // Update audit log with error
            $auditLog->update([
                'status' => AuditLog::STATUS_ERROR,
                'notes' => 'Exception: ' . $e->getMessage()
            ]);

            // Fallback redirect to dashboard with error
            return view('payment.error', [
                'transaction' => $transaction,
                'environment' => $environment,
                "branding" => $branding,
                "protocol" => $protocol
            ]);
        }


    }

    public function paypalReturn(Request $request)
    {
        $transaction = null;

        try {
            $transactionId = $request->query('transaction_id');
            $paypalOrderId = $request->query('token') ?? $request->query('order_id');

            if (!$transactionId || !$paypalOrderId) {
                Log::error('PayPal return missing required identifiers', [
                    'transaction_id' => $transactionId,
                    'token_present' => !empty($paypalOrderId),
                    'payload' => $request->all(),
                ]);

                return response()->json(['error' => 'Missing PayPal transaction identifiers'], 400);
            }

            $transaction = Transaction::withoutGlobalScopes()->find($transactionId);
            if (!$transaction) {
                Log::error('PayPal return transaction not found', [
                    'transaction_id' => $transactionId,
                    'payload' => $request->all(),
                ]);

                return response()->json(['error' => 'Transaction not found'], 404);
            }

            $gatewaySettings = $transaction->payment_gateway_setting_id
                ? PaymentGatewaySetting::withoutGlobalScopes()->find($transaction->payment_gateway_setting_id)
                : PaymentGatewaySetting::withoutGlobalScopes()
                    ->where('code', 'paypal')
                    ->where('status', true)
                    ->where(function ($query) use ($transaction) {
                        $query->where('environment_id', $transaction->environment_id)
                            ->orWhereNull('environment_id');
                    })
                    ->orderByRaw('environment_id IS NULL')
                    ->first();

            if (!$gatewaySettings) {
                Log::error('PayPal gateway settings not found for return callback', [
                    'transaction_id' => $transaction->transaction_id,
                    'payment_gateway_setting_id' => $transaction->payment_gateway_setting_id,
                ]);

                return response()->json(['error' => 'PayPal gateway settings not found'], 404);
            }

            $gateway = \App\Services\PaymentGateways\PaymentGatewayFactory::create('paypal', $gatewaySettings);
            if (!$gateway) {
                return response()->json(['error' => 'PayPal gateway unavailable'], 500);
            }

            $result = $gateway->processPayment($transaction, ['order_id' => $paypalOrderId]);
            $payload = array_merge($request->all(), ['paypal_capture' => $result]);

            if (($result['success'] ?? false) && strtoupper((string) ($result['status'] ?? 'COMPLETED')) === 'COMPLETED') {
                $this->processCompletedWebhookTransaction($transaction, $result['status'] ?? 'COMPLETED', $payload);
                $transaction->refresh();

                return $this->renderPayPalCallbackView($transaction, 'success');
            }

            $this->processFailedWebhookTransaction($transaction, $result['status'] ?? 'failed', $payload, 'PayPal capture failed during return callback');
            $transaction->refresh();

            return $this->renderPayPalCallbackView($transaction, 'failed');
        } catch (\Exception $e) {
            Log::error('Error processing PayPal return callback', [
                'error' => $e->getMessage(),
                'payload' => $request->all(),
                'transaction_id' => $transaction?->transaction_id,
            ]);

            return $transaction
                ? $this->renderPayPalCallbackView($transaction, 'failed')
                : response()->json(['error' => 'Error processing PayPal return callback'], 500);
        }
    }

    public function paypalCancel(Request $request)
    {
        $transactionId = $request->query('transaction_id');
        $transaction = $transactionId ? Transaction::withoutGlobalScopes()->find($transactionId) : null;

        if (!$transaction) {
            Log::error('PayPal cancel transaction not found', [
                'transaction_id' => $transactionId,
                'payload' => $request->all(),
            ]);

            return response()->json(['error' => 'Transaction not found'], 404);
        }

        $this->processFailedWebhookTransaction($transaction, 'cancelled', $request->all(), 'PayPal payment cancelled by customer');
        $transaction->refresh();

        return $this->renderPayPalCallbackView($transaction, 'cancelled');
    }

    private function renderPayPalCallbackView(Transaction $transaction, string $state)
    {
        $environment = $transaction->environment_id ? Environment::find($transaction->environment_id) : null;
        $branding = $transaction->environment_id ? Branding::where('environment_id', $transaction->environment_id)->first() : null;
        $protocol = app()->environment('production') ? 'https' : 'http';
        $isSupportedPlan = $this->isSupportedPlanPayment($transaction);

        $viewPath = match ($state) {
            'success' => $isSupportedPlan ? 'payment.environment-setup.callback-success' : 'payment.callback-success',
            'cancelled' => $isSupportedPlan ? 'payment.environment-setup.callback-cancelled' : 'payment.callback-cancelled',
            default => $isSupportedPlan ? 'payment.environment-setup.callback-failed' : 'payment.callback-failed',
        };

        return view($viewPath, [
            'transaction' => $transaction,
            'environment' => $environment,
            'branding' => $branding,
            'protocol' => $protocol,
        ]);
    }


    /**
     * Handle failed payment callback
     *
     * @OA\Get(
     *     path="/api/payments/transactions/callback/failure/{gateway}/{environment_id}",
     *     summary="Handle failed payment callback",
     *     description="Receives callback from payment gateway after failed payment and redirects user",
     *     operationId="callbackFailure",
     *     tags={"Transactions"},
     *     @OA\Parameter(
     *         name="gateway",
     *         in="path",
     *         required=true,
     *         description="Payment gateway identifier (stripe, paypal, etc)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="environment_id",
     *         in="path",
     *         required=true,
     *         description="Environment ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="transaction_id",
     *         in="query",
     *         required=true,
     *         description="Transaction ID (UUID)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=302,
     *         description="Redirects to failure page"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Transaction ID not provided"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Transaction not found"
     *     )
     * )
     */
    /**
     * Handle payment webhook notifications from gateways
     * 
     * @OA\Post(
     *     path="/api/payments/transactions/webhook/{gateway}/{environment_id}",
     *     summary="Process payment gateway webhook notifications",
     *     description="Handles webhook notifications from payment gateways like Stripe, PayPal etc.",
     *     operationId="transactionWebhook",
     *     tags={"Transactions"},
     *     @OA\Parameter(
     *         name="gateway",
     *         in="path",
     *         required=true,
     *         description="Payment gateway identifier (stripe, paypal, etc)",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="environment_id",
     *         in="path",
     *         required=true,
     *         description="Environment ID",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\RequestBody(
     *         description="Raw webhook payload from the payment gateway",
     *         required=true
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Webhook processed successfully"
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid webhook payload or signature"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Gateway settings not found"
     *     ),
     *     @OA\Response(
     *         response=500,
     *         description="Error processing webhook"
     *     )
     * )
     */
    public function webhook(Request $request, $gateway, $environment_id)
    {
        try {
            $auditEnvironmentId = is_numeric($environment_id) ? (int) $environment_id : null;

            // Log the incoming webhook to AuditLog
            $auditLog = AuditLog::logWebhook(
                $gateway,
                $request->all(),
                'Transaction', // entity type
                null, // entity id (not known yet)
                $auditEnvironmentId,
                null, // response data (will be updated later)
                ['headers' => $request->header()],
                AuditLog::STATUS_SUCCESS // Initial status
            );

            Log::info('Payment webhook received', [
                'gateway' => $gateway,
                'environment_id' => $environment_id,
                'audit_log_id' => $auditLog->id,
                'headers' => $request->header(),
                'payload' => $request->all(),
            ]);

            // Find the gateway settings for this environment
            $gatewaySettings = (is_numeric($environment_id)
                    ? PaymentGatewaySetting::where('environment_id', $environment_id)
                        ->where('code', $gateway)
                        ->first()
                    : null)
                ?: PaymentGatewaySetting::withoutGlobalScopes()
                    ->whereNull('environment_id')
                    ->where('code', $gateway)
                    ->where('status', true)
                    ->orderByDesc('is_default')
                    ->first();

            if (!$gatewaySettings) {
                Log::error('Gateway settings not found', [
                    'gateway' => $gateway,
                    'environment_id' => $environment_id
                ]);

                // Update audit log with error information
                $auditLog->update([
                    'status' => AuditLog::STATUS_ERROR,
                    'notes' => 'Gateway settings not found'
                ]);

                return response()->json(['error' => 'Gateway settings not found'], 404);
            }

            // Get the raw payload
            $payload = $request->getContent();
            $headers = $request->headers->all();

            $response = null;

            // Process the webhook based on the gateway
            switch ($gateway) {
                case 'stripe':
                    $response = $this->handleStripeWebhook($payload, $headers, $gatewaySettings);
                    break;

                case 'paypal':
                    $response = $this->handlePayPalWebhook($payload, $headers, $gatewaySettings);
                    break;

                case 'lygos':
                    $response = $this->handleLygosWebhook($payload, $headers, $gatewaySettings);
                    break;

                case 'monetbill':
                    $response = $this->handleMonetbillWebhook($payload, $headers, $gatewaySettings);
                    break;

                case 'taramoney':
                    $response = $this->handleTaraMoneyWebhook($payload, $headers, $gatewaySettings);
                    break;

                case 'moneroo':
                    $response = $this->handleMonerooWebhook($payload, $headers, $gatewaySettings);
                    break;

                default:
                    Log::error('Unsupported gateway for webhook', ['gateway' => $gateway]);

                    // Update audit log with error information
                    $auditLog->update([
                        'status' => AuditLog::STATUS_ERROR,
                        'notes' => 'Unsupported payment gateway'
                    ]);

                    return response()->json(['error' => 'Unsupported payment gateway'], 400);
            }

            // Update audit log with success information
            $auditLog->update([
                'status' => AuditLog::STATUS_SUCCESS,
                'response_data' => $response instanceof JsonResponse ? $response->getData(true) : null
            ]);

            return $response;
        } catch (\Exception $e) {
            Log::error('Error processing webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Update audit log with error information if we have one
            if (isset($auditLog)) {
                $auditLog->update([
                    'status' => AuditLog::STATUS_ERROR,
                    'notes' => 'Exception: ' . $e->getMessage()
                ]);
            }

            // Always return 200 to the gateway to prevent retries
            // This is a common practice with webhooks to avoid duplicate processing
            return response()->json(['status' => 'error', 'message' => 'Error processing webhook']);
        }
    }

    /**
     * Handle Stripe webhook events
     */
    private function handleStripeWebhook($payload, $headers, $gatewaySettings)
    {
        // Initialize Stripe with gateway settings
        $stripeSecretKey = $gatewaySettings->getSetting('api_key') ?? null;

        if (!$stripeSecretKey) {
            Log::error('Stripe api key(secret key) not found in gateway settings');
            return response()->json(['error' => 'Configuration error'], 500);
        }

        \Stripe\Stripe::setApiKey($stripeSecretKey);

        // Get the signature header
        $sigHeader = $headers['stripe-signature'][0] ?? '';
        $webhookSecret = $gatewaySettings->getSetting('webhook_secret') ?? null;

        try {
            // Verify the event
            if ($webhookSecret) {
                $event = \Stripe\Webhook::constructEvent($payload, $sigHeader, $webhookSecret);
            } else {
                // If no webhook secret, just decode the payload
                $event = json_decode($payload, true);
            }

            // Process the event based on its type
            $eventType = $event->type ?? $event['type'] ?? null;

            switch ($eventType) {
                case 'payment_intent.succeeded':
                case 'charge.succeeded':
                    return $this->handleStripePaymentSuccess($event->data->object ?? $event['data']['object'], $gatewaySettings);

                case 'payment_intent.payment_failed':
                case 'charge.failed':
                    return $this->handleStripePaymentFailure($event->data->object ?? $event['data']['object'], $gatewaySettings);

                case 'checkout.session.completed':
                    return $this->handleStripeCheckoutSessionCompleted($event->data->object ?? $event['data']['object'], $gatewaySettings);

                case 'invoice.payment_succeeded':
                    return $this->handleStripeInvoicePaymentSucceeded($event->data->object ?? $event['data']['object'], $gatewaySettings);

                case 'invoice.payment_failed':
                    return $this->handleStripeInvoicePaymentFailed($event->data->object ?? $event['data']['object'], $gatewaySettings);

                case 'customer.subscription.created':
                case 'customer.subscription.updated':
                    return $this->handleStripeSubscriptionUpdated($event->data->object ?? $event['data']['object'], $gatewaySettings);

                default:
                    // For unhandled events, just acknowledge receipt
                    return response()->json(['status' => 'success', 'message' => 'Unhandled event acknowledged']);
            }
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            Log::error('Invalid Stripe payload', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid payload'], 400);
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            Log::error('Invalid Stripe signature', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (\Exception $e) {
            // Other exceptions
            Log::error('Error processing Stripe webhook', ['error' => $e->getMessage()]);
            return response()->json(['error' => 'Webhook processing error'], 500);
        }
    }

    /**
     * Handle Stripe payment success event
     *
     * @param mixed $paymentIntent Payment intent object from Stripe webhook
     * @param PaymentGatewaySetting $gatewaySettings Payment gateway settings
     * @return \Illuminate\Http\JsonResponse
     */
    private function handleStripePaymentSuccess($paymentIntent, $gatewaySettings)
    {
        // Extract payment intent ID from object
        $paymentIntentId = $paymentIntent->id ?? $paymentIntent['id'] ?? null;

        if (!$paymentIntentId) {
            Log::error('Payment intent ID not found in Stripe webhook');
            return response()->json(['status' => 'error', 'message' => 'Invalid payload']);
        }

        // Find the transaction by gateway_transaction_id
        // Better way - use the transaction ID from metadata
        $metadata = $paymentIntent->metadata ?? $paymentIntent['metadata'] ?? [];
        $transactionId = $metadata['transaction_id'] ?? null;

        if ($transactionId) {
            $transaction = Transaction::where('id', $transactionId)
                ->where('environment_id', $gatewaySettings->environment_id)
                ->first();
            
            // If not found and this might be a supported plan, try global lookup
            if (!$transaction) {
                $globalTransaction = Transaction::where('id', $transactionId)->first();
                if ($globalTransaction && $this->isSupportedPlanPayment($globalTransaction)) {
                    $transaction = $globalTransaction;
                    Log::info('Stripe webhook: Supported plan transaction found with global lookup by ID', [
                        'transaction_id' => $transactionId,
                        'gateway_environment_id' => $gatewaySettings->environment_id,
                        'transaction_environment_id' => $transaction->environment_id
                    ]);
                }
            }
        } else {
            // Fallback to the old method with smart lookup
            $transaction = $this->findTransactionForWebhook($paymentIntentId, $gatewaySettings);
        }

        if (!$transaction) {
            Log::error('Transaction not found for Stripe payment intent', [
                'payment_intent_id' => $paymentIntentId,
                'environment_id' => $gatewaySettings->environment_id
            ]);
            return response()->json(['status' => 'success', 'message' => 'No matching transaction']);
        }

        $payload = is_array($paymentIntent) ? $paymentIntent : json_decode(json_encode($paymentIntent), true);
        $this->processCompletedWebhookTransaction(
            $transaction,
            $paymentIntent->status ?? $paymentIntent['status'] ?? 'succeeded',
            $payload ?: []
        );

        Log::info('Transaction marked as completed from webhook', [
            'transaction_id' => $transaction->id,
            'gateway_transaction_id' => $transaction->gateway_transaction_id
        ]);

        return response()->json(['status' => 'success', 'message' => 'Payment success processed']);
    }

    /**
     * Handle Stripe payment failure event
     *
     * @param mixed $paymentIntent Payment intent object from Stripe webhook
     * @param PaymentGatewaySetting $gatewaySettings Payment gateway settings
     * @return \Illuminate\Http\JsonResponse
     */
    private function handleStripePaymentFailure($paymentIntent, $gatewaySettings)
    {
        // Extract payment intent ID from object
        $paymentIntentId = $paymentIntent->id ?? $paymentIntent['id'] ?? null;

        if (!$paymentIntentId) {
            Log::error('Payment intent ID not found in Stripe webhook');
            return response()->json(['status' => 'error', 'message' => 'Invalid payload']);
        }

        // Find the transaction by gateway_transaction_id
        // Better way - use the transaction ID from metadata
        $metadata = $paymentIntent->metadata ?? $paymentIntent['metadata'] ?? [];
        $transactionId = $metadata['transaction_id'] ?? null;

        if ($transactionId) {
            $transaction = Transaction::where('id', $transactionId)
                ->where('environment_id', $gatewaySettings->environment_id)
                ->first();
            
            // If not found and this might be a supported plan, try global lookup
            if (!$transaction) {
                $globalTransaction = Transaction::where('id', $transactionId)->first();
                if ($globalTransaction && $this->isSupportedPlanPayment($globalTransaction)) {
                    $transaction = $globalTransaction;
                    Log::info('Stripe webhook: Supported plan transaction found with global lookup by ID (failure)', [
                        'transaction_id' => $transactionId,
                        'gateway_environment_id' => $gatewaySettings->environment_id,
                        'transaction_environment_id' => $transaction->environment_id
                    ]);
                }
            }
        } else {
            // Fallback to the old method with smart lookup
            $transaction = $this->findTransactionForWebhook($paymentIntentId, $gatewaySettings);
        }

        if (!$transaction) {
            Log::error('Transaction not found for Stripe payment intent', [
                'payment_intent_id' => $paymentIntentId,
                'environment_id' => $gatewaySettings->environment_id
            ]);
            return response()->json(['status' => 'success', 'message' => 'No matching transaction']);
        }

        // Get error information
        $errorMessage = '';
        if (isset($paymentIntent->last_payment_error)) {
            $errorMessage = $paymentIntent->last_payment_error->message ?? '';
        } elseif (isset($paymentIntent['last_payment_error'])) {
            $errorMessage = $paymentIntent['last_payment_error']['message'] ?? '';
        }

        $payload = is_array($paymentIntent) ? $paymentIntent : json_decode(json_encode($paymentIntent), true);
        $this->processFailedWebhookTransaction(
            $transaction,
            $paymentIntent->status ?? $paymentIntent['status'] ?? 'failed',
            $payload ?: [],
            $errorMessage ? 'Error: ' . $errorMessage : 'Payment failed'
        );

        Log::info('Transaction marked as failed from webhook', [
            'transaction_id' => $transaction->id,
            'gateway_transaction_id' => $transaction->gateway_transaction_id,
            'error' => $errorMessage
        ]);

        return response()->json(['status' => 'success', 'message' => 'Payment failure processed']);
    }

    /**
     * Handle Stripe checkout session completed event
     *
     * @param mixed $session Checkout session object from Stripe webhook
     * @param PaymentGatewaySetting $gatewaySettings Payment gateway settings
     * @return \Illuminate\Http\JsonResponse
     */
    private function handleStripeCheckoutSessionCompleted($session, $gatewaySettings)
    {
        // The payment_intent ID is nested in the checkout session
        $paymentIntentId = $session->payment_intent ?? $session['payment_intent'] ?? null;

        if (!$paymentIntentId) {
            // Try to find by checkout session ID instead
            $sessionId = $session->id ?? $session['id'] ?? null;
            if ($sessionId) {
                $transaction = Transaction::where('gateway_transaction_id', $sessionId)
                    ->where('environment_id', $gatewaySettings->environment_id)
                    ->first();

                if ($transaction) {
                    $transaction->status = Transaction::STATUS_COMPLETED;
                    $transaction->gateway_status = 'completed';
                    $transaction->gateway_response = json_encode($session);
                    $transaction->paid_at = now();
                    $transaction->save();

                    Log::info('Transaction marked as completed from checkout session', [
                        'transaction_id' => $transaction->id,
                        'session_id' => $sessionId
                    ]);
                    // Check if this is a subscription payment (For environement owners)
                    $payment = Payment::where('transaction_id', $transaction->transaction_id)->first();

                    if ($payment) {
                        // Update subscription status to active
                        $payment->status = Payment::STATUS_COMPLETED;
                        $payment->save();

                        //check if this is a subscription payment
                        $subscription = Subscription::where('id', $payment->subscription_id)->first();
                        if ($subscription) {
                            $subscription->status = Subscription::STATUS_ACTIVE;
                            $subscription->last_payment_at = now();

                            if ($subscription->billing_cycle == 'monthly') {
                                $subscription->next_payment_at = now()->addMonth();
                            } else {
                                $subscription->next_payment_at = now()->addYear();
                            }

                            //update end date of subcription
                            if ($subscription->billing_cycle == 'monthly') {
                                $subscription->ends_at = now()->addMonth();
                            } else {
                                $subscription->ends_at = now()->addYear();
                            }

                            $subscription->save();
                            /**
                             * To-do
                             * Send notification of subcription renewed and payment success /mail/telegram/database
                             */
                        }
                        Log::info('Payment activated', ['payment_id' => $payment->id]);
                    }

                    /**
                     * To-do
                     */

                    //implement check for EnrollementSubscription Payment


                    //check if this is an order payment
                    $order = Order::where('id', $transaction->order_id)->first();
                    if ($order) event(new \App\Events\OrderCompleted($order));

                    return response()->json(['status' => 'success', 'message' => 'Payment success processed']);
                }
            }

            Log::error('Payment intent ID not found in checkout session', [
                'session_id' => $session->id ?? $session['id'] ?? 'unknown'
            ]);
            return response()->json(['status' => 'error', 'message' => 'Payment intent not found']);
        }

        // Find the transaction by gateway_transaction_id
        // Better way - use the transaction ID from metadata
        $metadata = $paymentIntent->metadata ?? $paymentIntent['metadata'] ?? [];
        $transactionId = $metadata['transaction_id'] ?? null;

        if ($transactionId) {
            $transaction = Transaction::where('id', $transactionId)
                ->where('environment_id', $gatewaySettings->environment_id)
                ->first();
        } else {
            // Fallback to the old method
            $transaction = Transaction::where('gateway_transaction_id', $paymentIntentId)
                ->where('environment_id', $gatewaySettings->environment_id)
                ->first();
        }

        if (!$transaction) {
            Log::error('Transaction not found for Stripe checkout session', [
                'payment_intent_id' => $paymentIntentId,
                'environment_id' => $gatewaySettings->environment_id
            ]);
            return response()->json(['status' => 'error', 'message' => 'Transaction not found']);
        }

        // Update transaction status
        $transaction->status = Transaction::STATUS_COMPLETED;
        $transaction->gateway_status = 'completed';
        $transaction->gateway_response = json_encode($session);
        $transaction->paid_at = now();
        $transaction->save();

        Log::info('Transaction marked as completed from checkout session', [
            'transaction_id' => $transaction->id,
            'gateway_transaction_id' => $transaction->gateway_transaction_id
        ]);

        // Check if this is a subscription payment (For environement owners)
        $payment = Payment::where('transaction_id', $transaction->transaction_id)->first();

        if ($payment) {
            // Update subscription status to active
            $payment->status = Payment::STATUS_COMPLETED;
            $payment->save();

            //check if this is a subscription payment
            $subscription = Subscription::where('id', $payment->subscription_id)->first();
            if ($subscription) {
                $subscription->status = Subscription::STATUS_ACTIVE;
                $subscription->last_payment_at = now();

                if ($subscription->billing_cycle == 'monthly') {
                    $subscription->next_payment_at = now()->addMonth();
                } else {
                    $subscription->next_payment_at = now()->addYear();
                }

                //update end date of subcription
                if ($subscription->billing_cycle == 'monthly') {
                    $subscription->ends_at = now()->addMonth();
                } else {
                    $subscription->ends_at = now()->addYear();
                }

                $subscription->save();
                /**
                 * To-do
                 * Send notification of subcription renewed and payment success /mail/telegram/database
                 */
            }
            Log::info('Payment activated', ['payment_id' => $payment->id]);
        }

        /**
         * To-do
         */

        //implement check for EnrollementSubscription Payment


        //check if this is an order payment
        $order = Order::where('id', $transaction->order_id)->first();
        if ($order) event(new \App\Events\OrderCompleted($order));

        return response()->json(['status' => 'success', 'message' => 'Payment success processed']);
    }

    /**
     * Handle Stripe invoice payment succeeded event
     *
     * @param mixed $invoice Invoice object from Stripe webhook
     * @param PaymentGatewaySetting $gatewaySettings Payment gateway settings
     * @return \Illuminate\Http\JsonResponse
     */
    private function handleStripeInvoicePaymentSucceeded($invoice, $gatewaySettings)
    {
        // // For subscriptions, the invoice will have a subscription ID
        // $subscriptionId = $invoice->subscription ?? $invoice['subscription'] ?? null;

        // if (!$subscriptionId) {
        //     Log::error('Subscription ID not found in invoice', [
        //         'invoice_id' => $invoice->id ?? $invoice['id'] ?? 'unknown'
        //     ]);
        //     return response()->json(['status' => 'error', 'message' => 'Subscription ID not found']);
        // }

        // // Find subscription by gateway_subscription_id
        // $subscription = Subscription::where('gateway_subscription_id', $subscriptionId)
        //     ->where('environment_id', $gatewaySettings->environment_id)
        //     ->first();

        // if (!$subscription) {
        //     Log::error('Subscription not found for invoice', [
        //         'subscription_id' => $subscriptionId,
        //         'environment_id' => $gatewaySettings->environment_id
        //     ]);
        //     return response()->json(['status' => 'success', 'message' => 'No matching subscription']);
        // }

        // // Create a new transaction record for this invoice payment
        // $transaction = new Transaction();
        // $transaction->transaction_id = (string) Str::uuid();
        // $transaction->environment_id = $gatewaySettings->environment_id;
        // $transaction->payment_gateway_setting_id = $gatewaySettings->id;
        // $transaction->customer_id = $subscription->customer_id;
        // $transaction->amount = $invoice->amount_paid / 100 ?? $invoice['amount_paid'] / 100 ?? 0; // Convert from cents
        // $transaction->total_amount = $invoice->amount_paid / 100 ?? $invoice['amount_paid'] / 100 ?? 0;
        // $transaction->currency = $invoice->currency ?? $invoice['currency'] ?? 'usd';
        // $transaction->status = Transaction::STATUS_COMPLETED;
        // $transaction->payment_method = 'credit_card';
        // $transaction->gateway_transaction_id = $invoice->payment_intent ?? $invoice['payment_intent'] ?? $invoice->id ?? $invoice['id'];
        // $transaction->gateway_status = 'succeeded';
        // $transaction->description = 'Subscription payment for ' . $subscription->plan_name;
        // $transaction->gateway_response = json_encode($invoice);
        // $transaction->paid_at = now();
        // $transaction->save();

        // // Update subscription status if needed
        // if ($subscription->status !== 'active') {
        //     $subscription->status = 'active';
        //     $subscription->save();
        // }

        // // Update subscription's next_billing_date
        // if (isset($invoice->lines->data[0]->period->end) || isset($invoice['lines']['data'][0]['period']['end'])) {
        //     $periodEnd = $invoice->lines->data[0]->period->end ?? $invoice['lines']['data'][0]['period']['end'];
        //     $subscription->next_billing_date = date('Y-m-d H:i:s', $periodEnd);
        //     $subscription->save();

        //     Log::info('Subscription next billing date updated', [
        //         'subscription_id' => $subscription->id,
        //         'next_billing_date' => $subscription->next_billing_date
        //     ]);
        // }

        // Log::info('Invoice payment succeeded processed', [
        //     'subscription_id' => $subscription->id,
        //     'transaction_id' => $transaction->id
        // ]);

        // return response()->json(['status' => 'success', 'message' => 'Invoice payment succeeded processed']);
    }

    /**
     * Handle Stripe invoice payment failed event
     *
     * @param mixed $invoice Invoice object from Stripe webhook
     * @param PaymentGatewaySetting $gatewaySettings Payment gateway settings
     * @return \Illuminate\Http\JsonResponse
     */
    private function handleStripeInvoicePaymentFailed($invoice, $gatewaySettings)
    {
        // // For subscriptions, the invoice will have a subscription ID
        // $subscriptionId = $invoice->subscription ?? $invoice['subscription'] ?? null;

        // if (!$subscriptionId) {
        //     Log::error('Subscription ID not found in failed invoice', [
        //         'invoice_id' => $invoice->id ?? $invoice['id'] ?? 'unknown'
        //     ]);
        //     return response()->json(['status' => 'error', 'message' => 'Subscription ID not found']);
        // }

        // // Find subscription by gateway_subscription_id
        // $subscription = Subscription::where('gateway_subscription_id', $subscriptionId)
        //     ->where('environment_id', $gatewaySettings->environment_id)
        //     ->first();

        // if (!$subscription) {
        //     Log::error('Subscription not found for failed invoice', [
        //         'subscription_id' => $subscriptionId,
        //         'environment_id' => $gatewaySettings->environment_id
        //     ]);
        //     return response()->json(['status' => 'success', 'message' => 'No matching subscription']);
        // }

        // // Create a transaction record for this failed payment
        // $transaction = new Transaction();
        // $transaction->transaction_id = (string) Str::uuid();
        // $transaction->environment_id = $gatewaySettings->environment_id;
        // $transaction->payment_gateway_setting_id = $gatewaySettings->id;
        // $transaction->customer_id = $subscription->customer_id;
        // $transaction->amount = $invoice->amount_due / 100 ?? $invoice['amount_due'] / 100 ?? 0; // Convert from cents
        // $transaction->total_amount = $invoice->amount_due / 100 ?? $invoice['amount_due'] / 100 ?? 0;
        // $transaction->currency = $invoice->currency ?? $invoice['currency'] ?? 'usd';
        // $transaction->status = Transaction::STATUS_FAILED;
        // $transaction->payment_method = 'credit_card';
        // $transaction->gateway_transaction_id = $invoice->payment_intent ?? $invoice['payment_intent'] ?? $invoice->id ?? $invoice['id'];
        // $transaction->gateway_status = 'failed';
        // $transaction->description = 'Failed subscription payment for ' . $subscription->plan_name;
        // $transaction->gateway_response = json_encode($invoice);
        // $transaction->save();

        // // Update subscription status
        // $subscription->status = 'past_due';
        // $subscription->save();

        // Log::info('Invoice payment failed processed', [
        //     'subscription_id' => $subscription->id,
        //     'transaction_id' => $transaction->id
        // ]);

        // return response()->json(['status' => 'success', 'message' => 'Invoice payment failed processed']);
    }

    /**
     * Handle Stripe subscription updated event
     *
     * @param mixed $subscriptionObj Subscription object from Stripe webhook
     * @param PaymentGatewaySetting $gatewaySettings Payment gateway settings
     * @return \Illuminate\Http\JsonResponse
     */
    private function handleStripeSubscriptionUpdated($subscriptionObj, $gatewaySettings)
    {
        // // Get subscription ID
        // $subscriptionId = $subscriptionObj->id ?? $subscriptionObj['id'] ?? null;

        // if (!$subscriptionId) {
        //     Log::error('Subscription ID not found in subscription update');
        //     return response()->json(['status' => 'error', 'message' => 'Subscription ID not found']);
        // }

        // // Find subscription by gateway_subscription_id
        // $subscription = Subscription::where('gateway_subscription_id', $subscriptionId)
        //     ->where('environment_id', $gatewaySettings->environment_id)
        //     ->first();

        // if (!$subscription) {
        //     Log::error('Subscription not found for update', [
        //         'subscription_id' => $subscriptionId,
        //         'environment_id' => $gatewaySettings->environment_id
        //     ]);
        //     return response()->json(['status' => 'success', 'message' => 'No matching subscription']);
        // }

        // // Get subscription details from Stripe
        // $status = $subscriptionObj->status ?? $subscriptionObj['status'] ?? null;

        // if ($status) {
        //     // Map Stripe subscription status to our status
        //     $statusMap = [
        //         'active' => 'active',
        //         'canceled' => 'canceled',
        //         'incomplete' => 'pending',
        //         'incomplete_expired' => 'expired',
        //         'past_due' => 'past_due',
        //         'trialing' => 'trial',
        //         'unpaid' => 'past_due'
        //     ];

        //     $subscription->status = $statusMap[$status] ?? $status;

        //     // Update current_period_end if available
        //     if (isset($subscriptionObj->current_period_end) || isset($subscriptionObj['current_period_end'])) {
        //         $periodEnd = $subscriptionObj->current_period_end ?? $subscriptionObj['current_period_end'];
        //         $subscription->next_billing_date = date('Y-m-d H:i:s', $periodEnd);
        //     }

        //     // Get plan details if available
        //     if (isset($subscriptionObj->plan) || isset($subscriptionObj['plan'])) {
        //         $plan = $subscriptionObj->plan ?? $subscriptionObj['plan'];
        //         $planName = $plan->nickname ?? $plan['nickname'] ?? $plan->product ?? $plan['product'] ?? null;

        //         if ($planName) {
        //             $subscription->plan_name = $planName;
        //         }

        //         // Update amount if available
        //         if (isset($plan->amount) || isset($plan['amount'])) {
        //             $amount = $plan->amount ?? $plan['amount'];
        //             $subscription->amount = $amount / 100; // Convert from cents
        //         }

        //         // Update currency if available
        //         if (isset($plan->currency) || isset($plan['currency'])) {
        //             $subscription->currency = $plan->currency ?? $plan['currency'];
        //         }

        //         // Update interval if available
        //         if (isset($plan->interval) || isset($plan['interval'])) {
        //             $interval = $plan->interval ?? $plan['interval'];
        //             $intervalCount = $plan->interval_count ?? $plan['interval_count'] ?? 1;

        //             $subscription->plan_interval = $interval;
        //             $subscription->plan_interval_count = $intervalCount;
        //         }
        //     }

        //     $subscription->save();

        //     Log::info('Subscription updated from webhook', [
        //         'subscription_id' => $subscription->id,
        //         'status' => $subscription->status,
        //         'next_billing_date' => $subscription->next_billing_date
        //     ]);
        // }

        // return response()->json(['status' => 'success', 'message' => 'Subscription update processed']);
    }

    /**
     * Handle PayPal webhook notifications
     *
     * @param string $payload Raw webhook payload
     * @param array $headers Request headers
     * @param PaymentGatewaySetting $gatewaySettings Payment gateway settings
     * @return \Illuminate\Http\JsonResponse
     */
    private function handlePayPalWebhook($payload, $headers, $gatewaySettings)
    {
        //     Log::info('Processing PayPal webhook', ['environment_id' => $gatewaySettings->environment_id]);

        //     // Parse the payload
        //     $event = json_decode($payload, true);

        //     if (!$event || !isset($event['event_type'])) {
        //         Log::error('Invalid PayPal webhook payload');
        //         return response()->json(['error' => 'Invalid payload'], 400);
        //     }

        //     // Basic verification of webhook signature (can be expanded with PayPal SDK)
        //     // For now, just log the event type
        //     Log::info('PayPal webhook event received', ['event_type' => $event['event_type']]);

        //     // Handle different event types
        //     switch ($event['event_type']) {
        //         case 'PAYMENT.CAPTURE.COMPLETED':
        //             // Payment successfully captured
        //             $resource = $event['resource'] ?? [];
        //             $paymentId = $resource['id'] ?? null;

        //             if ($paymentId) {
        //                 // Find the transaction by gateway_transaction_id
        //                 $transaction = Transaction::where('gateway_transaction_id', $paymentId)
        //                     ->where('environment_id', $gatewaySettings->environment_id)
        //                     ->first();

        //                 if ($transaction) {
        //                     // Update transaction status
        //                     $transaction->status = Transaction::STATUS_COMPLETED;
        //                     $transaction->gateway_status = 'COMPLETED';
        //                     $transaction->gateway_response = json_encode($event);
        //                     $transaction->paid_at = now();
        //                     $transaction->save();

        //                     Log::info('PayPal transaction marked as completed', [
        //                         'transaction_id' => $transaction->id,
        //                         'gateway_transaction_id' => $paymentId
        //                     ]);
        //                 }
        //             }
        //             break;

        //         case 'PAYMENT.CAPTURE.DENIED':
        //         case 'PAYMENT.CAPTURE.DECLINED':
        //         case 'PAYMENT.CAPTURE.REFUNDED':
        //             // Payment failed or was refunded
        //             $resource = $event['resource'] ?? [];
        //             $paymentId = $resource['id'] ?? null;

        //             if ($paymentId) {
        //                 // Find the transaction by gateway_transaction_id
        //                 $transaction = Transaction::where('gateway_transaction_id', $paymentId)
        //                     ->where('environment_id', $gatewaySettings->environment_id)
        //                     ->first();

        //                 if ($transaction) {
        //                     // Update transaction status
        //                     $transaction->status = Transaction::STATUS_FAILED;
        //                     $transaction->gateway_status = $event['event_type'];
        //                     $transaction->gateway_response = json_encode($event);
        //                     $transaction->notes = 'Payment ' . strtolower(str_replace('PAYMENT.CAPTURE.', '', $event['event_type']));
        //                     $transaction->save();

        //                     Log::info('PayPal transaction marked as failed/refunded', [
        //                         'transaction_id' => $transaction->id,
        //                         'gateway_transaction_id' => $paymentId,
        //                         'event_type' => $event['event_type']
        //                     ]);
        //                 }
        //             }
        //             break;

        //         case 'BILLING.SUBSCRIPTION.CREATED':
        //         case 'BILLING.SUBSCRIPTION.ACTIVATED':
        //         case 'BILLING.SUBSCRIPTION.UPDATED':
        //             // Subscription was created or updated
        //             $resource = $event['resource'] ?? [];
        //             $subscriptionId = $resource['id'] ?? null;

        //             if ($subscriptionId) {
        //                 // Find subscription by gateway_subscription_id
        //                 $subscription = Subscription::where('gateway_subscription_id', $subscriptionId)
        //                     ->where('environment_id', $gatewaySettings->environment_id)
        //                     ->first();

        //                 if ($subscription) {
        //                     // Get status from PayPal event
        //                     $status = $resource['status'] ?? '';

        //                     // Map PayPal status to our status
        //                     $statusMap = [
        //                         'ACTIVE' => 'active',
        //                         'APPROVAL_PENDING' => 'pending',
        //                         'APPROVED' => 'pending',
        //                         'SUSPENDED' => 'past_due',
        //                         'CANCELLED' => 'cancelled',
        //                         'EXPIRED' => 'expired'
        //                     ];

        //                     $subscription->status = $statusMap[$status] ?? $subscription->status;

        //                     // Update billing details if available
        //                     if (isset($resource['billing_info'])) {
        //                         $billingInfo = $resource['billing_info'];

        //                         // Next billing date
        //                         if (isset($billingInfo['next_billing_time'])) {
        //                             $subscription->next_billing_date = date('Y-m-d H:i:s', strtotime($billingInfo['next_billing_time']));
        //                         }

        //                         // Last payment date
        //                         if (isset($billingInfo['last_payment']['time'])) {
        //                             $subscription->last_payment_date = date('Y-m-d H:i:s', strtotime($billingInfo['last_payment']['time']));
        //                         }
        //                     }

        //                     $subscription->save();

        //                     Log::info('PayPal subscription updated', [
        //                         'subscription_id' => $subscription->id,
        //                         'status' => $subscription->status
        //                     ]);
        //                 }
        //             }
        //             break;

        //         case 'BILLING.SUBSCRIPTION.CANCELLED':
        //             // Subscription was cancelled
        //             $resource = $event['resource'] ?? [];
        //             $subscriptionId = $resource['id'] ?? null;

        //             if ($subscriptionId) {
        //                 // Find subscription by gateway_subscription_id
        //                 $subscription = Subscription::where('gateway_subscription_id', $subscriptionId)
        //                     ->where('environment_id', $gatewaySettings->environment_id)
        //                     ->first();

        //                 if ($subscription) {
        //                     $subscription->status = 'cancelled';
        //                     $subscription->cancelled_at = now();
        //                     $subscription->cancellation_reason = 'Cancelled via PayPal';
        //                     $subscription->save();

        //                     Log::info('PayPal subscription cancelled', [
        //                         'subscription_id' => $subscription->id
        //                     ]);
        //                 }
        //             }
        //             break;
        //     }

        //     // Return 200 OK to acknowledge receipt of the webhook
        //     return response()->json(['status' => 'success', 'message' => 'PayPal webhook received']);
        // 
    }

    /**
     * Handle Lygos webhook notifications
     *
     * @param string $payload Raw webhook payload
     * @param array $headers Request headers
     * @param PaymentGatewaySetting $gatewaySettings Payment gateway settings
     * @return \Illuminate\Http\JsonResponse
     */
    private function handleLygosWebhook($payload, $headers, $gatewaySettings)
    {
        // Log::info('Processing Lygos webhook', ['environment_id' => $gatewaySettings->environment_id]);

        // // Parse the payload
        // $event = json_decode($payload, true);

        // if (!$event) {
        //     Log::error('Invalid Lygos webhook payload');
        //     return response()->json(['error' => 'Invalid payload'], 400);
        // }

        // // Extract event type and transaction reference
        // $eventType = $event['type'] ?? $event['event'] ?? null;
        // $reference = $event['reference'] ?? $event['transaction_reference'] ?? null;

        // if (!$eventType || !$reference) {
        //     Log::error('Missing required fields in Lygos webhook', [
        //         'event_type' => $eventType,
        //         'reference' => $reference
        //     ]);
        //     return response()->json(['error' => 'Missing required fields'], 400);
        // }

        // // Find the transaction by gateway_transaction_id
        // $transaction = Transaction::where('gateway_transaction_id', $reference)
        //     ->where('environment_id', $gatewaySettings->environment_id)
        //     ->first();

        // if (!$transaction) {
        //     Log::error('Transaction not found for Lygos webhook', [
        //         'reference' => $reference,
        //         'event_type' => $eventType
        //     ]);
        //     return response()->json(['status' => 'error', 'message' => 'Transaction not found']);
        // }

        // // Process based on event type
        // switch ($eventType) {
        //     case 'payment.success':
        //     case 'payment_success':
        //         $transaction->status = Transaction::STATUS_COMPLETED;
        //         $transaction->gateway_status = 'success';
        //         $transaction->gateway_response = json_encode($event);
        //         $transaction->paid_at = now();
        //         $transaction->save();

        //         Log::info('Lygos payment success processed', [
        //             'transaction_id' => $transaction->id,
        //             'gateway_transaction_id' => $reference
        //         ]);
        //         break;

        //     case 'payment.failed':
        //     case 'payment_failed':
        //         $transaction->status = Transaction::STATUS_FAILED;
        //         $transaction->gateway_status = 'failed';
        //         $transaction->gateway_response = json_encode($event);
        //         $transaction->notes = 'Payment failed: ' . ($event['reason'] ?? 'Unknown reason');
        //         $transaction->save();

        //         Log::info('Lygos payment failure processed', [
        //             'transaction_id' => $transaction->id,
        //             'gateway_transaction_id' => $reference
        //         ]);
        //         break;

        //     default:
        //         Log::info('Unhandled Lygos event type', [
        //             'event_type' => $eventType,
        //             'transaction_id' => $transaction->id
        //         ]);
        // }

        // return response()->json(['status' => 'success', 'message' => 'Lygos webhook processed']);
    }

    /**
     * Handle Monetbill webhook notifications
     *
     * @param string $payload Raw webhook payload
     * @param array $headers Request headers
     * @param PaymentGatewaySetting $gatewaySettings Payment gateway settings
     * @return \Illuminate\Http\JsonResponse
     */
    private function handleMonetbillWebhook($payload, $headers, $gatewaySettings)
    {
        Log::info('Processing Monetbill webhook', [
            'environment_id' => $gatewaySettings->environment_id,
            "headers" => $headers,
            "payload" => $payload
        ]);

        // Monetbill typically sends data as form parameters in $_GET or $_POST, not JSON
        // First check if we have a JSON payload
        $event = json_decode($payload, true) ?: [];

        // If event is empty, check for GET parameters
        if (empty($event) && !empty($_GET)) {
            Log::info('Monetbill webhook using GET parameters', ['params' => $_GET]);
            $event = $_GET;
        }

        // If still empty, check for POST parameters
        if (empty($event) && !empty($_POST)) {
            Log::info('Monetbill webhook using POST parameters', ['params' => $_POST]);
            $event = $_POST;
        }

        if (!$this->monetbill_check_sign($event, $gatewaySettings)) {
            Log::warning('Rejected Monetbill webhook with invalid signature', [
                'environment_id' => $gatewaySettings->environment_id,
                'reference' => $event['payment_ref'] ?? null,
            ]);

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Get the transaction reference
        $reference = $event['payment_ref'] ?? null;
        $status = $event['status'] ?? null;

        if (!$reference) {
            Log::error('Missing transaction reference in Monetbill webhook');
            return response()->json(['error' => 'Missing transaction reference'], 400);
        }


        // Find the transaction using smart lookup that handles cross-environment supported plan transactions
        $transaction = $this->findTransactionForWebhook($reference, $gatewaySettings);

        if (!$transaction) {
            Log::error('Transaction not found for Monetbill webhook', [
                'reference' => $reference
            ]);
            return response()->json(['status' => 'error', 'message' => 'Transaction not found']);
        }

        $normalizedStatus = strtolower((string) $status);

        // Process based on status
        if (in_array($normalizedStatus, ['successful', 'success', '1'], true)) {
            $this->processCompletedWebhookTransaction($transaction, 'success', $event);
            Log::info('Monetbill payment success processed', [
                'transaction_id' => $transaction->id,
                'gateway_transaction_id' => $reference
            ]);
        } elseif (in_array($normalizedStatus, ['failed', 'failure', '0'], true)) {
            $this->processFailedWebhookTransaction(
                $transaction,
                'failed',
                $event,
                'Payment failed: ' . ($event['message'] ?? 'Unknown reason')
            );
            Log::info('Monetbill payment failure processed', [
                'transaction_id' => $transaction->id,
                'gateway_transaction_id' => $reference
            ]);
        } else {
            Log::info('Unhandled Monetbill status', [
                'status' => $status,
                'transaction_id' => $transaction->id
            ]);
        }

        return response()->json(['status' => 'success', 'message' => 'Monetbill webhook processed']);
    }

    /**
     * Handle TaraMoney webhook notifications
     *
     * @param string $payload Raw webhook payload
     * @param array $headers Request headers
     * @param PaymentGatewaySetting $gatewaySettings Payment gateway settings
     * @return \Illuminate\Http\JsonResponse
     */
    private function handleTaraMoneyWebhook($payload, $headers, $gatewaySettings)
    {
        Log::info('Processing TaraMoney webhook', [
            'environment_id' => $gatewaySettings->environment_id,
            "headers" => $headers,
            "payload" => $payload
        ]);

        // TaraMoney sends data as JSON payload
        $event = json_decode($payload, true) ?: [];

        // If event is empty, check for POST parameters
        if (empty($event) && !empty($_POST)) {
            Log::info('TaraMoney webhook using POST parameters', ['params' => $_POST]);
            $event = $_POST;
        }

        if (!$this->verifyTaraMoneyWebhookSignature($payload, $headers, $gatewaySettings, $event)) {
            Log::warning('Rejected TaraMoney webhook with invalid signature', [
                'environment_id' => $gatewaySettings->environment_id,
                'payment_id' => $event['paymentId'] ?? null,
            ]);

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        // Get the transaction reference (paymentId from webhook)
        $paymentId = $event['paymentId'] ?? null;
        $productId = $event['productId'] ?? null;
        $status = $event['status'] ?? null;

        if (!$paymentId) {
            Log::error('Missing payment ID in TaraMoney webhook');
            return response()->json(['error' => 'Missing payment ID'], 400);
        }

        // Find the transaction using smart lookup that handles cross-environment transactions
        // TaraMoney sends paymentId in webhook, which maps to our gateway_transaction_id or transaction_id
        $transaction = $this->findTransactionForWebhook($paymentId, $gatewaySettings);

        if (!$transaction && $productId) {
            $transaction = $this->findTransactionForWebhook($productId, $gatewaySettings);
        }

        if (!$transaction) {
            Log::error('Transaction not found for TaraMoney webhook', [
                'payment_id' => $paymentId,
                'product_id' => $productId,
                'environment_id' => $gatewaySettings->environment_id
            ]);
            return response()->json(['status' => 'error', 'message' => 'Transaction not found']);
        }

        $normalizedStatus = strtolower((string) $status);

        // Process based on status
        if ($normalizedStatus === 'success') {
            $this->processCompletedWebhookTransaction($transaction, 'success', $event);
            Log::info('TaraMoney payment success processed', [
                'transaction_id' => $transaction->id,
                'gateway_transaction_id' => $paymentId
            ]);
        } elseif (in_array($normalizedStatus, ['failure', 'failed'], true)) {
            $this->processFailedWebhookTransaction($transaction, 'failed', $event, 'Payment failed');
            Log::info('TaraMoney payment failure processed', [
                'transaction_id' => $transaction->id,
                'gateway_transaction_id' => $paymentId
            ]);
        } else {
            Log::info('Unhandled TaraMoney status', [
                'status' => $status,
                'transaction_id' => $transaction->id
            ]);
        }

        return response()->json(['status' => 'success', 'message' => 'TaraMoney webhook processed']);
    }

    private function handleMonerooWebhook($payload, $headers, $gatewaySettings)
    {
        Log::info('Processing Moneroo webhook', [
            'environment_id' => $gatewaySettings->environment_id,
            'headers' => $headers,
            'payload' => $payload,
        ]);

        $event = json_decode($payload, true) ?: request()->all();

        if (!$this->verifyMonerooWebhookSignature($payload, $headers, $gatewaySettings)) {
            Log::warning('Rejected Moneroo webhook with invalid signature', [
                'environment_id' => $gatewaySettings->environment_id,
                'reference' => $event['id'] ?? $event['transaction_id'] ?? null,
            ]);

            return response()->json(['error' => 'Invalid signature'], 400);
        }

        $reference = $event['metadata']['transaction_id']
            ?? $event['transaction_id']
            ?? $event['payment_id']
            ?? $event['id']
            ?? $event['reference']
            ?? null;
        $status = strtolower((string) ($event['status'] ?? $event['payment_status'] ?? $event['data']['status'] ?? ''));

        if (!$reference) {
            Log::error('Missing transaction reference in Moneroo webhook', ['payload' => $event]);
            return response()->json(['error' => 'Missing transaction reference'], 400);
        }

        $transaction = $this->findTransactionForWebhook($reference, $gatewaySettings);

        if (!$transaction) {
            Log::error('Transaction not found for Moneroo webhook', [
                'reference' => $reference,
                'gateway_transaction_id' => $event['id'] ?? null,
            ]);
            return response()->json(['status' => 'error', 'message' => 'Transaction not found']);
        }

        if (empty($transaction->gateway_transaction_id) && !empty($event['id'])) {
            $transaction->gateway_transaction_id = $event['id'];
            $transaction->save();
        }

        if (in_array($status, ['success', 'successful', 'succeeded', 'paid', 'completed'], true)) {
            $this->processCompletedWebhookTransaction($transaction, $status ?: 'completed', $event);
        } elseif (in_array($status, ['failed', 'failure', 'cancelled', 'canceled', 'expired'], true)) {
            $this->processFailedWebhookTransaction($transaction, $status, $event, 'Moneroo payment failed');
        } else {
            Log::info('Unhandled Moneroo payment status', [
                'status' => $status,
                'transaction_id' => $transaction->transaction_id,
            ]);
        }

        return response()->json(['status' => 'success', 'message' => 'Moneroo webhook processed']);
    }

    private function verifyMonerooWebhookSignature(string $payload, array $headers, PaymentGatewaySetting $gatewaySettings): bool
    {
        $secret = $gatewaySettings->getSetting('webhook_secret');

        if (empty($secret)) {
            Log::warning('[MonerooWebhook] No webhook secret configured; accepting webhook without signature verification');
            return true;
        }

        $signature = $headers['x-moneroo-signature'][0]
            ?? $headers['moneroo-signature'][0]
            ?? $headers['x-signature'][0]
            ?? $headers['signature'][0]
            ?? $headers['x-hub-signature-256'][0]
            ?? null;

        if (!$signature) {
            return false;
        }

        $signature = preg_replace('/^sha256=/i', '', (string) $signature);
        $expected = hash_hmac('sha256', $payload, $secret);

        return hash_equals($expected, $signature);
    }

    private function verifyTaraMoneyWebhookSignature(string $payload, array $headers, PaymentGatewaySetting $gatewaySettings, array $event): bool
    {
        $secret = $gatewaySettings->getSetting('webhook_secret');

        if (empty($secret)) {
            Log::warning('[TaraMoneyWebhook] No webhook secret configured; accepting webhook without signature verification');
            return true;
        }

        $signature = $headers['x-taramoney-signature'][0]
            ?? $headers['x-signature'][0]
            ?? $headers['signature'][0]
            ?? $headers['x-hub-signature-256'][0]
            ?? $event['signature']
            ?? $event['sign']
            ?? null;

        if (!$signature) {
            $businessId = $gatewaySettings->getSetting('business_id');
            $payloadBusinessId = $event['businessId'] ?? null;

            if ($businessId && $payloadBusinessId && hash_equals((string) $businessId, (string) $payloadBusinessId)) {
                Log::info('[TaraMoneyWebhook] No signature provided by Tara; businessId matched configured gateway');
                return true;
            }

            Log::error('[TaraMoneyWebhook] Missing signature and businessId did not match configured gateway', [
                'business_id_present' => !empty($businessId),
                'payload_business_id_present' => !empty($payloadBusinessId),
            ]);
            return false;
        }

        $signature = preg_replace('/^sha256=/i', '', (string) $signature);
        $signedPayload = $payload !== '' ? $payload : json_encode($event);
        $expected = hash_hmac('sha256', $signedPayload, $secret);

        if (hash_equals($expected, $signature)) {
            return true;
        }

        Log::error('[TaraMoneyWebhook] Invalid signature', [
            'received' => $signature,
        ]);

        return false;
    }

    private function processCompletedWebhookTransaction(Transaction $transaction, string $gatewayStatus, array $payload): void
    {
        DB::transaction(function () use ($transaction, $gatewayStatus, $payload) {
            $transaction = Transaction::withoutGlobalScopes()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            $alreadyCompleted = $transaction->status === Transaction::STATUS_COMPLETED;

            $transaction->status = Transaction::STATUS_COMPLETED;
            $transaction->gateway_status = $gatewayStatus;
            $transaction->gateway_response = $payload;
            $transaction->paid_at = $transaction->paid_at ?: now();
            $transaction->save();
            $transaction->refresh();

            $this->createCommissionRecordIfNeeded($transaction);
            $this->completeRelatedPaymentIfNeeded($transaction, $payload);
            $this->markInvoicePaidIfNeeded($transaction);

            if (!$alreadyCompleted) {
                $this->completeOrderIfNeeded($transaction);
            }
        });
    }

    private function processFailedWebhookTransaction(Transaction $transaction, string $gatewayStatus, array $payload, string $notes): void
    {
        DB::transaction(function () use ($transaction, $gatewayStatus, $payload, $notes) {
            $transaction = Transaction::withoutGlobalScopes()
                ->whereKey($transaction->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($transaction->status === Transaction::STATUS_COMPLETED) {
                Log::warning('Ignoring failure webhook for already completed transaction', [
                    'transaction_id' => $transaction->transaction_id,
                    'gateway_status' => $gatewayStatus,
                ]);
                return;
            }

            $transaction->status = Transaction::STATUS_FAILED;
            $transaction->gateway_status = $gatewayStatus;
            $transaction->gateway_response = $payload;
            $transaction->notes = $notes;
            $transaction->save();

            $payment = Payment::where('transaction_id', $transaction->transaction_id)->first();
            if ($payment && $payment->status !== Payment::STATUS_COMPLETED) {
                $payment->markAsFailed(
                    $transaction->gateway_transaction_id,
                    $gatewayStatus,
                    $payload
                );
            }
        });
    }

    private function createCommissionRecordIfNeeded(Transaction $transaction): void
    {
        if (!$transaction->order_id) {
            return;
        }

        try {
            $config = app(\App\Services\EnvironmentPaymentConfigService::class)->getConfig($transaction->environment_id);

            if (!$config || !$config->use_centralized_gateways) {
                return;
            }

            if (InstructorCommission::where('transaction_id', $transaction->id)->exists()) {
                return;
            }

            app(\App\Services\InstructorCommissionService::class)->createCommissionRecord($transaction);
            Log::info('Commission record created for centralized payment', [
                'transaction_id' => $transaction->id,
                'environment_id' => $transaction->environment_id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create commission record', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function completeRelatedPaymentIfNeeded(Transaction $transaction, array $payload): void
    {
        $payment = Payment::where('transaction_id', $transaction->transaction_id)->first();

        if (!$payment) {
            return;
        }

        $paymentAlreadyCompleted = $payment->status === Payment::STATUS_COMPLETED;

        $payment->markAsCompleted(
            $transaction->gateway_transaction_id,
            $transaction->gateway_status,
            $payload
        );

        if ($paymentAlreadyCompleted) {
            Log::info('Payment already completed; skipping subscription date update', ['payment_id' => $payment->id]);
            return;
        }

        $subscription = Subscription::where('id', $payment->subscription_id)->first();
        if (!$subscription) {
            Log::info('Payment activated', ['payment_id' => $payment->id]);
            return;
        }

        $metadata = $payment->metadata ?? [];

        if (($metadata['type'] ?? null) === 'subscription_plan_change' && !empty($metadata['new_plan_id'])) {
            $subscription->plan_id = $metadata['new_plan_id'];
            $subscription->billing_cycle = $metadata['billing_cycle'] ?? $subscription->billing_cycle;
        }

        $subscription->status = Subscription::STATUS_ACTIVE;
        $subscription->last_payment_at = now();
        $subscription->next_payment_at = $subscription->billing_cycle === 'annual' ? now()->addYear() : now()->addMonth();
        $subscription->ends_at = $subscription->billing_cycle === 'annual' ? now()->addYear() : now()->addMonth();
        $subscription->save();

        Log::info('Payment activated', ['payment_id' => $payment->id]);
    }

    private function markInvoicePaidIfNeeded(Transaction $transaction): void
    {
        if (!$transaction->invoice_id) {
            return;
        }

        Invoice::where('id', $transaction->invoice_id)->update([
            'status' => 'paid',
            'paid_at' => now(),
            'payment_gateway' => $transaction->payment_method,
            'payment_link' => null,
        ]);
    }

    private function completeOrderIfNeeded(Transaction $transaction): void
    {
        $order = Order::where('id', $transaction->order_id)->first();

        if (!$order || $order->status === Order::STATUS_COMPLETED) {
            return;
        }

        event(new \App\Events\OrderCompleted($order));
    }

    /**
     * Display a listing of transactions.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/transactions",
     *     summary="Get list of transactions",
     *     description="Returns paginated list of transactions with optional filtering",
     *     operationId="getTransactionsList",
     *     tags={"Transactions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="environment_id",
     *         in="query",
     *         description="Filter by environment ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="payment_gateway_id",
     *         in="query",
     *         description="Filter by payment gateway ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="order_id",
     *         in="query",
     *         description="Filter by order ID",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="customer_id",
     *         in="query",
     *         description="Filter by customer ID",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by transaction status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pending", "processing", "completed", "failed", "refunded", "partially_refunded"})
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Filter transactions created after this date (format: Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Filter transactions created before this date (format: Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="sort_field",
     *         in="query",
     *         description="Field to sort by",
     *         required=false,
     *         @OA\Schema(type="string", enum={"created_at", "amount", "status"}, default="created_at")
     *     ),
     *     @OA\Parameter(
     *         name="sort_direction",
     *         in="query",
     *         description="Sort direction",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}, default="desc")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/Transaction")
     *                 ),
     *                 @OA\Property(property="total", type="integer", example=50),
     *                 @OA\Property(property="per_page", type="integer", example=15)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission"
     *     )
     * )
     */
    public function index(Request $request)
    {
        // Validate request parameters
        $validator = Validator::make($request->all(), [
            'environment_id' => 'nullable|integer|exists:environments,id',
            'payment_gateway_id' => 'nullable|integer|exists:payment_gateway_settings,id',
            'transaction_id' => 'nullable|string',
            'order_id' => 'nullable|string',
            'customer_id' => 'nullable|string',
            'status' => 'nullable|in:pending,processing,completed,failed,refunded,partially_refunded',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d',
            'sort_field' => 'nullable|in:created_at,amount,status',
            'sort_direction' => 'nullable|in:asc,desc',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Build query with filters
        $query = Transaction::query();
        
        // Eager load order and user
        $query = $query->with(['order.user'], 'user');

        // Apply environment filter if provided
        if ($request->has('environment_id')) {
            $query->where('environment_id', $request->environment_id);
        }

        // Apply payment gateway filter if provided
        if ($request->has('payment_gateway_id')) {
            $query->where('payment_gateway_setting_id', $request->payment_gateway_id);
        }

        if ($request->has('transaction_id')) {
            $query->where(function ($query) use ($request) {
                $query->where('transaction_id', $request->transaction_id)
                    ->orWhere('gateway_transaction_id', $request->transaction_id);
            });
        }

        // Apply order filter if provided
        if ($request->has('order_id')) {
            $query->where('order_id', $request->order_id);
        }

        // Apply customer filter if provided
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        // Apply status filter if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Apply date range filters if provided
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Apply sorting
        $sortField = $request->input('sort_field', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortField, $sortDirection);

        // Get paginated results
        $perPage = $request->input('per_page', 15);
        $transactions = $query->paginate($perPage);

        return response()->json([
            'status' => 'success',
            'data' => $transactions,
        ], Response::HTTP_OK);
    }

    /**
     * Store a newly created transaction.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/transactions",
     *     summary="Create a new transaction",
     *     description="Creates a new transaction with the provided data",
     *     operationId="storeTransaction",
     *     tags={"Transactions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Transaction data",
     *         @OA\JsonContent(
     *             required={"environment_id", "amount", "currency"},
     *             @OA\Property(property="environment_id", type="integer", example=1),
     *             @OA\Property(property="payment_gateway_setting_id", type="integer", example=1, nullable=true),
     *             @OA\Property(property="order_id", type="string", example="ORD-2025-0001", nullable=true),
     *             @OA\Property(property="invoice_id", type="string", example="INV-2025-0001", nullable=true),
     *             @OA\Property(property="customer_id", type="string", example="cus_123456", nullable=true),
     *             @OA\Property(property="customer_email", type="string", example="john@example.com", nullable=true),
     *             @OA\Property(property="customer_name", type="string", example="John Doe", nullable=true),
     *             @OA\Property(property="amount", type="number", format="float", example=99.99),
     *             @OA\Property(property="fee_amount", type="number", format="float", example=2.9, nullable=true),
     *             @OA\Property(property="tax_amount", type="number", format="float", example=5.0, nullable=true),
     *             @OA\Property(property="currency", type="string", example="USD"),
     *             @OA\Property(property="payment_method", type="string", example="credit_card", nullable=true),
     *             @OA\Property(property="payment_method_details", type="string", example="Visa **** 4242", nullable=true),
     *             @OA\Property(property="description", type="string", example="Payment for order #ORD-2025-0001", nullable=true),
     *             @OA\Property(property="notes", type="string", example="Customer requested express shipping", nullable=true),
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Transaction created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Transaction created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Transaction")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid input data"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request)
    {
        // Validate request data
        $validator = Validator::make($request->all(), [
            'environment_id' => 'required|integer|exists:environments,id',
            'payment_gateway_setting_id' => 'nullable|integer|exists:payment_gateway_settings,id',
            'order_id' => 'nullable|string|max:255',
            'invoice_id' => 'nullable|string|max:255',
            'customer_id' => 'nullable|string|max:255',
            'customer_email' => 'nullable|email|max:255',
            'customer_name' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'fee_amount' => 'nullable|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'currency' => 'required|string|size:3',
            'payment_method' => 'nullable|string|max:255',
            'payment_method_details' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Create transaction
        $data = $validator->validated();

        // Generate a UUID for the transaction
        $data['transaction_id'] = (string) Str::uuid();

        // Set default status to pending
        $data['status'] = Transaction::STATUS_PENDING;

        // Calculate total amount if not provided
        $data['fee_amount'] = $data['fee_amount'] ?? 0;
        $data['tax_amount'] = $data['tax_amount'] ?? 0;
        $data['total_amount'] = $data['amount'] + $data['fee_amount'] + $data['tax_amount'];

        // Add created_by field
        $data['created_by'] = Auth::id();

        $transaction = Transaction::create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Transaction created successfully',
            'data' => $transaction
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified transaction.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/transactions/{id}",
     *     summary="Get transaction details",
     *     description="Returns details of a specific transaction",
     *     operationId="getTransactionById",
     *     tags={"Transactions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Transaction ID",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/Transaction")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Transaction not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function show($id)
    {
        $transaction = Transaction::with('paymentGatewaySetting')->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $transaction
        ], Response::HTTP_OK);
    }

    /**
     * Update the transaction status.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * 
     * @OA\Put(
     *     path="/transactions/{id}/status",
     *     summary="Update transaction status",
     *     description="Updates the status of an existing transaction",
     *     operationId="updateTransactionStatus",
     *     tags={"Transactions"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Transaction ID",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Transaction status data",
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 enum={"pending", "processing", "completed", "failed", "refunded", "partially_refunded"},
     *                 example="completed"
     *             ),
     *             @OA\Property(property="gateway_transaction_id", type="string", example="ch_123456789", nullable=true),
     *             @OA\Property(property="gateway_status", type="string", example="succeeded", nullable=true),
     *             @OA\Property(property="gateway_response", type="object", nullable=true),
     *             @OA\Property(property="notes", type="string", example="Payment confirmed", nullable=true),
     *             @OA\Property(property="refund_reason", type="string", example="Customer requested refund", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transaction status updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Transaction status updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Transaction")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid input data"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Transaction not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function updateStatus(Request $request, $id)
    {
        $transaction = Transaction::findOrFail($id);

        // Validate request data
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:pending,processing,completed,failed,refunded,partially_refunded',
            'gateway_transaction_id' => 'nullable|string|max:255',
            'gateway_status' => 'nullable|string|max:255',
            'gateway_response' => 'nullable|json',
            'notes' => 'nullable|string',
            'refund_reason' => 'nullable|string|required_if:status,refunded,partially_refunded',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $data = $validator->validated();
        $newStatus = $data['status'];

        // Add updated_by field
        $data['updated_by'] = Auth::id();

        // Handle status-specific updates
        switch ($newStatus) {
            case Transaction::STATUS_COMPLETED:
                $transaction->markAsCompleted(
                    $data['gateway_transaction_id'] ?? null,
                    $data['gateway_status'] ?? null,
                    $data['gateway_response'] ?? null
                );
                break;

            case Transaction::STATUS_FAILED:
                $transaction->markAsFailed(
                    $data['gateway_transaction_id'] ?? null,
                    $data['gateway_status'] ?? null,
                    $data['gateway_response'] ?? null
                );
                break;

            case Transaction::STATUS_REFUNDED:
            case Transaction::STATUS_PARTIALLY_REFUNDED:
                $transaction->markAsRefunded(
                    $data['refund_reason'] ?? null,
                    $data['gateway_transaction_id'] ?? null,
                    $data['gateway_status'] ?? null,
                    $data['gateway_response'] ?? null
                );
                break;

            default:
                // For other statuses, just update the fields
                $transaction->status = $newStatus;
                if (isset($data['gateway_transaction_id'])) {
                    $transaction->gateway_transaction_id = $data['gateway_transaction_id'];
                }
                if (isset($data['gateway_status'])) {
                    $transaction->gateway_status = $data['gateway_status'];
                }
                if (isset($data['gateway_response'])) {
                    $transaction->gateway_response = $data['gateway_response'];
                }
                if (isset($data['notes'])) {
                    $transaction->notes = $data['notes'];
                }
                $transaction->save();
        }

        // Refresh the transaction data
        $transaction->refresh();

        return response()->json([
            'status' => 'success',
            'message' => 'Transaction status updated successfully',
            'data' => $transaction
        ], Response::HTTP_OK);
    }



    /**
     * Verify a signature from Monetbill webhook
     *
     * @param array $params Request parameters
     * @param PaymentGatewaySetting $gatewaySettings Payment gateway settings
     * @return bool
     */
    private function monetbill_check_sign(array $params, $gatewaySettings): bool
    {
        // For development/testing environments, we can bypass signature verification
        // if the environment variable is set
        if (config('app.env') === 'local' && config('app.monetbill_bypass_signature', false)) {
            Log::info('[MonetbillWebhook] Bypassing signature verification in local environment');
            return true;
        }

        // Check if sign parameter exists
        if (!array_key_exists('sign', $params)) {
            Log::error('[MonetbillWebhook] Missing signature in parameters');
            return false;
        }

        // Get service secret from gateway settings
        $service_secret = $gatewaySettings->getSetting('service_secret');
        if (empty($service_secret)) {
            Log::error('[MonetbillWebhook] Missing service_secret in gateway settings');
            return false;
        }

        // Extract the sign parameter
        $sign = $params['sign'];

        // Remove sign from parameters before generating comparison signature
        $paramsToVerify = $params;
        unset($paramsToVerify['sign']);

        // Remove environment object and other non-scalar values that shouldn't be part of signature
        foreach ($paramsToVerify as $key => $value) {
            if (is_array($value) || is_object($value)) {
                unset($paramsToVerify[$key]);
            }
        }

        // Sort parameters alphabetically by key
        ksort($paramsToVerify);

        // Log the parameters we're using for signature calculation
        Log::debug('[MonetbillWebhook] Parameters for signature calculation', [
            'params' => $paramsToVerify
        ]);

        // Create signature by concatenating service_secret with parameters
        $signature = md5($service_secret . implode('', $paramsToVerify));

        // Compare signatures
        $result = ($sign === $signature);

        if (!$result) {
            // Try alternate signature calculation methods as fallback
            // Method 2: Only use specific fields known to be part of original signature
            $essentialParams = array_intersect_key($paramsToVerify, [
                'payment_ref' => 1,
                'item_ref' => 1,
                'amount' => 1,
                'status' => 1,
                'transaction_id' => 1,
                'user' => 1
            ]);
            ksort($essentialParams);
            $altSignature = md5($service_secret . implode('', $essentialParams));

            if ($sign === $altSignature) {
                Log::info('[MonetbillWebhook] Signature verified with essential parameters');
                return true;
            }

            Log::error('[MonetbillWebhook] Invalid signature', [
                'received' => $sign,
                'calculated' => $signature,
                'alt_calculated' => $altSignature
            ]);
        } else {
            Log::info('[MonetbillWebhook] Signature verified successfully');
        }

        return $result;
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
        // Add debugging: Check if transaction exists at all
        // IMPORTANT: Use withoutGlobalScopes to bypass EnvironmentScope
        $anyTransaction = Transaction::withoutGlobalScopes()->where("transaction_id", $transactionId)->first();
        
        Log::info('Transaction existence check (without global scopes)', [
            'transaction_id' => $transactionId,
            'exists' => $anyTransaction ? 'yes' : 'no',
            'status' => $anyTransaction ? $anyTransaction->status : 'none',
            'environment_id' => $anyTransaction ? $anyTransaction->environment_id : 'none',
            'has_payment_gateway_setting' => $anyTransaction && $anyTransaction->paymentGatewaySetting ? 'yes' : 'no',
            'current_session_env' => session('current_environment_id')
        ]);

        // First, try environment-specific lookup (existing behavior)
        // Use transaction_id for callback lookups (payment_ref parameter)
        $transaction = Transaction::where(function ($query) use ($transactionId) {
                $query->where("transaction_id", $transactionId)
                    ->orWhere("gateway_transaction_id", $transactionId);
            })
            ->when(is_numeric($environment_id), fn ($query) => $query->where("environment_id", $environment_id))
            ->where("status", Transaction::STATUS_PENDING)
            ->whereHas("paymentGatewaySetting")
            ->first();

        if ($transaction) {
            Log::info('Transaction found with environment-specific lookup', [
                'transaction_id' => $transactionId,
                'environment_id' => $environment_id,
                'found_environment_id' => $transaction->environment_id
            ]);
            return $transaction;
        }

        // If not found, try global lookup for supported plan transactions
        // IMPORTANT: Use withoutGlobalScopes to bypass EnvironmentScope for cross-environment lookup
        $globalTransaction = Transaction::withoutGlobalScopes()
            ->where(function ($query) use ($transactionId) {
                $query->where("transaction_id", $transactionId)
                    ->orWhere("gateway_transaction_id", $transactionId);
            })
            ->where("status", Transaction::STATUS_PENDING)
            ->whereHas("paymentGatewaySetting")
            ->first();

        Log::info('Global transaction lookup result', [
            'transaction_id' => $transactionId,
            'found' => $globalTransaction ? 'yes' : 'no',
            'status' => $globalTransaction ? $globalTransaction->status : 'none',
            'environment_id' => $globalTransaction ? $globalTransaction->environment_id : 'none'
        ]);

        if ($globalTransaction) {
            // Check if this is a supported plan transaction using basic detection
            $isLikelySupportedPlan = $this->isLikelySupportedPlanTransaction($globalTransaction);
            
            Log::info('Supported plan detection result', [
                'transaction_id' => $transactionId,
                'is_likely_supported_plan' => $isLikelySupportedPlan,
                'description' => $globalTransaction->description,
                'amount' => $globalTransaction->total_amount
            ]);
            
            if ($isLikelySupportedPlan) {
                Log::info('Supported plan transaction found with global lookup', [
                    'transaction_id' => $transactionId,
                    'callback_environment_id' => $environment_id,
                    'transaction_environment_id' => $globalTransaction->environment_id
                ]);
                return $globalTransaction;
            } else {
                Log::warning('Non-supported plan transaction found in different environment', [
                    'transaction_id' => $transactionId,
                    'callback_environment_id' => $environment_id,
                    'transaction_environment_id' => $globalTransaction->environment_id
                ]);
            }
        }

        // Check for completed transactions as fallback
        $completedTransaction = Transaction::withoutGlobalScopes()
            ->where(function ($query) use ($transactionId) {
                $query->where("transaction_id", $transactionId)
                    ->orWhere("gateway_transaction_id", $transactionId);
            })
            ->where("status", Transaction::STATUS_COMPLETED)
            ->whereHas("paymentGatewaySetting")
            ->first();

        if ($completedTransaction) {
            if ($completedTransaction->environment_id == $environment_id || $this->isLikelySupportedPlanTransaction($completedTransaction)) {
                Log::info('Completed transaction found', [
                    'transaction_id' => $transactionId,
                    'environment_id' => $environment_id,
                    'found_environment_id' => $completedTransaction->environment_id,
                    'is_supported_plan' => $this->isLikelySupportedPlanTransaction($completedTransaction)
                ]);
                return $completedTransaction;
            }
        }

        Log::error('Transaction not found with any lookup method', [
            'transaction_id' => $transactionId,
            'environment_id' => $environment_id
        ]);

        return null;
    }

    /**
     * Find transaction for webhook with smart lookup logic
     * Handles cross-environment transactions for supported plans
     * 
     * @param string $reference
     * @param \App\Models\PaymentGatewaySetting $gatewaySettings
     * @return Transaction|null
     */
    private function findTransactionForWebhook($reference, $gatewaySettings)
    {
        // First, try environment-specific lookup (existing behavior)
        $transaction = Transaction::where(function ($query) use ($reference) {
                $query->where('gateway_transaction_id', $reference)
                    ->orWhere('transaction_id', $reference);
            })
            ->where('environment_id', $gatewaySettings->environment_id)
            ->first();

        if ($transaction) {
            Log::info('Transaction found with environment-specific webhook lookup', [
                'reference' => $reference,
                'gateway_environment_id' => $gatewaySettings->environment_id,
                'found_environment_id' => $transaction->environment_id
            ]);
            return $transaction;
        }

        // If not found, try global lookup
        // IMPORTANT: Use withoutGlobalScopes to bypass EnvironmentScope for cross-environment lookup
        $globalTransaction = Transaction::withoutGlobalScopes()
            ->where(function ($query) use ($reference) {
                $query->where('gateway_transaction_id', $reference)
                    ->orWhere('transaction_id', $reference);
            })
            ->first();

        if ($globalTransaction) {
            // Check if this is a supported plan transaction using basic detection
            if ($this->isLikelySupportedPlanTransaction($globalTransaction)) {
                Log::info('Supported plan transaction found with global webhook lookup', [
                    'reference' => $reference,
                    'gateway_environment_id' => $gatewaySettings->environment_id,
                    'transaction_environment_id' => $globalTransaction->environment_id
                ]);
                return $globalTransaction;
            } else {
                Log::warning('Non-supported plan transaction found in different environment via webhook', [
                    'reference' => $reference,
                    'gateway_environment_id' => $gatewaySettings->environment_id,
                    'transaction_environment_id' => $globalTransaction->environment_id
                ]);
            }
        }

        Log::error('Transaction not found with any webhook lookup method', [
            'reference' => $reference,
            'gateway_environment_id' => $gatewaySettings->environment_id
        ]);

        return null;
    }

    /**
     * Basic check to identify likely supported plan transactions
     * Used by helper methods to avoid circular dependency with isSupportedPlanPayment
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

        $details = is_array($transaction->payment_method_details)
            ? $transaction->payment_method_details
            : json_decode($transaction->payment_method_details ?: '[]', true);

        if (($details['scope'] ?? null) === 'platform') {
            return true;
        }

        // Check product name if it contains supported plan keywords
        if (isset($transaction->product_name) && $transaction->product_name && stripos($transaction->product_name, 'supported') !== false) {
            return true;
        }

        return false;
    }

    /**
     * Check if the transaction is for a supported plan payment (environment setup)
     * 
     * @param Transaction $transaction
     * @return bool
     */
    private function isSupportedPlanPayment($transaction): bool
    {
        if (!$transaction) {
            return false;
        }

        // Check if transaction has a subscription associated with it
        $payment = Payment::where('transaction_id', $transaction->transaction_id)->first();
        
        if ($payment && $payment->subscription_id) {
            $subscription = Subscription::where('id', $payment->subscription_id)->first();
            
            if ($subscription && $subscription->plan_id) {
                $plan = Plan::where('id', $subscription->plan_id)->first();
                
                // Check if the plan is a supported plan (you can adjust this logic based on your plan naming/type)
                // For now, we'll check if the plan name contains "supported" or has a specific type
                if ($plan && (
                    stripos($plan->name, 'supported') !== false || 
                    $plan->type === 'supported_plan' ||
                    $plan->type === 'business_teacher'  // Assuming business teacher plan is the supported plan
                )) {
                    return true;
                }
            }
        }

        // Alternative check: look for specific transaction description patterns
        if ($transaction->description && stripos($transaction->description, 'supported plan') !== false) {
            return true;
        }

        // Check if transaction amount matches supported plan pricing (fallback)
        // Assuming supported plan costs $177.00 as mentioned in SupportedCompletion.tsx
        if ($transaction->total_amount == 177.00) {
            return true;
        }

        return false;
    }
}
