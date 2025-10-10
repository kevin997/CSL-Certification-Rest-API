<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\WithdrawalRequest;
use App\Models\InstructorCommission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class WithdrawalRequestController extends Controller
{
    /**
     * List all withdrawal requests with filters
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

        $query = WithdrawalRequest::with(['environment', 'commissions']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by environment
        if ($request->has('environment_id')) {
            $query->where('environment_id', $request->environment_id);
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

        $withdrawals = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $withdrawals
        ]);
    }

    /**
     * Get withdrawal request details
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

        $withdrawal = WithdrawalRequest::with([
            'environment',
            'commissions.transaction'
        ])->find($id);

        if (!$withdrawal) {
            return response()->json([
                'success' => false,
                'message' => 'Withdrawal request not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $withdrawal
        ]);
    }

    /**
     * Approve a withdrawal request
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

        $withdrawal = WithdrawalRequest::find($id);

        if (!$withdrawal) {
            return response()->json([
                'success' => false,
                'message' => 'Withdrawal request not found'
            ], 404);
        }

        if ($withdrawal->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending withdrawal requests can be approved'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $withdrawal->status = 'approved';
            $withdrawal->approved_by = $request->user()->id;
            $withdrawal->approved_at = now();
            $withdrawal->save();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request approved successfully',
                'data' => $withdrawal
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve withdrawal request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject a withdrawal request
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function reject(Request $request, int $id): JsonResponse
    {
        // Ensure user is super admin
        if (!$request->user() || $request->user()->role->value !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'rejection_reason' => 'required|string|max:500'
        ]);

        $withdrawal = WithdrawalRequest::find($id);

        if (!$withdrawal) {
            return response()->json([
                'success' => false,
                'message' => 'Withdrawal request not found'
            ], 404);
        }

        if ($withdrawal->status !== 'pending' && $withdrawal->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Only pending or approved withdrawal requests can be rejected'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $withdrawal->status = 'rejected';
            $withdrawal->rejection_reason = $request->rejection_reason;
            $withdrawal->rejected_at = now();
            $withdrawal->save();

            // Set related commissions back to approved status
            InstructorCommission::where('withdrawal_request_id', $withdrawal->id)
                ->update(['status' => 'approved']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request rejected successfully',
                'data' => $withdrawal
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject withdrawal request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark withdrawal request as processed/paid
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function process(Request $request, int $id): JsonResponse
    {
        // Ensure user is super admin
        if (!$request->user() || $request->user()->role->value !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'payment_reference' => 'required|string|max:255|unique:withdrawal_requests,payment_reference'
        ]);

        $withdrawal = WithdrawalRequest::find($id);

        if (!$withdrawal) {
            return response()->json([
                'success' => false,
                'message' => 'Withdrawal request not found'
            ], 404);
        }

        if ($withdrawal->status !== 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'Only approved withdrawal requests can be processed'
            ], 400);
        }

        DB::beginTransaction();
        try {
            $withdrawal->status = 'completed';
            $withdrawal->payment_reference = $request->payment_reference;
            $withdrawal->processed_at = now();
            $withdrawal->save();

            // Update related commissions to paid status
            InstructorCommission::where('withdrawal_request_id', $withdrawal->id)
                ->update([
                    'status' => 'paid',
                    'paid_at' => now()
                ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request processed successfully',
                'data' => $withdrawal
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process withdrawal request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get withdrawal statistics
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

        $query = WithdrawalRequest::query();

        // Filter by environment if specified
        if ($request->has('environment_id')) {
            $query->where('environment_id', $request->environment_id);
        }

        $stats = [
            'total_requests' => $query->count(),
            'total_amount' => $query->sum('amount'),
            'pending_amount' => WithdrawalRequest::where('status', 'pending')
                ->when($request->has('environment_id'), function ($q) use ($request) {
                    $q->where('environment_id', $request->environment_id);
                })
                ->sum('amount'),
            'approved_amount' => WithdrawalRequest::where('status', 'approved')
                ->when($request->has('environment_id'), function ($q) use ($request) {
                    $q->where('environment_id', $request->environment_id);
                })
                ->sum('amount'),
            'completed_amount' => WithdrawalRequest::where('status', 'completed')
                ->when($request->has('environment_id'), function ($q) use ($request) {
                    $q->where('environment_id', $request->environment_id);
                })
                ->sum('amount'),
            'rejected_amount' => WithdrawalRequest::where('status', 'rejected')
                ->when($request->has('environment_id'), function ($q) use ($request) {
                    $q->where('environment_id', $request->environment_id);
                })
                ->sum('amount'),
            'pending_count' => WithdrawalRequest::where('status', 'pending')
                ->when($request->has('environment_id'), function ($q) use ($request) {
                    $q->where('environment_id', $request->environment_id);
                })
                ->count(),
            'approved_count' => WithdrawalRequest::where('status', 'approved')
                ->when($request->has('environment_id'), function ($q) use ($request) {
                    $q->where('environment_id', $request->environment_id);
                })
                ->count(),
            'completed_count' => WithdrawalRequest::where('status', 'completed')
                ->when($request->has('environment_id'), function ($q) use ($request) {
                    $q->where('environment_id', $request->environment_id);
                })
                ->count(),
            'rejected_count' => WithdrawalRequest::where('status', 'rejected')
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
}
