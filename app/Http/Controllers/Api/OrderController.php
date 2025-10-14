<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\Referral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\Str;
use App\Models\Environment;

/**
 * @OA\Schema(
 *     schema="OrderItem",
 *     required={"order_id", "product_id", "quantity", "price"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="order_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="product_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="quantity", type="integer", example=1),
 *     @OA\Property(property="price", type="number", format="float", example=99.99),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */

/**
 * @OA\Schema(
 *     schema="Order",
 *     required={"user_id", "total_amount", "currency", "status"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="user_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="order_number", type="string", example="ORD-2025-0001"),
 *     @OA\Property(property="status", type="string", enum={"pending", "processing", "completed", "cancelled", "refunded"}, example="pending"),
 *     @OA\Property(property="total_amount", type="number", format="float", example=99.99),
 *     @OA\Property(property="currency", type="string", example="USD"),
 *     @OA\Property(property="payment_method", type="string", example="credit_card", nullable=true),
 *     @OA\Property(property="payment_id", type="string", example="pay_123456789", nullable=true),
 *     @OA\Property(property="billing_name", type="string", example="John Doe", nullable=true),
 *     @OA\Property(property="billing_email", type="string", example="john@example.com", nullable=true),
 *     @OA\Property(property="billing_address", type="string", example="123 Main St", nullable=true),
 *     @OA\Property(property="billing_city", type="string", example="New York", nullable=true),
 *     @OA\Property(property="billing_state", type="string", example="NY", nullable=true),
 *     @OA\Property(property="billing_zip", type="string", example="10001", nullable=true),
 *     @OA\Property(property="billing_country", type="string", example="US", nullable=true),
 *     @OA\Property(property="notes", type="string", example="Please deliver to the front door", nullable=true),
 *     @OA\Property(property="referral_id", type="integer", format="int64", example=1, nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(
 *         property="items",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="id", type="integer", format="int64", example=1),
 *             @OA\Property(property="order_id", type="integer", format="int64", example=1),
 *             @OA\Property(property="product_id", type="integer", format="int64", example=1),
 *             @OA\Property(property="quantity", type="integer", example=1),
 *             @OA\Property(property="price", type="number", format="float", example=99.99)
 *         )
 *     ),
 *     @OA\Property(
 *         property="user",
 *         type="object",
 *         @OA\Property(property="id", type="integer", format="int64", example=1),
 *         @OA\Property(property="name", type="string", example="John Doe"),
 *         @OA\Property(property="email", type="string", format="email", example="john@example.com")
 *     ),
 *     @OA\Property(
 *         property="referral",
 *         type="object",
 *         nullable=true,
 *         @OA\Property(property="id", type="integer", format="int64", example=1),
 *         @OA\Property(property="code", type="string", example="WELCOME10"),
 *         @OA\Property(property="discount_type", type="string", example="percentage"),
 *         @OA\Property(property="discount_value", type="number", format="float", example=10)
 *     )
 * )
 */

