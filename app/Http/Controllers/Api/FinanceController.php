<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Response;

class FinanceController extends Controller
{
    /**
     * Get finance overview for the current environment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function overview(Request $request)
    {
        // Get the environment ID from the authenticated user
        $environmentId = session('current_environment_id');
        
        if (!$environmentId) {
            return response()->json([
                'success' => false,
                'message' => 'No environment selected'
            ], 400);
        }

        // Get subscription details
        $subscription = Subscription::where('environment_id', $environmentId)
            ->with('plan')
            ->orderBy('created_at', 'desc')
            ->first();

        // Get order statistics
        $orderStats = $this->getOrderStats($environmentId);

        // Get revenue statistics
        $revenueStats = $this->getRevenueStats($environmentId);

        // Get top products by revenue
        $topProducts = $this->getTopProducts($environmentId, 5);

        // Get recent transactions
        $recentTransactions = Transaction::where('environment_id', $environmentId)
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'subscription' => $subscription,
                'order_stats' => $orderStats,
                'revenue_stats' => $revenueStats,
                'top_products' => $topProducts,
                'recent_transactions' => $recentTransactions
            ]
        ]);
    }

    /**
     * Get order statistics for the environment.
     *
     * @param  int  $environmentId
     * @return array
     */
    private function getOrderStats($environmentId)
    {
        $totalOrders = Order::where('environment_id', $environmentId)->count();
        $pendingOrders = Order::where('environment_id', $environmentId)
            ->where('status', 'pending')
            ->count();
        $completedOrders = Order::where('environment_id', $environmentId)
            ->where('status', 'completed')
            ->count();
        $canceledOrders = Order::where('environment_id', $environmentId)
            ->where('status', 'canceled')
            ->count();

        return [
            'total' => $totalOrders,
            'pending' => $pendingOrders,
            'completed' => $completedOrders,
            'canceled' => $canceledOrders
        ];
    }

    /**
     * Get revenue statistics for the environment.
     *
     * @param  int  $environmentId
     * @return array
     */
    private function getRevenueStats($environmentId)
    {
        $totalRevenue = Order::where('environment_id', $environmentId)
            ->where('status', 'completed')
            ->sum('total');

        $monthlyRevenue = Order::where('environment_id', $environmentId)
            ->where('status', 'completed')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->sum('total');

        $yearlyRevenue = Order::where('environment_id', $environmentId)
            ->where('status', 'completed')
            ->whereYear('created_at', now()->year)
            ->sum('total');

        // Get monthly revenue for the last 12 months
        $monthlyRevenueData = Order::where('environment_id', $environmentId)
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subMonths(12))
            ->select(
                DB::raw('YEAR(created_at) as year'),
                DB::raw('MONTH(created_at) as month'),
                DB::raw('SUM(total) as revenue')
            )
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT),
                    'revenue' => $item->revenue
                ];
            });

        return [
            'total' => $totalRevenue,
            'monthly' => $monthlyRevenue,
            'yearly' => $yearlyRevenue,
            'monthly_data' => $monthlyRevenueData
        ];
    }

    /**
     * Get top products by revenue.
     *
     * @param  int  $environmentId
     * @param  int  $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function getTopProducts($environmentId, $limit = 5)
    {
        return DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.environment_id', $environmentId)
            ->where('orders.status', 'completed')
            ->select(
                'products.id',
                'products.name',
                'products.type',
                DB::raw('SUM(order_items.quantity) as total_quantity'),
                DB::raw('SUM(order_items.price * order_items.quantity) as total_revenue')
            )
            ->groupBy('products.id', 'products.name', 'products.type')
            ->orderBy('total_revenue', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get subscription details for the environment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function subscription(Request $request)
    {
        // Get the environment ID from the authenticated user
        $environmentId = session('current_environment_id');
        
        if (!$environmentId) {
            return response()->json([
                'success' => false,
                'message' => 'No environment selected'
            ], 400);
        }

        $subscription = Subscription::where('environment_id', $environmentId)
            ->with('plan')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$subscription) {
            return response()->json([
                'success' => false,
                'message' => 'No subscription found for this environment'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $subscription
        ]);
    }

    /**
     * Get orders for the environment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function orders(Request $request)
    {
        // Get the environment ID from the authenticated user
        $environmentId = session('current_environment_id');
        
        if (!$environmentId) {
            return response()->json([
                'success' => false,
                'message' => 'No environment selected'
            ], 400);
        }

        $query = Order::where('environment_id', $environmentId);

        // Apply filters if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('order_number', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($q) use ($search) {
                      $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        // Apply sorting
        $sortField = $request->input('sort_field', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $allowedSortFields = ['created_at', 'order_number', 'total', 'status'];
        
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Get paginated results with relationships
        $perPage = $request->input('per_page', 10);
        $orders = $query->with(['user', 'items.product'])->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $orders
        ]);
    }

    /**
     * Get transactions for the environment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function transactions(Request $request)
    {
        // Get the environment ID from the authenticated user
        $environmentId = session('current_environment_id');
        
        if (!$environmentId) {
            return response()->json([
                'success' => false,
                'message' => 'No environment selected'
            ], 400);
        }

        $query = Transaction::where('environment_id', $environmentId);

        // Apply filters if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('date_from')) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('created_at', '<=', $request->date_to);
        }

        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->payment_method);
        }

        // Apply sorting
        $sortField = $request->input('sort_field', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $allowedSortFields = ['created_at', 'amount', 'status', 'payment_method'];
        
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Get paginated results with relationships
        $perPage = $request->input('per_page', 10);
        $transactions = $query->with(['order', 'user'])->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $transactions
        ]);
    }

    /**
     * Get revenue by product type.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function revenueByProductType(Request $request)
    {
        // Get the environment ID from the authenticated user
        $environmentId = session('current_environment_id');
        
        if (!$environmentId) {
            return response()->json([
                'success' => false,
                'message' => 'No environment selected'
            ], 400);
        }

        $revenueByType = DB::table('order_items')
            ->join('orders', 'order_items.order_id', '=', 'orders.id')
            ->join('products', 'order_items.product_id', '=', 'products.id')
            ->where('orders.environment_id', $environmentId)
            ->where('orders.status', 'completed')
            ->select(
                'products.type',
                DB::raw('SUM(order_items.price * order_items.quantity) as total_revenue')
            )
            ->groupBy('products.type')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $revenueByType
        ]);
    }
}
