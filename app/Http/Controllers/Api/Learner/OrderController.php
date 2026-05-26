<?php

namespace App\Http\Controllers\Api\Learner;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /**
     * Get all orders for the authenticated learner
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $environmentId = session('current_environment_id');
        
        $orders = Order::where('user_id', $user->id)
            ->where('environment_id', $environmentId)
            ->with('transaction')
            ->with(['items.product'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 10));

        // Transform collection to resolve payment method names
        $orders->getCollection()->transform(function ($order) {
            if (is_numeric($order->payment_method)) {
                $gateway = \App\Models\PaymentGatewaySetting::find($order->payment_method);
                if ($gateway) {
                    $order->payment_method = $gateway->gateway_name ?? $gateway->code;
                }
            }
            return $order;
        });
        
        return response()->json([
            'status' => 'success',
            'data' => $orders,
        ]);
    }
    
    /**
     * Get a specific order for the learner
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        $user = Auth::user();
        $environmentId = session('current_environment_id');
        
        $order = Order::where('id', $id)
            ->where('user_id', $user->id)
            ->where('environment_id', $environmentId)
            ->with('transaction')
            ->with([
                'items.product' => function($query) {
                    $query->with('courses');
                }
            ])
            ->firstOrFail();

        if (is_numeric($order->payment_method)) {
            $gateway = \App\Models\PaymentGatewaySetting::find($order->payment_method);
            if ($gateway) {
                $order->payment_method = $gateway->gateway_name ?? $gateway->code;
            }
        }
        
        return response()->json([
            'status' => 'success',
            'data' => $order,
        ]);
    }
    
    /**
     * Create a new order for the learner
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        $environmentId = session('current_environment_id');
        
        $request->validate([
            'items' => 'required|array',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'billing_name' => 'required|string|max:255',
            'billing_email' => 'required|email',
            'payment_method' => 'required|string',
        ]);
        
        // Start a database transaction
        DB::beginTransaction();
        
        try {
            // Create the order
            $order = Order::create([
                'user_id' => $user->id,
                'environment_id' => $environmentId,
                'order_number' => 'ORD-' . strtoupper(uniqid()),
                'status' => 'pending',
                'total_amount' => 0, // Will be calculated below
                'currency' => $request->input('currency', 'USD'),
                'payment_method' => $request->input('payment_method'),
                'billing_name' => $request->input('billing_name'),
                'billing_email' => $request->input('billing_email'),
                'billing_address' => $request->input('billing_address'),
                'billing_city' => $request->input('billing_city'),
                'billing_state' => $request->input('billing_state'),
                'billing_zip' => $request->input('billing_zip'),
                'billing_country' => $request->input('billing_country'),
                'notes' => $request->input('notes'),
            ]);
            
            $totalAmount = 0;
            
            // Create the order items
            foreach ($request->input('items') as $item) {
                $product = \App\Models\Product::findOrFail($item['product_id']);
                
                $price = $product->discount_price ?? $product->price;
                $quantity = $item['quantity'];
                $itemTotal = $price * $quantity;
                
                $order->items()->create([
                    'product_id' => $product->id,
                    'quantity' => $quantity,
                    'price' => $price,
                    'total' => $itemTotal,
                    'is_subscription' => $product->is_subscription,
                ]);
                
                $totalAmount += $itemTotal;
            }
            
            // Update the order total
            $order->total_amount = $totalAmount;
            $order->save();
            
            // Dispatch OrderCreated notification
            try {
                $user->notify(new \App\Notifications\OrderCreated($order, app(\App\Services\TelegramService::class)));
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Failed to send OrderCreated notification: ' . $e->getMessage());
            }

            // Order is created in pending state. Enrollment is granted by the OrderCompleted
            // event after payment is confirmed — not at order creation time.
            DB::commit();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Order created successfully',
                'data' => $order->load('items.product'),
            ], 201);
            
        } catch (\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create order: ' . $e->getMessage(),
            ], 500);
        }
    }
}
