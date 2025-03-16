<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderService
{
    /**
     * @var ProductService
     */
    protected $productService;

    /**
     * Constructor
     */
    public function __construct(ProductService $productService)
    {
        $this->productService = $productService;
    }
    
    /**
     * Get all orders
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllOrders(array $filters = [])
    {
        $query = Order::with(['user', 'orderItems.product']);
        
        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        
        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhere('customer_email', 'like', "%{$search}%")
                  ->orWhere('customer_name', 'like', "%{$search}%");
            });
        }
        
        // Apply sorting
        $sortField = $filters['sort_field'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $query->orderBy($sortField, $sortDirection);
        
        return $query->get();
    }
    
    /**
     * Get order by ID
     *
     * @param int $id
     * @return Order|null
     */
    public function getOrderById(int $id): ?Order
    {
        return Order::with(['user', 'orderItems.product'])->find($id);
    }
    
    /**
     * Get order by order number
     *
     * @param string $orderNumber
     * @return Order|null
     */
    public function getOrderByNumber(string $orderNumber): ?Order
    {
        return Order::with(['user', 'orderItems.product'])
            ->where('order_number', $orderNumber)
            ->first();
    }
    
    /**
     * Create a new order
     *
     * @param array $data
     * @param array $items
     * @return Order|null
     */
    public function createOrder(array $data, array $items): ?Order
    {
        // Validate items
        foreach ($items as $item) {
            if (!isset($item['product_id']) || !isset($item['quantity'])) {
                return null;
            }
            
            $product = $this->productService->getProductById($item['product_id']);
            
            if (!$product) {
                return null;
            }
            
            // Check if product is available
            if (!$this->productService->isProductAvailable($item['product_id'])) {
                return null;
            }
            
            // Check if quantity is available
            if ($product->stock_quantity !== null && $product->stock_quantity < $item['quantity']) {
                return null;
            }
        }
        
        // Generate order number if not provided
        if (!isset($data['order_number'])) {
            $data['order_number'] = $this->generateOrderNumber();
        }
        
        // Calculate totals
        $subtotal = 0;
        $tax = 0;
        $discount = $data['discount'] ?? 0;
        
        foreach ($items as $item) {
            $product = $this->productService->getProductById($item['product_id']);
            $price = $product->sale_price ?? $product->price;
            $itemTotal = $price * $item['quantity'];
            $subtotal += $itemTotal;
        }
        
        // Calculate tax if tax_rate is provided
        if (isset($data['tax_rate'])) {
            $tax = ($subtotal - $discount) * ($data['tax_rate'] / 100);
        }
        
        // Calculate total
        $total = $subtotal - $discount + $tax;
        
        // Prepare order data
        $orderData = [
            'user_id' => $data['user_id'] ?? null,
            'order_number' => $data['order_number'],
            'status' => $data['status'] ?? 'pending',
            'customer_name' => $data['customer_name'] ?? null,
            'customer_email' => $data['customer_email'] ?? null,
            'billing_address' => $data['billing_address'] ?? null,
            'shipping_address' => $data['shipping_address'] ?? null,
            'payment_method' => $data['payment_method'] ?? null,
            'payment_status' => $data['payment_status'] ?? 'pending',
            'subtotal' => $subtotal,
            'tax' => $tax,
            'discount' => $discount,
            'total' => $total,
            'notes' => $data['notes'] ?? null,
            'metadata' => isset($data['metadata']) ? json_encode($data['metadata']) : null
        ];
        
        // Create order in a transaction
        try {
            DB::beginTransaction();
            
            // Create order
            $order = Order::create($orderData);
            
            // Create order items
            foreach ($items as $item) {
                $product = $this->productService->getProductById($item['product_id']);
                $price = $product->sale_price ?? $product->price;
                
                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $item['product_id'],
                    'name' => $product->name,
                    'quantity' => $item['quantity'],
                    'price' => $price,
                    'total' => $price * $item['quantity'],
                    'metadata' => isset($item['metadata']) ? json_encode($item['metadata']) : null
                ]);
                
                // Decrease stock if product has stock_quantity
                if ($product->stock_quantity !== null) {
                    $this->productService->decreaseStock($item['product_id'], $item['quantity']);
                }
            }
            
            DB::commit();
            
            return $this->getOrderById($order->id);
        } catch (\Exception $e) {
            DB::rollBack();
            return null;
        }
    }
    
    /**
     * Update order status
     *
     * @param int $id
     * @param string $status
     * @return Order|null
     */
    public function updateOrderStatus(int $id, string $status): ?Order
    {
        $order = $this->getOrderById($id);
        
        if (!$order) {
            return null;
        }
        
        $order->update(['status' => $status]);
        
        return $order;
    }
    
    /**
     * Update payment status
     *
     * @param int $id
     * @param string $paymentStatus
     * @param array $paymentData
     * @return Order|null
     */
    public function updatePaymentStatus(int $id, string $paymentStatus, array $paymentData = []): ?Order
    {
        $order = $this->getOrderById($id);
        
        if (!$order) {
            return null;
        }
        
        $metadata = json_decode($order->metadata ?? '{}', true);
        $metadata['payment_data'] = $paymentData;
        
        $order->update([
            'payment_status' => $paymentStatus,
            'metadata' => json_encode($metadata)
        ]);
        
        return $order;
    }
    
    /**
     * Cancel order
     *
     * @param int $id
     * @param string $reason
     * @return Order|null
     */
    public function cancelOrder(int $id, string $reason = ''): ?Order
    {
        $order = $this->getOrderById($id);
        
        if (!$order) {
            return null;
        }
        
        // Only allow cancellation of pending or processing orders
        if (!in_array($order->status, ['pending', 'processing'])) {
            return null;
        }
        
        try {
            DB::beginTransaction();
            
            // Update order status
            $order->update([
                'status' => 'cancelled',
                'notes' => $order->notes . "\nCancellation reason: " . $reason
            ]);
            
            // Restore stock for each item
            foreach ($order->orderItems as $item) {
                $product = $this->productService->getProductById($item->product_id);
                
                if ($product && $product->stock_quantity !== null) {
                    $this->productService->increaseStock($item->product_id, $item->quantity);
                }
            }
            
            DB::commit();
            
            return $order;
        } catch (\Exception $e) {
            DB::rollBack();
            return null;
        }
    }
    
    /**
     * Add item to order
     *
     * @param int $orderId
     * @param int $productId
     * @param int $quantity
     * @return Order|null
     */
    public function addOrderItem(int $orderId, int $productId, int $quantity): ?Order
    {
        $order = $this->getOrderById($orderId);
        $product = $this->productService->getProductById($productId);
        
        if (!$order || !$product) {
            return null;
        }
        
        // Only allow adding items to pending or processing orders
        if (!in_array($order->status, ['pending', 'processing'])) {
            return null;
        }
        
        // Check if product is available
        if (!$this->productService->isProductAvailable($productId)) {
            return null;
        }
        
        // Check if quantity is available
        if ($product->stock_quantity !== null && $product->stock_quantity < $quantity) {
            return null;
        }
        
        try {
            DB::beginTransaction();
            
            // Check if product already exists in order
            $existingItem = OrderItem::where('order_id', $orderId)
                ->where('product_id', $productId)
                ->first();
            
            $price = $product->sale_price ?? $product->price;
            
            if ($existingItem) {
                // Update existing item
                $newQuantity = $existingItem->quantity + $quantity;
                $existingItem->update([
                    'quantity' => $newQuantity,
                    'total' => $price * $newQuantity
                ]);
            } else {
                // Create new item
                OrderItem::create([
                    'order_id' => $orderId,
                    'product_id' => $productId,
                    'name' => $product->name,
                    'quantity' => $quantity,
                    'price' => $price,
                    'total' => $price * $quantity
                ]);
            }
            
            // Decrease stock
            if ($product->stock_quantity !== null) {
                $this->productService->decreaseStock($productId, $quantity);
            }
            
            // Recalculate order totals
            $this->recalculateOrderTotals($orderId);
            
            DB::commit();
            
            return $this->getOrderById($orderId);
        } catch (\Exception $e) {
            DB::rollBack();
            return null;
        }
    }
    
    /**
     * Update order item quantity
     *
     * @param int $orderId
     * @param int $itemId
     * @param int $quantity
     * @return Order|null
     */
    public function updateOrderItemQuantity(int $orderId, int $itemId, int $quantity): ?Order
    {
        $order = $this->getOrderById($orderId);
        
        if (!$order) {
            return null;
        }
        
        // Only allow updating items in pending or processing orders
        if (!in_array($order->status, ['pending', 'processing'])) {
            return null;
        }
        
        $item = OrderItem::find($itemId);
        
        if (!$item || $item->order_id !== $orderId) {
            return null;
        }
        
        $product = $this->productService->getProductById($item->product_id);
        
        if (!$product) {
            return null;
        }
        
        // Calculate quantity difference
        $quantityDiff = $quantity - $item->quantity;
        
        // If increasing quantity, check if product is available
        if ($quantityDiff > 0) {
            // Check if product is available
            if (!$this->productService->isProductAvailable($item->product_id)) {
                return null;
            }
            
            // Check if quantity is available
            if ($product->stock_quantity !== null && $product->stock_quantity < $quantityDiff) {
                return null;
            }
        }
        
        try {
            DB::beginTransaction();
            
            // Update item quantity and total
            $price = $item->price;
            $item->update([
                'quantity' => $quantity,
                'total' => $price * $quantity
            ]);
            
            // Update stock
            if ($product->stock_quantity !== null) {
                if ($quantityDiff > 0) {
                    // Decrease stock
                    $this->productService->decreaseStock($item->product_id, $quantityDiff);
                } else if ($quantityDiff < 0) {
                    // Increase stock
                    $this->productService->increaseStock($item->product_id, abs($quantityDiff));
                }
            }
            
            // Recalculate order totals
            $this->recalculateOrderTotals($orderId);
            
            DB::commit();
            
            return $this->getOrderById($orderId);
        } catch (\Exception $e) {
            DB::rollBack();
            return null;
        }
    }
    
    /**
     * Remove item from order
     *
     * @param int $orderId
     * @param int $itemId
     * @return Order|null
     */
    public function removeOrderItem(int $orderId, int $itemId): ?Order
    {
        $order = $this->getOrderById($orderId);
        
        if (!$order) {
            return null;
        }
        
        // Only allow removing items from pending or processing orders
        if (!in_array($order->status, ['pending', 'processing'])) {
            return null;
        }
        
        $item = OrderItem::find($itemId);
        
        if (!$item || $item->order_id !== $orderId) {
            return null;
        }
        
        try {
            DB::beginTransaction();
            
            // Restore stock
            $product = $this->productService->getProductById($item->product_id);
            
            if ($product && $product->stock_quantity !== null) {
                $this->productService->increaseStock($item->product_id, $item->quantity);
            }
            
            // Delete item
            $item->delete();
            
            // Recalculate order totals
            $this->recalculateOrderTotals($orderId);
            
            DB::commit();
            
            return $this->getOrderById($orderId);
        } catch (\Exception $e) {
            DB::rollBack();
            return null;
        }
    }
    
    /**
     * Apply discount to order
     *
     * @param int $orderId
     * @param float $discount
     * @return Order|null
     */
    public function applyDiscount(int $orderId, float $discount): ?Order
    {
        $order = $this->getOrderById($orderId);
        
        if (!$order) {
            return null;
        }
        
        // Only allow applying discount to pending or processing orders
        if (!in_array($order->status, ['pending', 'processing'])) {
            return null;
        }
        
        // Ensure discount is not greater than subtotal
        if ($discount > $order->subtotal) {
            $discount = $order->subtotal;
        }
        
        // Update discount and recalculate total
        $order->update([
            'discount' => $discount,
            'total' => $order->subtotal - $discount + $order->tax
        ]);
        
        return $order;
    }
    
    /**
     * Apply tax to order
     *
     * @param int $orderId
     * @param float $taxRate
     * @return Order|null
     */
    public function applyTax(int $orderId, float $taxRate): ?Order
    {
        $order = $this->getOrderById($orderId);
        
        if (!$order) {
            return null;
        }
        
        // Only allow applying tax to pending or processing orders
        if (!in_array($order->status, ['pending', 'processing'])) {
            return null;
        }
        
        // Calculate tax
        $tax = ($order->subtotal - $order->discount) * ($taxRate / 100);
        
        // Update tax and recalculate total
        $order->update([
            'tax' => $tax,
            'total' => $order->subtotal - $order->discount + $tax
        ]);
        
        return $order;
    }
    
    /**
     * Recalculate order totals
     *
     * @param int $orderId
     * @return bool
     */
    protected function recalculateOrderTotals(int $orderId): bool
    {
        $order = Order::find($orderId);
        
        if (!$order) {
            return false;
        }
        
        // Calculate subtotal
        $subtotal = OrderItem::where('order_id', $orderId)->sum('total');
        
        // Calculate tax
        $taxableAmount = $subtotal - $order->discount;
        $tax = $taxableAmount * ($order->tax / $subtotal);
        
        // Calculate total
        $total = $subtotal - $order->discount + $tax;
        
        // Update order
        $order->update([
            'subtotal' => $subtotal,
            'tax' => $tax,
            'total' => $total
        ]);
        
        return true;
    }
    
    /**
     * Generate unique order number
     *
     * @return string
     */
    protected function generateOrderNumber(): string
    {
        $prefix = 'ORD';
        $timestamp = now()->format('YmdHis');
        $random = Str::random(4);
        
        return $prefix . '-' . $timestamp . '-' . $random;
    }
    
    /**
     * Get user orders
     *
     * @param int $userId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getUserOrders(int $userId)
    {
        return Order::with(['orderItems.product'])
            ->where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
    }
    
    /**
     * Get order validation rules
     *
     * @return array
     */
    public function getValidationRules(): array
    {
        return [
            'user_id' => 'nullable|integer|exists:users,id',
            'order_number' => 'nullable|string|max:100|unique:orders,order_number',
            'status' => 'required|string|in:pending,processing,completed,cancelled,refunded',
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'billing_address' => 'required|string',
            'shipping_address' => 'nullable|string',
            'payment_method' => 'required|string|max:100',
            'payment_status' => 'required|string|in:pending,paid,failed,refunded',
            'discount' => 'nullable|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'notes' => 'nullable|string',
            'metadata' => 'nullable|array',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.metadata' => 'nullable|array'
        ];
    }
}
