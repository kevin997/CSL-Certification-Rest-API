<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Models\InstructorCommission;
use App\Models\Environment;
use App\Models\EnvironmentPaymentConfig;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class EarningsController extends Controller
{
    /**
     * List instructor's commissions
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Get instructor's environment (assuming instructor is the environment owner)
        $environment = $user->ownedEnvironments()->first();

        if (!$environment) {
            return response()->json([
                'success' => false,
                'message' => 'No environment found for this instructor'
            ], 404);
        }

        $query = InstructorCommission::where('environment_id', $environment->id)
            ->with(['transaction.order']);

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
     * Get earnings statistics
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Get instructor's environment
        $environment = $user->ownedEnvironments()->first();

        if (!$environment) {
            return response()->json([
                'success' => false,
                'message' => 'No environment found for this instructor'
            ], 404);
        }

        $query = InstructorCommission::where('environment_id', $environment->id);

        $stats = [
            'total_earned' => $query->sum('instructor_payout_amount'),
            'total_paid' => InstructorCommission::where('environment_id', $environment->id)
                ->where('status', 'paid')
                ->sum('instructor_payout_amount'),
            'pending_amount' => InstructorCommission::where('environment_id', $environment->id)
                ->where('status', 'pending')
                ->sum('instructor_payout_amount'),
            'approved_amount' => InstructorCommission::where('environment_id', $environment->id)
                ->where('status', 'approved')
                ->sum('instructor_payout_amount'),
            'available_balance' => InstructorCommission::where('environment_id', $environment->id)
                ->whereIn('status', ['approved'])
                ->whereNull('withdrawal_request_id')
                ->sum('instructor_payout_amount'),
            'pending_withdrawal' => InstructorCommission::where('environment_id', $environment->id)
                ->whereNotNull('withdrawal_request_id')
                ->whereHas('withdrawalRequest', function ($q) {
                    $q->whereIn('status', ['pending', 'approved']);
                })
                ->sum('instructor_payout_amount'),
            'total_commissions' => $query->count(),
            'paid_count' => InstructorCommission::where('environment_id', $environment->id)
                ->where('status', 'paid')
                ->count(),
            // Whether this env routes through centralized gateways. When false, the
            // earnings above were collected directly by the instructor (their own
            // gateway / offline) — KURSA holds nothing and there is no withdrawable
            // settlement flow. The UI relabels accordingly.
            'centralized' => $this->isCentralized($environment->id),
        ];

        return response()->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Get available balance for withdrawal
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function balance(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Get instructor's environment
        $environment = $user->ownedEnvironments()->first();

        if (!$environment) {
            return response()->json([
                'success' => false,
                'message' => 'No environment found for this instructor'
            ], 404);
        }

        // Available balance = approved commissions that are not attached to any withdrawal request
        $availableBalance = InstructorCommission::where('environment_id', $environment->id)
            ->where('status', 'approved')
            ->whereNull('withdrawal_request_id')
            ->sum('instructor_payout_amount');

        // Pending withdrawal = commissions attached to pending/approved withdrawal requests
        $pendingWithdrawal = InstructorCommission::where('environment_id', $environment->id)
            ->whereNotNull('withdrawal_request_id')
            ->whereHas('withdrawalRequest', function ($q) {
                $q->whereIn('status', ['pending', 'approved']);
            })
            ->sum('instructor_payout_amount');

        // Pending approval = commissions still awaiting super-admin approval (status
        // 'pending', not yet attached to a withdrawal). These are NOT withdrawable yet,
        // but surfacing the amount prevents the "$0 available while the dashboard says
        // you have earnings" confusion: the money exists, it just awaits approval.
        $pendingAmount = InstructorCommission::where('environment_id', $environment->id)
            ->where('status', 'pending')
            ->whereNull('withdrawal_request_id')
            ->sum('instructor_payout_amount');

        return response()->json([
            'success' => true,
            'data' => [
                'available_balance' => $availableBalance,
                'pending_withdrawal' => $pendingWithdrawal,
                'pending_amount' => $pendingAmount,
                // When false, this env collects payments directly (no centralized
                // gateway): KURSA holds no balance and the withdraw flow does not apply.
                'centralized' => $this->isCentralized($environment->id),
                'currency' => 'USD'
            ]
        ]);
    }

    /**
     * Whether the given environment routes payments through the centralized
     * gateways (and therefore has a real KURSA-held, withdrawable balance).
     * Defaults to false (safer: never implies a KURSA liability that isn't held).
     *
     * @param int $environmentId
     * @return bool
     */
    private function isCentralized(int $environmentId): bool
    {
        $config = EnvironmentPaymentConfig::where('environment_id', $environmentId)
            ->where('is_active', true)
            ->first();

        return $config ? (bool) $config->use_centralized_gateways : false;
    }
}
