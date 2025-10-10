<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\Environment;
use App\Models\EnvironmentPaymentConfig;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CentralizedTransactionController extends Controller
{
    /**
     * List all centralized transactions
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Ensure user is super admin
        if (!$request->user() || $request->user()->role->value !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Get environments that use centralized gateways
        $centralizedEnvironmentIds = EnvironmentPaymentConfig::where('use_centralized_gateways', true)
            ->pluck('environment_id')
            ->toArray();

        $query = Transaction::with(['order', 'environment'])
            ->whereIn('environment_id', $centralizedEnvironmentIds);

        // Filter by environment
        if ($request->has('environment_id')) {
            $query->where('environment_id', $request->environment_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by gateway
        if ($request->has('gateway')) {
            $query->where('gateway', $request->gateway);
        }

        // Filter by date range
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        // Sort by created_at descending
        $query->orderBy('created_at', 'desc');

        $transactions = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $transactions
        ]);
    }

    /**
     * Get transaction details
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
    {
        // Ensure user is super admin
        if (!$request->user() || $request->user()->role->value !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $transaction = Transaction::with([
            'order.user',
            'environment',
            'commissions'
        ])->find($id);

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Transaction not found'
            ], 404);
        }

        // Verify this is a centralized transaction
        $config = EnvironmentPaymentConfig::where('environment_id', $transaction->environment_id)->first();
        if (!$config || !$config->use_centralized_gateways) {
            return response()->json([
                'success' => false,
                'message' => 'This is not a centralized transaction'
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => $transaction
        ]);
    }

    /**
     * Get transaction statistics
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function stats(Request $request): JsonResponse
    {
        // Ensure user is super admin
        if (!$request->user() || $request->user()->role->value !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Get environments that use centralized gateways
        $centralizedEnvironmentIds = EnvironmentPaymentConfig::where('use_centralized_gateways', true)
            ->pluck('environment_id')
            ->toArray();

        $query = Transaction::whereIn('environment_id', $centralizedEnvironmentIds);

        // Filter by environment if specified
        if ($request->has('environment_id')) {
            $query->where('environment_id', $request->environment_id);
        }

        // Filter by date range if specified
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $stats = [
            'total_transactions' => $query->count(),
            'total_revenue' => $query->where('status', 'completed')->sum('total_amount'),
            'average_transaction' => $query->where('status', 'completed')->avg('total_amount'),
            'completed_count' => Transaction::whereIn('environment_id', $centralizedEnvironmentIds)
                ->where('status', 'completed')
                ->when($request->has('environment_id'), function ($q) use ($request) {
                    $q->where('environment_id', $request->environment_id);
                })
                ->when($request->has('start_date'), function ($q) use ($request) {
                    $q->whereDate('created_at', '>=', $request->start_date);
                })
                ->when($request->has('end_date'), function ($q) use ($request) {
                    $q->whereDate('created_at', '<=', $request->end_date);
                })
                ->count(),
            'pending_count' => Transaction::whereIn('environment_id', $centralizedEnvironmentIds)
                ->where('status', 'pending')
                ->when($request->has('environment_id'), function ($q) use ($request) {
                    $q->where('environment_id', $request->environment_id);
                })
                ->when($request->has('start_date'), function ($q) use ($request) {
                    $q->whereDate('created_at', '>=', $request->start_date);
                })
                ->when($request->has('end_date'), function ($q) use ($request) {
                    $q->whereDate('created_at', '<=', $request->end_date);
                })
                ->count(),
            'failed_count' => Transaction::whereIn('environment_id', $centralizedEnvironmentIds)
                ->where('status', 'failed')
                ->when($request->has('environment_id'), function ($q) use ($request) {
                    $q->where('environment_id', $request->environment_id);
                })
                ->when($request->has('start_date'), function ($q) use ($request) {
                    $q->whereDate('created_at', '>=', $request->start_date);
                })
                ->when($request->has('end_date'), function ($q) use ($request) {
                    $q->whereDate('created_at', '<=', $request->end_date);
                })
                ->count(),
        ];

        // Calculate success rate
        $totalNonPending = $stats['completed_count'] + $stats['failed_count'];
        $stats['success_rate'] = $totalNonPending > 0
            ? round(($stats['completed_count'] / $totalNonPending) * 100, 2)
            : 0;

        // Get revenue by gateway
        $revenueByGateway = Transaction::whereIn('environment_id', $centralizedEnvironmentIds)
            ->where('status', 'completed')
            ->when($request->has('environment_id'), function ($q) use ($request) {
                $q->where('environment_id', $request->environment_id);
            })
            ->when($request->has('start_date'), function ($q) use ($request) {
                $q->whereDate('created_at', '>=', $request->start_date);
            })
            ->when($request->has('end_date'), function ($q) use ($request) {
                $q->whereDate('created_at', '<=', $request->end_date);
            })
            ->select('gateway', DB::raw('SUM(total_amount) as total'), DB::raw('COUNT(*) as count'))
            ->groupBy('gateway')
            ->get();

        $stats['revenue_by_gateway'] = $revenueByGateway;

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Filter transactions by environment
     *
     * @param Request $request
     * @param int $environmentId
     * @return JsonResponse
     */
    public function byEnvironment(Request $request, int $environmentId): JsonResponse
    {
        // Ensure user is super admin
        if (!$request->user() || $request->user()->role->value !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $environment = Environment::find($environmentId);

        if (!$environment) {
            return response()->json([
                'success' => false,
                'message' => 'Environment not found'
            ], 404);
        }

        // Verify this environment uses centralized gateways
        $config = EnvironmentPaymentConfig::where('environment_id', $environmentId)->first();
        if (!$config || !$config->use_centralized_gateways) {
            return response()->json([
                'success' => false,
                'message' => 'This environment does not use centralized payment gateways'
            ], 400);
        }

        $query = Transaction::where('environment_id', $environmentId)
            ->with(['order', 'environment']);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $query->orderBy('created_at', 'desc');

        $transactions = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $transactions,
            'environment' => $environment
        ]);
    }

    /**
     * Export transactions to CSV
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function export(Request $request)
    {
        // Ensure user is super admin
        if (!$request->user() || $request->user()->role->value !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Get environments that use centralized gateways
        $centralizedEnvironmentIds = EnvironmentPaymentConfig::where('use_centralized_gateways', true)
            ->pluck('environment_id')
            ->toArray();

        $query = Transaction::with(['order', 'environment'])
            ->whereIn('environment_id', $centralizedEnvironmentIds);

        // Apply filters
        if ($request->has('environment_id')) {
            $query->where('environment_id', $request->environment_id);
        }
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        if ($request->has('gateway')) {
            $query->where('gateway', $request->gateway);
        }
        if ($request->has('start_date')) {
            $query->whereDate('created_at', '>=', $request->start_date);
        }
        if ($request->has('end_date')) {
            $query->whereDate('created_at', '<=', $request->end_date);
        }

        $transactions = $query->orderBy('created_at', 'desc')->get();

        // Generate CSV
        $filename = 'centralized_transactions_' . date('Y-m-d_His') . '.csv';
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function() use ($transactions) {
            $file = fopen('php://output', 'w');

            // CSV Headers
            fputcsv($file, [
                'Transaction ID',
                'Order ID',
                'Environment',
                'Gateway',
                'Amount',
                'Currency',
                'Status',
                'Created At',
                'Paid At'
            ]);

            // CSV Rows
            foreach ($transactions as $transaction) {
                fputcsv($file, [
                    $transaction->id,
                    $transaction->order_id,
                    $transaction->environment->name ?? 'N/A',
                    $transaction->gateway,
                    $transaction->total_amount,
                    $transaction->currency,
                    $transaction->status,
                    $transaction->created_at->format('Y-m-d H:i:s'),
                    $transaction->paid_at ? $transaction->paid_at->format('Y-m-d H:i:s') : 'N/A'
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }
}
