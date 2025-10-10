<?php

namespace App\Services;

use App\Models\InstructorCommission;
use App\Models\Transaction;
use App\Models\Order;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Instructor Commission Service
 *
 * Manages instructor commission tracking for transactions
 * processed through centralized payment gateways.
 */
class InstructorCommissionService
{
    /**
     * @var EnvironmentPaymentConfigService
     */
    protected $environmentPaymentConfigService;

    /**
     * Constructor
     *
     * @param EnvironmentPaymentConfigService $environmentPaymentConfigService
     */
    public function __construct(EnvironmentPaymentConfigService $environmentPaymentConfigService)
    {
        $this->environmentPaymentConfigService = $environmentPaymentConfigService;
    }

    /**
     * Create commission record from transaction
     *
     * @param Transaction $transaction
     * @return InstructorCommission
     * @throws \Exception
     */
    public function createCommissionRecord(Transaction $transaction): InstructorCommission
    {
        try {
            // Get payment config to retrieve commission rate
            $config = $this->environmentPaymentConfigService->getConfig($transaction->environment_id);

            if (!$config) {
                throw new \Exception("Payment config not found for environment ID: {$transaction->environment_id}");
            }

            // Get order to access order details
            $order = Order::find($transaction->order_id);

            if (!$order) {
                throw new \Exception("Order not found for transaction ID: {$transaction->id}");
            }

            // Calculate platform fee and instructor payout
            // Platform takes platform_fee_rate (e.g., 17%), instructor receives the rest (83%)
            $grossAmount = $transaction->total_amount;
            $platformFeeRate = $config->platform_fee_rate;
            $platformFeeAmount = round($grossAmount * $platformFeeRate, 2);
            $instructorPayoutAmount = round($grossAmount - $platformFeeAmount, 2);

            // Create commission record
            $commission = InstructorCommission::create([
                'environment_id' => $transaction->environment_id,
                'transaction_id' => $transaction->id,
                'order_id' => $transaction->order_id,
                'gross_amount' => $grossAmount,
                'platform_fee_rate' => $platformFeeRate,
                'platform_fee_amount' => $platformFeeAmount,
                'instructor_payout_amount' => $instructorPayoutAmount,
                'currency' => $transaction->currency ?? 'XAF',
                'status' => 'pending',
            ]);

            Log::info('Commission record created', [
                'commission_id' => $commission->id,
                'environment_id' => $transaction->environment_id,
                'transaction_id' => $transaction->id,
                'gross_amount' => $grossAmount,
                'platform_fee_amount' => $platformFeeAmount,
                'instructor_payout_amount' => $instructorPayoutAmount,
            ]);

            return $commission;
        } catch (\Exception $e) {
            Log::error('Failed to create commission record', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Calculate net earnings (available balance) for environment
     *
     * @param int $environmentId
     * @return float
     */
    public function calculateNetEarnings(int $environmentId): float
    {
        return $this->getAvailableBalance($environmentId);
    }

    /**
     * Get total earned for environment (all statuses)
     *
     * @param int $environmentId
     * @return float
     */
    public function getTotalEarned(int $environmentId): float
    {
        return InstructorCommission::where('environment_id', $environmentId)
            ->sum('instructor_payout_amount');
    }

    /**
     * Get total paid for environment (completed withdrawals)
     *
     * @param int $environmentId
     * @return float
     */
    public function getTotalPaid(int $environmentId): float
    {
        return InstructorCommission::where('environment_id', $environmentId)
            ->where('status', 'paid')
            ->whereNotNull('paid_at')
            ->sum('instructor_payout_amount');
    }

    /**
     * Get available balance for environment (approved commissions not yet withdrawn)
     *
     * @param int $environmentId
     * @return float
     */
    public function getAvailableBalance(int $environmentId): float
    {
        return InstructorCommission::where('environment_id', $environmentId)
            ->where('status', 'approved')
            ->whereNull('withdrawal_request_id')
            ->sum('instructor_payout_amount');
    }

    /**
     * Get commissions for environment with filters
     *
     * @param int $environmentId
     * @param array $filters
     * @return Collection
     */
    public function getCommissions(int $environmentId, array $filters = []): Collection
    {
        $query = InstructorCommission::where('environment_id', $environmentId)
            ->with(['transaction', 'order', 'withdrawalRequest']);

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        if (isset($filters['has_withdrawal'])) {
            if ($filters['has_withdrawal']) {
                $query->whereNotNull('withdrawal_request_id');
            } else {
                $query->whereNull('withdrawal_request_id');
            }
        }

        // Default ordering by newest first
        $query->orderBy('created_at', 'desc');

        return $query->get();
    }

    /**
     * Approve commission
     *
     * @param InstructorCommission $commission
     * @return bool
     */
    public function approveCommission(InstructorCommission $commission): bool
    {
        try {
            if ($commission->status !== 'pending') {
                throw new \Exception("Commission must be in 'pending' status to approve. Current status: {$commission->status}");
            }

            $commission->update(['status' => 'approved']);

            Log::info('Commission approved', [
                'commission_id' => $commission->id,
                'environment_id' => $commission->environment_id,
                'instructor_payout_amount' => $commission->instructor_payout_amount,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to approve commission', [
                'commission_id' => $commission->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Bulk approve commissions
     *
     * @param array $commissionIds
     * @return array
     */
    public function bulkApproveCommissions(array $commissionIds): array
    {
        $approved = 0;
        $failed = 0;
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($commissionIds as $commissionId) {
                $commission = InstructorCommission::find($commissionId);

                if (!$commission) {
                    $failed++;
                    $errors[] = "Commission ID {$commissionId} not found";
                    continue;
                }

                if ($this->approveCommission($commission)) {
                    $approved++;
                } else {
                    $failed++;
                    $errors[] = "Failed to approve commission ID {$commissionId}";
                }
            }

            DB::commit();

            Log::info('Bulk commission approval completed', [
                'approved' => $approved,
                'failed' => $failed,
            ]);

            return [
                'success' => true,
                'approved' => $approved,
                'failed' => $failed,
                'errors' => $errors,
            ];
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Bulk commission approval failed', [
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'approved' => 0,
                'failed' => count($commissionIds),
                'errors' => [$e->getMessage()],
            ];
        }
    }

    /**
     * Mark commission as paid
     *
     * @param InstructorCommission $commission
     * @param string $paymentReference
     * @return bool
     */
    public function markAsPaid(InstructorCommission $commission, string $paymentReference): bool
    {
        try {
            $commission->update([
                'status' => 'paid',
                'paid_at' => now(),
                'payment_reference' => $paymentReference,
            ]);

            Log::info('Commission marked as paid', [
                'commission_id' => $commission->id,
                'payment_reference' => $paymentReference,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to mark commission as paid', [
                'commission_id' => $commission->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
