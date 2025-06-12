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
}
