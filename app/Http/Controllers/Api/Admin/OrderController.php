<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    private function guard(Request $request): bool
    {
        return $request->user() && $request->user()->role->value === 'super_admin';
    }

    public function index(Request $request): JsonResponse
    {
        if (!$this->guard($request)) return response()->json(['message' => 'Unauthorized'], 403);

        $query = Order::with(['user:id,name,email', 'items.product:id,title,price', 'environment:id,name']);

        if ($request->has('environment_id')) $query->where('environment_id', $request->environment_id);
        if ($request->has('status'))         $query->where('status', $request->status);
        if ($request->has('start_date'))     $query->whereDate('created_at', '>=', $request->start_date);
        if ($request->has('end_date'))       $query->whereDate('created_at', '<=', $request->end_date);
        if ($request->filled('search')) {
            $s = $request->search;
            $query->where(fn($q) => $q->where('order_number','like',"%$s%")->orWhere('billing_name','like',"%$s%")->orWhere('billing_email','like',"%$s%"));
        }

        $sort = in_array($request->input('sort_field'), ['created_at','total_amount','status','order_number'])
            ? $request->input('sort_field') : 'created_at';
        $query->orderBy($sort, $request->input('sort_dir','desc') === 'asc' ? 'asc' : 'desc');

        return response()->json(['success' => true, 'data' => $query->paginate($request->get('per_page', 25))]);
    }

    public function stats(Request $request): JsonResponse
    {
        if (!$this->guard($request)) return response()->json(['message' => 'Unauthorized'], 403);

        $base = Order::query();
        if ($request->has('environment_id')) $base->where('environment_id', $request->environment_id);
        if ($request->has('start_date'))     $base->whereDate('created_at', '>=', $request->start_date);
        if ($request->has('end_date'))       $base->whereDate('created_at', '<=', $request->end_date);

        $completed = (clone $base)->where('status','completed');
        $failed    = (clone $base)->where('status','failed');
        $total     = $completed->count() + $failed->count();

        return response()->json(['success' => true, 'data' => [
            'total_orders'    => (clone $base)->count(),
            'total_revenue'   => (clone $base)->where('status','completed')->sum('total_amount'),
            'completed_count' => $completed->count(),
            'pending_count'   => (clone $base)->where('status','pending')->count(),
            'failed_count'    => $failed->count(),
            'refunded_count'  => (clone $base)->where('status','refunded')->count(),
            'avg_order_value' => (clone $base)->where('status','completed')->avg('total_amount'),
            'success_rate'    => $total > 0 ? round(($completed->count() / $total) * 100, 2) : 0,
        ]]);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        if (!$this->guard($request)) return response()->json(['message' => 'Unauthorized'], 403);

        $order = Order::with(['user','items.product','environment','transactions'])->find($id);
        if (!$order) return response()->json(['success'=>false,'message'=>'Not found'], 404);

        return response()->json(['success' => true, 'data' => $order]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        if (!$this->guard($request)) return response()->json(['message' => 'Unauthorized'], 403);

        $order = Order::find($id);
        if (!$order) return response()->json(['success'=>false,'message'=>'Not found'], 404);

        $validated = $request->validate([
            'status'       => 'sometimes|in:pending,processing,completed,failed,refunded,partially_refunded',
            'total_amount' => 'sometimes|numeric|min:0',
            'notes'        => 'sometimes|nullable|string|max:1000',
            'billing_name' => 'sometimes|string|max:255',
            'billing_email'=> 'sometimes|email|max:255',
        ]);

        $order->fill($validated)->save();

        return response()->json(['success' => true, 'data' => $order->fresh(['user','items.product','environment'])]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        if (!$this->guard($request)) return response()->json(['message' => 'Unauthorized'], 403);

        $order = Order::find($id);
        if (!$order) return response()->json(['success'=>false,'message'=>'Not found'], 404);

        $order->delete();
        return response()->json(['success' => true, 'message' => 'Order deleted']);
    }
}
