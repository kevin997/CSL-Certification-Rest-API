<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use App\Mail\OrderConfirmation;
use Illuminate\Support\Str;

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

        // Apply environment filter if provided
        if ($request->has('environment_id')) {
            $query->where('environment_id', $request->environment_id);
        }

        // Apply payment gateway filter if provided
        if ($request->has('payment_gateway_id')) {
            $query->where('payment_gateway_setting_id', $request->payment_gateway_id);
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
     * Create a payment for an order
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/payments/create",
     *     summary="Create a payment for an order",
     *     description="Creates a payment session/intent based on the selected payment method",
     *     operationId="createPayment",
     *     tags={"Payments"},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"order_id", "gateway_code"},
     *             @OA\Property(property="order_id", type="integer", example=1),
     *             @OA\Property(property="gateway_code", type="string", example="stripe"),
     *             @OA\Property(property="environment_id", type="integer", example=1),
     *             @OA\Property(property="payment_data", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="type", type="string", example="client_secret"),
     *             @OA\Property(property="value", type="string", example="pi_3NJK9JHjZ8aCLp1X1L8qlmvN_secret_MhCLmVIjBBBBrrrGGGGnnnn"),
     *             @OA\Property(property="payment_intent_id", type="string", example="pi_3NJK9JHjZ8aCLp1X1L8qlmvN"),
     *             @OA\Property(property="publishable_key", type="string", example="pk_test_51JK9JHjZ8aCLp1X1L8qlmvN")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid input data or payment creation error"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Order not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function createPayment(Request $request)
    {
        // Validate request data
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|integer|exists:orders,id',
            'gateway_code' => 'required|string|max:50',
            'environment_id' => 'nullable|integer|exists:environments,id',
            'payment_data' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Create the payment
        $paymentService = app(\App\Services\PaymentService::class);
        $result = $paymentService->createPayment(
            $request->input('order_id'),
            $request->input('gateway_code'),
            $request->input('payment_data', []),
            $request->input('environment_id')
        );

        if ($result['success']) {
            return response()->json($result, Response::HTTP_OK);
        } else {
            return response()->json($result, Response::HTTP_BAD_REQUEST);
        }
    }
    
    /**
     * Handle Stripe webhook events
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function stripeWebhook(Request $request)
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');
        
        try {
            // Verify the webhook signature
            $event = \Stripe\Webhook::constructEvent(
                $payload, $sigHeader, $endpointSecret
            );
            
            // Handle the event
            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $paymentIntent = $event->data->object;
                    $this->handleStripePaymentSuccess($paymentIntent);
                    break;
                    
                case 'payment_intent.payment_failed':
                    $paymentIntent = $event->data->object;
                    $this->handleStripePaymentFailure($paymentIntent);
                    break;
                    
                default:
                    // Unexpected event type
                    Log::info('Unhandled Stripe event: ' . $event->type);
            }
            
            return response()->json(['status' => 'success']);
            
        } catch (\UnexpectedValueException $e) {
            // Invalid payload
            Log::error('Stripe webhook error: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid payload'], 400);
            
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            // Invalid signature
            Log::error('Stripe webhook signature verification failed: ' . $e->getMessage());
            return response()->json(['error' => 'Invalid signature'], 400);
            
        } catch (\Exception $e) {
            // Other exceptions
            Log::error('Stripe webhook error: ' . $e->getMessage());
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }
    
    /**
     * Handle PayPal webhook events
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function paypalWebhook(Request $request)
    {
        $payload = $request->all();
        $webhookId = config('services.paypal.webhook_id');
        
        try {
            // Verify the webhook signature (PayPal uses a different approach)
            $paypalGateway = app(\App\Services\PaymentGateways\PayPalGateway::class);
            $webhookSecret = config('services.paypal.webhook_secret');
            $isValid = $paypalGateway->verifyWebhookSignature($request, $webhookId, $webhookSecret);
            
            if (!$isValid) {
                Log::error('PayPal webhook signature verification failed');
                return response()->json(['error' => 'Invalid signature'], 400);
            }
            
            // Handle the event
            $eventType = $payload['event_type'] ?? '';
            
            switch ($eventType) {
                case 'PAYMENT.CAPTURE.COMPLETED':
                    $this->handlePayPalPaymentSuccess($payload);
                    break;
                    
                case 'PAYMENT.CAPTURE.DENIED':
                case 'PAYMENT.CAPTURE.DECLINED':
                    $this->handlePayPalPaymentFailure($payload);
                    break;
                    
                default:
                    // Unexpected event type
                    Log::info('Unhandled PayPal event: ' . $eventType);
            }
            
            return response()->json(['status' => 'success']);
            
        } catch (\Exception $e) {
            // Other exceptions
            Log::error('PayPal webhook error: ' . $e->getMessage());
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }
    
    /**
     * Handle Lygos webhook events
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function lygosWebhook(Request $request)
    {
        $payload = $request->all();
        $signature = $request->header('X-Lygos-Signature');
        $secret = config('services.lygos.webhook_secret');
        
        try {
            // Verify the webhook signature
            $lygosGateway = app(\App\Services\PaymentGateways\LygosGateway::class);
            $isValid = $lygosGateway->verifyWebhookSignature($payload, $signature, $secret);
            
            if (!$isValid) {
                Log::error('Lygos webhook signature verification failed');
                return response()->json(['error' => 'Invalid signature'], 400);
            }
            
            // Handle the event
            $eventType = $payload['event'] ?? '';
            
            switch ($eventType) {
                case 'payment.completed':
                    $this->handleLygosPaymentSuccess($payload);
                    break;
                    
                case 'payment.failed':
                    $this->handleLygosPaymentFailure($payload);
                    break;
                    
                default:
                    // Unexpected event type
                    Log::info('Unhandled Lygos event: ' . $eventType);
            }
            
            return response()->json(['status' => 'success']);
            
        } catch (\Exception $e) {
            // Other exceptions
            Log::error('Lygos webhook error: ' . $e->getMessage());
            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }
    
    /**
     * Handle successful Stripe payment
     *
     * @param object $paymentIntent
     * @return void
     */
    private function handleStripePaymentSuccess($paymentIntent)
    {
        // Extract metadata
        $transactionId = $paymentIntent->metadata->transaction_id ?? null;
        $orderId = $paymentIntent->metadata->order_id ?? null;
        
        if (!$transactionId && !$orderId) {
            Log::error('Stripe payment success: Missing transaction_id and order_id in metadata');
            return;
        }
        
        // Find the transaction
        $transaction = null;
        
        if ($transactionId) {
            $transaction = Transaction::where('id', $transactionId)->first();
        } elseif ($orderId) {
            $transaction = Transaction::where('order_id', $orderId)->first();
        }
        
        if (!$transaction) {
            Log::error('Stripe payment success: Transaction not found');
            return;
        }
        
        // Update transaction status
        $transaction->status = Transaction::STATUS_COMPLETED;
        $transaction->gateway_transaction_id = $paymentIntent->id;
        $transaction->gateway_response = json_encode($paymentIntent);
        $transaction->completed_at = now();
        $transaction->save();
        
        // Update order status
        $order = Order::findOrFail($transaction->order_id);
        $order->status = 'completed';
        $order->save();
        
        // Process order completion (e.g., create enrollments, send emails)
        $this->processCompletedOrder($order);
    }
    
    /**
     * Handle failed Stripe payment
     *
     * @param object $paymentIntent
     * @return void
     */
    private function handleStripePaymentFailure($paymentIntent)
    {
        // Extract metadata
        $transactionId = $paymentIntent->metadata->transaction_id ?? null;
        $orderId = $paymentIntent->metadata->order_id ?? null;
        
        if (!$transactionId && !$orderId) {
            Log::error('Stripe payment failure: Missing transaction_id and order_id in metadata');
            return;
        }
        
        // Find the transaction
        $transaction = null;
        
        if ($transactionId) {
            $transaction = Transaction::where('id', $transactionId)->first();
        } elseif ($orderId) {
            $transaction = Transaction::where('order_id', $orderId)->first();
        }
        
        if (!$transaction) {
            Log::error('Stripe payment failure: Transaction not found');
            return;
        }
        
        // Update transaction status
        $transaction->status = Transaction::STATUS_FAILED;
        $transaction->gateway_transaction_id = $paymentIntent->id;
        $transaction->gateway_response = json_encode($paymentIntent);
        $transaction->error_message = $paymentIntent->last_payment_error->message ?? 'Payment failed';
        $transaction->save();
        
        // Update order status
        $order = Order::find($transaction->order_id);
        if ($order) {
            $order->status = 'failed';
            $order->save();
        }
    }
    
    /**
     * Handle successful PayPal payment
     *
     * @param array $payload
     * @return void
     */
    private function handlePayPalPaymentSuccess($payload)
    {
        // Extract resource data
        $resource = $payload['resource'] ?? [];
        $purchaseUnits = $resource['purchase_units'] ?? [];
        
        if (empty($purchaseUnits)) {
            Log::error('PayPal payment success: Missing purchase units');
            return;
        }
        
        // Get the first purchase unit (we only use one per transaction)
        $purchaseUnit = $purchaseUnits[0];
        $referenceId = $purchaseUnit['reference_id'] ?? null;
        
        if (!$referenceId) {
            Log::error('PayPal payment success: Missing reference_id');
            return;
        }
        
        // Find the transaction using the reference_id (which is our transaction ID)
        $transaction = Transaction::where('id', $referenceId)->first();
        
        if (!$transaction) {
            Log::error('PayPal payment success: Transaction not found');
            return;
        }
        
        // Update transaction status
        $transaction->status = Transaction::STATUS_COMPLETED;
        $transaction->gateway_transaction_id = $resource['id'] ?? null;
        $transaction->gateway_response = json_encode($payload);
        $transaction->completed_at = now();
        $transaction->save();
        
        // Update order status
        $order = Order::findOrFail($transaction->order_id);
        $order->status = 'completed';
        $order->save();
        
        // Process order completion (e.g., create enrollments, send emails)
        $this->processCompletedOrder($order);
    }
    
    /**
     * Handle failed PayPal payment
     *
     * @param array $payload
     * @return void
     */
    private function handlePayPalPaymentFailure($payload)
    {
        // Extract resource data
        $resource = $payload['resource'] ?? [];
        $purchaseUnits = $resource['purchase_units'] ?? [];
        
        if (empty($purchaseUnits)) {
            Log::error('PayPal payment failure: Missing purchase units');
            return;
        }
        
        // Get the first purchase unit (we only use one per transaction)
        $purchaseUnit = $purchaseUnits[0];
        $referenceId = $purchaseUnit['reference_id'] ?? null;
        
        if (!$referenceId) {
            Log::error('PayPal payment failure: Missing reference_id');
            return;
        }
        
        // Find the transaction using the reference_id (which is our transaction ID)
        $transaction = Transaction::where('id', $referenceId)->first();
        
        if (!$transaction) {
            Log::error('PayPal payment failure: Transaction not found');
            return;
        }
        
        // Update transaction status
        $transaction->status = Transaction::STATUS_FAILED;
        $transaction->gateway_transaction_id = $resource['id'] ?? null;
        $transaction->gateway_response = json_encode($payload);
        $transaction->error_message = $resource['status_details']['reason'] ?? 'Payment failed';
        $transaction->save();
        
        // Update order status
        $order = Order::find($transaction->order_id);
        if ($order) {
            $order->status = 'failed';
            $order->save();
        }
    }
    
    /**
     * Handle successful Lygos payment
     *
     * @param array $payload
     * @return void
     */
    private function handleLygosPaymentSuccess($payload)
    {
        // Extract data
        $paymentId = $payload['payment_id'] ?? null;
        $metadata = $payload['metadata'] ?? [];
        $transactionId = $metadata['transaction_id'] ?? null;
        $orderId = $metadata['order_id'] ?? null;
        
        if (!$transactionId && !$orderId) {
            Log::error('Lygos payment success: Missing transaction_id and order_id in metadata');
            return;
        }
        
        // Find the transaction
        $transaction = null;
        
        if ($transactionId) {
            $transaction = Transaction::where('id', $transactionId)->first();
        } elseif ($orderId) {
            $transaction = Transaction::where('order_id', $orderId)->first();
        }
        
        if (!$transaction) {
            Log::error('Lygos payment success: Transaction not found');
            return;
        }
        
        // Update transaction status
        $transaction->status = Transaction::STATUS_COMPLETED;
        $transaction->gateway_transaction_id = $paymentId;
        $transaction->gateway_response = json_encode($payload);
        $transaction->completed_at = now();
        $transaction->save();
        
        // Update order status
        $order = Order::findOrFail($transaction->order_id);
        $order->status = 'completed';
        $order->save();
        
        // Process order completion (e.g., create enrollments, send emails)
        $this->processCompletedOrder($order);
    }
    
    /**
     * Handle failed Lygos payment
     *
     * @param array $payload
     * @return void
     */
    private function handleLygosPaymentFailure($payload)
    {
        // Extract data
        $paymentId = $payload['payment_id'] ?? null;
        $metadata = $payload['metadata'] ?? [];
        $transactionId = $metadata['transaction_id'] ?? null;
        $orderId = $metadata['order_id'] ?? null;
        $errorMessage = $payload['error_message'] ?? 'Payment failed';
        
        if (!$transactionId && !$orderId) {
            Log::error('Lygos payment failure: Missing transaction_id and order_id in metadata');
            return;
        }
        
        // Find the transaction
        $transaction = null;
        
        if ($transactionId) {
            $transaction = Transaction::where('id', $transactionId)->first();
        } elseif ($orderId) {
            $transaction = Transaction::where('order_id', $orderId)->first();
        }
        
        if (!$transaction) {
            Log::error('Lygos payment failure: Transaction not found');
            return;
        }
        
        // Update transaction status
        $transaction->status = Transaction::STATUS_FAILED;
        $transaction->gateway_transaction_id = $paymentId;
        $transaction->gateway_response = json_encode($payload);
        $transaction->error_message = $errorMessage;
        $transaction->save();
        
        // Update order status
        $order = Order::findOrFail($transaction->order_id);
        $order->status = 'failed';
        $order->save();
    }
    
    /**
     * Process a completed order
     *
     * @param Order $order
     * @return void
     */
    private function processCompletedOrder(Order $order)
    {
        // Ensure we have a single Order instance, not a collection
        if ($order instanceof \Illuminate\Database\Eloquent\Collection) {
            if ($order->count() > 0) {
                $order = $order->first();
            } else {
                Log::error('processCompletedOrder received empty collection');
                return;
            }
        }
        
        // Get the order items
        $orderItems = OrderItem::where('order_id', $order->id)->get();
        
        foreach ($orderItems as $item) {
            $product = Product::find($item->product_id);
            
            if (!$product) {
                continue;
            }
            
            // Handle course enrollments if the product contains courses
            $productCourses = DB::table('product_courses')
                ->where('product_id', $product->id)
                ->get();
                
            foreach ($productCourses as $productCourse) {
                // Create enrollment if it doesn't exist
                $enrollment = DB::table('enrollments')
                    ->where('user_id', $order->user_id)
                    ->where('course_id', $productCourse->course_id)
                    ->where('environment_id', $order->environment_id)
                    ->first();
                    
                if (!$enrollment) {
                    DB::table('enrollments')->insert([
                        'user_id' => $order->user_id,
                        'course_id' => $productCourse->course_id,
                        'environment_id' => $order->environment_id,
                        'status' => 'active',
                        'progress' => 0,
                        'enrolled_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            
            // Handle subscriptions if the product is a subscription
            if ($product->is_subscription) {
                // Create or update subscription
                $subscription = DB::table('subscriptions')
                    ->where('user_id', $order->user_id)
                    ->where('product_id', $product->id)
                    ->where('environment_id', $order->environment_id)
                    ->first();
                    
                if (!$subscription) {
                    // Create new subscription
                    DB::table('subscriptions')->insert([
                        'user_id' => $order->user_id,
                        'product_id' => $product->id,
                        'environment_id' => $order->environment_id,
                        'status' => 'active',
                        'start_date' => now(),
                        'end_date' => now()->addDays($product->subscription_duration),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    // Update existing subscription
                    DB::table('subscriptions')
                        ->where('id', $subscription->id)
                        ->update([
                            'status' => 'active',
                            'end_date' => now()->addDays($product->subscription_duration),
                            'updated_at' => now(),
                        ]);
                }
            }
        }
        
        // Send order confirmation email
        try {
            Mail::to($order->billing_email)->send(new OrderConfirmation($order));
        } catch (\Exception $e) {
            Log::error('Failed to send order confirmation email: ' . $e->getMessage());
        }
    }

    /**
     * Process a transaction with a payment gateway.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/transactions/{id}/process",
     *     summary="Process a transaction",
     *     description="Process a transaction with the associated payment gateway",
     *     operationId="processTransaction",
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
     *         description="Payment processing data",
     *         @OA\JsonContent(
     *             @OA\Property(property="payment_method", type="string", example="credit_card"),
     *             @OA\Property(property="payment_token", type="string", example="tok_visa"),
     *             @OA\Property(property="payment_details", type="object", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Transaction processed successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Transaction processed successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Transaction")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid input data or payment processing error"
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
    public function process(Request $request, $id)
    {
        $transaction = Transaction::findOrFail($id);

        // Check if transaction is already processed
        if ($transaction->status !== Transaction::STATUS_PENDING) {
            return response()->json([
                'status' => 'error',
                'message' => 'Transaction cannot be processed because it is not in pending status.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate request data
        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|string|max:255',
            'payment_token' => 'required|string|max:255',
            'payment_details' => 'nullable|json',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Update transaction with payment method
        $transaction->payment_method = $request->payment_method;
        $transaction->status = Transaction::STATUS_PROCESSING;
        $transaction->save();

        // In a real implementation, you would integrate with the actual payment gateway here
        // For now, we'll simulate a successful payment
        try {
            // Simulate payment processing delay
            sleep(1);
            
            // Simulate successful payment
            $gatewayResponse = [
                'id' => 'ch_' . Str::random(14),
                'object' => 'charge',
                'amount' => $transaction->total_amount * 100, // Convert to cents
                'currency' => strtolower($transaction->currency),
                'status' => 'succeeded',
                'payment_method' => $request->payment_method,
                'payment_method_details' => $request->payment_details ?? [],
                'created' => time(),
            ];
            
            // Mark transaction as completed
            $transaction->markAsCompleted(
                $gatewayResponse['id'],
                $gatewayResponse['status'],
                $gatewayResponse
            );
            
            return response()->json([
                'status' => 'success',
                'message' => 'Transaction processed successfully',
                'data' => $transaction
            ], Response::HTTP_OK);
            
        } catch (\Exception $e) {
            // Mark transaction as failed
            $transaction->markAsFailed(
                null,
                'error',
                ['error' => $e->getMessage()]
            );
            
            return response()->json([
                'status' => 'error',
                'message' => 'Payment processing failed: ' . $e->getMessage(),
                'data' => $transaction
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