class OrderController extends Controller
{
    /**
     * Display a listing of orders.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/orders",
     *     summary="Get list of orders",
     *     description="Returns paginated list of orders with optional filtering",
     *     operationId="getOrdersList",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term for order number or customer name",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by order status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pending", "processing", "completed", "cancelled", "refunded"})
     *     ),
     *     @OA\Parameter(
     *         name="user_id",
     *         in="query",
     *         description="Filter by user ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="min_amount",
     *         in="query",
     *         description="Minimum order amount filter",
     *         required=false,
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Parameter(
     *         name="max_amount",
     *         in="query",
     *         description="Maximum order amount filter",
     *         required=false,
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Parameter(
     *         name="date_from",
     *         in="query",
     *         description="Filter orders created after this date (format: Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="date_to",
     *         in="query",
     *         description="Filter orders created before this date (format: Y-m-d)",
     *         required=false,
     *         @OA\Schema(type="string", format="date")
     *     ),
     *     @OA\Parameter(
     *         name="sort_field",
     *         in="query",
     *         description="Field to sort by",
     *         required=false,
     *         @OA\Schema(type="string", enum={"created_at", "total_amount", "status"}, default="created_at")
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
     *                     @OA\Items(ref="#/components/schemas/Order")
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
    // Get the environment from the request
    $environment = $request->environment;

    $query = Order::with(['user', 'items.product']);
    
    // Always filter by environment_id
    if ($environment) {
        $query->where('environment_id', $environment->id);
    } elseif ($request->has('environment_id')) {
        $query->where('environment_id', $request->environment_id);
    }
    
    // Apply filters
    if ($request->has('search')) {
        $search = $request->search;
        $query->where(function ($q) use ($search) {
            $q->where('order_number', 'like', "%{$search}%")
              ->orWhere('billing_name', 'like', "%{$search}%")
              ->orWhere('billing_email', 'like', "%{$search}%");
        });
    }
    
    if ($request->has('status')) {
        $query->where('status', $request->status);
    }
    
    if ($request->has('user_id')) {
        $query->where('user_id', $request->user_id);
    }
    
    if ($request->has('min_amount')) {
        $query->where('total_amount', '>=', $request->min_amount);
    }
    
    if ($request->has('max_amount')) {
        $query->where('total_amount', '<=', $request->max_amount);
    }
    
    if ($request->has('from_date')) {
        $query->whereDate('created_at', '>=', $request->from_date);
    }
    
    if ($request->has('to_date')) {
        $query->whereDate('created_at', '<=', $request->to_date);
    }
    
    // Apply sorting
    $sortField = $request->input('sort_field', 'created_at');
    $sortDirection = $request->input('sort_direction', 'desc');
    $allowedSortFields = ['order_number', 'total_amount', 'status', 'created_at'];
    
    if (in_array($sortField, $allowedSortFields)) {
        $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
    } else {
        $query->orderBy('created_at', 'desc');
    }
    
    // Pagination
    $perPage = $request->input('per_page', 15);
    $orders = $query->paginate($perPage);
    
    return response()->json([
        'status' => 'success',
        'data' => $orders,
    ]);
}

    /**
     * Display a listing of the current user's orders.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/orders/my-orders",
     *     summary="Get current user's orders",
     *     description="Returns paginated list of the authenticated user's orders",
     *     operationId="getUserOrders",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by order status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"pending", "processing", "completed", "cancelled", "refunded"})
     *     ),
     *     @OA\Parameter(
     *         name="sort_field",
     *         in="query",
     *         description="Field to sort by",
     *         required=false,
     *         @OA\Schema(type="string", enum={"created_at", "total_amount", "status"}, default="created_at")
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
     *                     @OA\Items(ref="#/components/schemas/Order")
     *                 ),
     *                 @OA\Property(property="total", type="integer", example=10),
     *                 @OA\Property(property="per_page", type="integer", example=15)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function myOrders(Request $request)
    {
        $query = Order::with(['items.product'])
            ->where('user_id', Auth::id());
        
        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        // Apply sorting
        $sortField = $request->input('sort_field', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $allowedSortFields = ['order_number', 'total_amount', 'status', 'created_at'];
        
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }
        
        // Pagination
        $perPage = $request->input('per_page', 15);
        $orders = $query->paginate($perPage);
        
        return response()->json([
            'status' => 'success',
            'data' => $orders,
        ]);
    }

    /**
     * Store a newly created order.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/orders",
     *     summary="Create a new order",
     *     description="Creates a new order with the provided products and billing information",
     *     operationId="storeOrder",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Order data",
     *         @OA\JsonContent(
     *             required={"items"},
     *             @OA\Property(
     *                 property="items",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"product_id", "quantity"},
     *                     @OA\Property(property="product_id", type="integer", example=1),
     *                     @OA\Property(property="quantity", type="integer", example=1)
     *                 )
     *             ),
     *             @OA\Property(property="referral_code", type="string", example="WELCOME10", nullable=true),
     *             @OA\Property(property="billing_name", type="string", example="John Doe", nullable=true),
     *             @OA\Property(property="billing_email", type="string", example="john@example.com", nullable=true),
     *             @OA\Property(property="billing_address", type="string", example="123 Main St", nullable=true),
     *             @OA\Property(property="billing_city", type="string", example="New York", nullable=true),
     *             @OA\Property(property="billing_state", type="string", example="NY", nullable=true),
     *             @OA\Property(property="billing_zip", type="string", example="10001", nullable=true),
     *             @OA\Property(property="billing_country", type="string", example="US", nullable=true),
     *             @OA\Property(property="notes", type="string", example="Please deliver to the front door", nullable=true),
     *             @OA\Property(property="payment_method", type="string", example="credit_card", nullable=true),
     *             @OA\Property(property="payment_id", type="string", example="pay_123456789", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Order created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Order created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Order")
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
        $validator = Validator::make($request->all(), [
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|string',
            'billing_name' => 'required|string|max:255',
            'billing_email' => 'required|email|max:255',
            'billing_address' => 'required|string|max:255',
            'billing_city' => 'required|string|max:255',
            'billing_state' => 'required|string|max:255',
            'billing_zip' => 'required|string|max:50',
            'billing_country' => 'required|string|max:2',
            'notes' => 'nullable|string',
            'referral_id' => 'nullable|exists:referrals,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Start transaction
        DB::beginTransaction();

        try {
            // Calculate total amount
            $totalAmount = 0;
            $products = [];

            foreach ($request->products as $productData) {
                $product = Product::findOrFail($productData['id']);
                
                // Skip inactive products
                if ($product->status !== 'active') {
                    continue;
                }
                
                $quantity = $productData['quantity'];
                $price = $product->discount_price ?? $product->price;
                $total = $price * $quantity;
                $totalAmount += $total;
                
                $products[] = [
                    'product' => $product,
                    'quantity' => $quantity,
                    'price' => $price,
                    'total' => $total,
                ];
            }

            // If no active products were found, return error
            if (empty($products)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No active products were found in your order',
                ], Response::HTTP_BAD_REQUEST);
            }

            // Create order
            $order = new Order();
            $order->user_id = Auth::id();
            $order->order_number = $this->generateOrderNumber();
            $order->status = 'pending';
            $order->total_amount = $totalAmount;
            $order->currency = $products[0]['product']->currency; // Use currency from first product
            $order->payment_method = $request->payment_method;
            $order->billing_name = $request->billing_name;
            $order->billing_email = $request->billing_email;
            $order->billing_address = $request->billing_address;
            $order->billing_city = $request->billing_city;
            $order->billing_state = $request->billing_state;
            $order->billing_zip = $request->billing_zip;
            $order->billing_country = $request->billing_country;
            $order->notes = $request->notes;
            $order->referral_id = $request->referral_id;
            $order->save();

            // Create order items
            foreach ($products as $productData) {
                $product = $productData['product'];
                
                $orderItem = new OrderItem();
                $orderItem->order_id = $order->id;
                $orderItem->product_id = $product->id;
                $orderItem->quantity = $productData['quantity'];
                $orderItem->price = $product->price;
                $orderItem->discount = $product->price - $productData['price'];
                $orderItem->total = $productData['total'];
                $orderItem->is_subscription = $product->is_subscription;
                $orderItem->save();
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Order created successfully',
                'data' => $order->load('items.product'),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create order: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified order.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/orders/{id}",
     *     summary="Get order details",
     *     description="Returns details of a specific order",
     *     operationId="getOrderById",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Order ID",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/Order")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Order not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function show($id)
    {
        $order = Order::with(['user', 'items.product'])->findOrFail($id);

        // Check if user has permission to view this order
        $user = Auth::user();
        $hasPermission = false;
        if ($user->isAdmin()) {
            $hasPermission = true;
        } elseif ($order->environment_id) {
            $environment = \App\Models\Environment::find($order->environment_id);
            if ($environment && $environment->owner_id === $user->id) {
                $hasPermission = true;
            }
        } else {
            // Fallback: allow if user is the order owner
            if ($order->user_id === $user->id) {
                $hasPermission = true;
            }
        }
        if (!$hasPermission) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to view this order',
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'status' => 'success',
            'data' => $order,
        ]);
    }

    /**
     * Update the specified order status.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * 
     * @OA\Put(
     *     path="/orders/{id}/status",
     *     summary="Update order status",
     *     description="Updates the status of an existing order",
     *     operationId="updateOrderStatus",
     *     tags={"Orders"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Order ID",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Order status data",
     *         @OA\JsonContent(
     *             required={"status"},
     *             @OA\Property(
     *                 property="status",
     *                 type="string",
     *                 enum={"pending", "processing", "completed", "cancelled", "refunded"},
     *                 example="processing"
     *             ),
     *             @OA\Property(
     *                 property="notes",
     *                 type="string",
     *                 example="Order is being processed",
     *                 nullable=true
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Order status updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Order status updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Order")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission"
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
    public function updateStatus(Request $request, $id)
    {
        $order = Order::findOrFail($id);

        // Check if user has permission to view this order
        $user = Auth::user();
        $hasPermission = false;
        if ($user->isAdmin()) {
            $hasPermission = true;
        } elseif ($order->environment_id) {
            $environment = \App\Models\Environment::find($order->environment_id);
            if ($environment && $environment->owner_id === $user->id) {
                $hasPermission = true;
            }
        } else {
            // Fallback: allow if user is the order owner
            if ($order->user_id === $user->id) {
                $hasPermission = true;
            }
        }
        if (!$hasPermission) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to view this order',
            ], Response::HTTP_FORBIDDEN);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:pending,completed,failed,refunded',
            'payment_id' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Start transaction
        DB::beginTransaction();

        try {
            $oldStatus = $order->status;
            $newStatus = $request->status;
            
            // Update order status
            $order->status = $newStatus;
            
            if ($request->has('payment_id')) {
                $order->payment_id = $request->payment_id;
            }
            
            if ($request->has('notes')) {
                $order->notes = $request->notes;
            }
            
            $order->save();

            // If order is completed, create enrollments for course products
            if ($oldStatus !== 'completed' && $newStatus === 'completed') {
                $this->processCompletedOrder($order);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Order status updated successfully',
                'data' => $order->load('items.product'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update order status: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Process a completed order by creating enrollments for course products.
     *
     * @param  \App\Models\Order  $order
     * @return void
     */
    private function processCompletedOrder(Order $order)
    {
        $user = User::findOrFail($order->user_id);
        
        // Ensure the user is associated with the environment where the order was placed
        if ($order->environment_id) {
            $environment = Environment::find($order->environment_id);
            if ($environment) {
                // Check if the user is already associated with this environment
                $existingAssociation = $user->environments()
                    ->where('environment_id', $environment->id)
                    ->exists();
                
                // If not, create the association
                if (!$existingAssociation) {
                    $user->environments()->attach($environment->id, [
                        'joined_at' => now(),
                    ]);
                }
            }
        }
        
        foreach ($order->items as $item) {
            $product = $item->product;
            
            // Skip if product is not found
            if (!$product) {
                continue;
            }
            
            // Create enrollments for each course in the product
            foreach ($product->courses as $course) {
                // Check if enrollment already exists
                $existingEnrollment = Enrollment::where('user_id', $user->id)
                    ->where('course_id', $course->id)
                    ->first();
                
                if ($existingEnrollment) {
                    // Update existing enrollment if it's expired or cancelled
                    if (in_array($existingEnrollment->status, ['expired', 'cancelled'])) {
                        $existingEnrollment->status = 'active';
                        $existingEnrollment->save();
                    }
                } else {
                    // Create new enrollment
                    $enrollment = new Enrollment();
                    $enrollment->user_id = $user->id;
                    $enrollment->course_id = $course->id;
                    $enrollment->environment_id = $order->environment_id;
                    $enrollment->status = 'active';
                    $enrollment->enrolled_at = now();
                    
                    // Set expiration date for subscription products
                    if ($product->is_subscription) {
                        $interval = $product->subscription_interval;
                        $count = (int) $product->subscription_interval_count;
                        
                        switch ($interval) {
                            case 'daily':
                                $enrollment->expires_at = now()->addDays($count);
                                break;
                            case 'weekly':
                                $enrollment->expires_at = now()->addWeeks($count);
                                break;
                            case 'monthly':
                                $enrollment->expires_at = now()->addMonths($count);
                                break;
                            case 'yearly':
                                $enrollment->expires_at = now()->addYears($count);
                                break;
                        }
                    }
                    
                    $enrollment->save();
                }
            }
        }
    }

    /**
     * Generate a unique order number.
     *
     * @return string
     */
    private function generateOrderNumber()
    {
        // Get the current environment
        $environmentId = session('current_environment_id');
        $prefix = 'CSL-'; // Default prefix
        
        if ($environmentId) {
            // Try to get branding from the environment
            $environment = Environment::find($environmentId);
            if ($environment) {
                // Get the active branding for this environment
                $branding = $environment->brandings()->where('is_active', true)->first();
                
                if ($branding && !empty($branding->company_name)) {
                    // Use first letters of company name as prefix
                    $words = explode(' ', $branding->company_name);
                    $prefix = '';
                    
                    foreach ($words as $word) {
                        if (!empty($word)) {
                            $prefix .= strtoupper(substr($word, 0, 1));
                        }
                    }
                    
                    // Ensure prefix is at least 2 characters
                    if (strlen($prefix) < 2) {
                        $prefix = strtoupper(substr($branding->company_name, 0, 2));
                    }
                    
                    $prefix .= '-';
                }
            }
        }
        
        $date = now()->format('Ymd');
        $random = strtoupper(\Illuminate\Support\Str::random(4));
        
        return $prefix . $date . '-' . $random;
    }
}
