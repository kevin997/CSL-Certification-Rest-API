<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\InstructorCommission;
use App\Models\Environment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class CommissionController extends Controller
{
    /**
     * List all instructor commissions with filters
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

        $query = InstructorCommission::with(['transaction', 'environment']);

        // Filter by environment
        if ($request->has('environment_id')) {
            $query->where('environment_id', $request->environment_id);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
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

        $commissions = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $commissions
        ]);
    }

    /**
     * Get commission details
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

        $commission = InstructorCommission::with([
            'transaction.order',
            'environment',
            'withdrawalRequest'
        ])->find($id);

        if (!$commission) {
            return response()->json([
                'success' => false,
                'message' => 'Commission not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $commission
        ]);
    }

    /**
     * Approve a commission
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function approve(Request $request, int $id): JsonResponse
    {
        // Ensure user is super admin
        if (!$request->user() || $request->user()->role->value !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $commission = InstructorCommission::find($id);

        if (!$commission) {
            return response()->json([
                'success' => false,
                'message' => 'Commission not found'
            ], 404);
        }

        if ($commission->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending commissions can be approved'
            ], 400);
        }

        $commission->status = 'approved';
        $commission->approved_at = now();
        $commission->save();

        return response()->json([
            'success' => true,
            'message' => 'Commission approved successfully',
            'data' => $commission
        ]);
    }

    /**
     * Bulk approve multiple commissions
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function bulkApprove(Request $request): JsonResponse
    {
        // Ensure user is super admin
        if (!$request->user() || $request->user()->role->value !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'commission_ids' => 'required|array',
            'commission_ids.*' => 'required|integer|exists:instructor_commissions,id'
        ]);

        $updated = InstructorCommission::whereIn('id', $request->commission_ids)
            ->where('status', 'pending')
            ->update([
                'status' => 'approved',
                'approved_at' => now()
            ]);

        return response()->json([
            'success' => true,
            'message' => "{$updated} commission(s) approved successfully",
            'approved_count' => $updated
        ]);
    }

    /**
     * Get commission statistics
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

        $query = InstructorCommission::query();

        // Filter by environment if specified
        if ($request->has('environment_id')) {
            $query->where('environment_id', $request->environment_id);
        }

        $stats = [
            'total_commissions' => $query->count(),
            'total_amount' => $query->sum('amount'),
            'pending_amount' => InstructorCommission::where('status', 'pending')
                ->when($request->has('environment_id'), function ($q) use ($request) {
                    $q->where('environment_id', $request->environment_id);
                })
                ->sum('amount'),
            'approved_amount' => InstructorCommission::where('status', 'approved')
                ->when($request->has('environment_id'), function ($q) use ($request) {
                    $q->where('environment_id', $request->environment_id);
                })
                ->sum('amount'),
            'paid_amount' => InstructorCommission::where('status', 'paid')
                ->when($request->has('environment_id'), function ($q) use ($request) {
                    $q->where('environment_id', $request->environment_id);
                })
                ->sum('amount'),
            'pending_count' => InstructorCommission::where('status', 'pending')
                ->when($request->has('environment_id'), function ($q) use ($request) {
                    $q->where('environment_id', $request->environment_id);
                })
                ->count(),
            'approved_count' => InstructorCommission::where('status', 'approved')
                ->when($request->has('environment_id'), function ($q) use ($request) {
                    $q->where('environment_id', $request->environment_id);
                })
                ->count(),
            'paid_count' => InstructorCommission::where('status', 'paid')
                ->when($request->has('environment_id'), function ($q) use ($request) {
                    $q->where('environment_id', $request->environment_id);
                })
                ->count(),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Filter commissions by environment
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

        $query = InstructorCommission::where('environment_id', $environmentId)
            ->with(['transaction', 'environment']);

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $query->orderBy('created_at', 'desc');

        $commissions = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $commissions,
            'environment' => $environment
        ]);
    }
}
