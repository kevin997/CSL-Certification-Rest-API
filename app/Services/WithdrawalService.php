<?php

namespace App\Services;

use App\Models\WithdrawalRequest;
use App\Models\InstructorCommission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Withdrawal Service
 *
 * Manages withdrawal requests from instructors for their earned commissions.
 */
class WithdrawalService
{
    /**
     * @var InstructorCommissionService
     */
    protected $instructorCommissionService;

    /**
     * @var EnvironmentPaymentConfigService
     */
    protected $environmentPaymentConfigService;

    /**
     * Constructor
     *
     * @param InstructorCommissionService $instructorCommissionService
     * @param EnvironmentPaymentConfigService $environmentPaymentConfigService
     */
    public function __construct(
        InstructorCommissionService $instructorCommissionService,
        EnvironmentPaymentConfigService $environmentPaymentConfigService
    ) {
        $this->instructorCommissionService = $instructorCommissionService;
        $this->environmentPaymentConfigService = $environmentPaymentConfigService;
    }

    /**
     * Create withdrawal request
     *
     * @param int $environmentId
     * @param int $userId
     * @param float $amount
     * @param array $details
     * @return WithdrawalRequest
     * @throws \Exception
     */
    public function createWithdrawalRequest(int $environmentId, int $userId, float $amount, array $details): WithdrawalRequest
    {
        DB::beginTransaction();

        try {
            // Validate withdrawal amount
            if (!$this->validateWithdrawalAmount($environmentId, $amount)) {
                throw new \Exception("Invalid withdrawal amount");
            }

            // Validate withdrawal method
            if (!isset($details['method']) || !in_array($details['method'], ['bank_transfer', 'paypal', 'mobile_money'])) {
                throw new \Exception("Invalid withdrawal method");
            }

            // Get approved commissions to include in this withdrawal
            $commissions = InstructorCommission::where('environment_id', $environmentId)
                ->where('status', 'approved')
                ->whereNull('withdrawal_request_id')
                ->orderBy('created_at', 'asc')
                ->get();

            $commissionIds = [];
            $totalAmount = 0;

            foreach ($commissions as $commission) {
                if ($totalAmount + $commission->instructor_payout_amount <= $amount) {
                    $commissionIds[] = $commission->id;
                    $totalAmount += $commission->instructor_payout_amount;
                }

                if ($totalAmount >= $amount) {
                    break;
                }
            }

            // Create withdrawal request
            $withdrawalRequest = WithdrawalRequest::create([
                'environment_id' => $environmentId,
                'requested_by' => $userId,
                'amount' => $totalAmount,
                'currency' => 'XAF',
                'status' => 'pending',
                'withdrawal_method' => $details['method'],
                'withdrawal_details' => $details,
                'commission_ids' => $commissionIds,
            ]);

            // Link commissions to withdrawal request
            InstructorCommission::whereIn('id', $commissionIds)
                ->update(['withdrawal_request_id' => $withdrawalRequest->id]);

            DB::commit();

            Log::info('Withdrawal request created', [
                'withdrawal_request_id' => $withdrawalRequest->id,
                'environment_id' => $environmentId,
                'requested_by' => $userId,
                'amount' => $totalAmount,
                'commission_count' => count($commissionIds),
            ]);

            return $withdrawalRequest;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to create withdrawal request', [
                'environment_id' => $environmentId,
                'user_id' => $userId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Approve withdrawal request
     *
     * @param WithdrawalRequest $request
     * @param int $approvedBy
     * @return bool
     */
    public function approveWithdrawal(WithdrawalRequest $request, int $approvedBy): bool
    {
        try {
            if ($request->status !== 'pending') {
                throw new \Exception("Withdrawal request must be in 'pending' status to approve. Current status: {$request->status}");
            }

            $request->update([
                'status' => 'approved',
                'approved_by' => $approvedBy,
                'approved_at' => now(),
            ]);

            Log::info('Withdrawal request approved', [
                'withdrawal_request_id' => $request->id,
                'approved_by' => $approvedBy,
                'amount' => $request->amount,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to approve withdrawal request', [
                'withdrawal_request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Reject withdrawal request
     *
     * @param WithdrawalRequest $request
     * @param string $reason
     * @return bool
     */
    public function rejectWithdrawal(WithdrawalRequest $request, string $reason): bool
    {
        DB::beginTransaction();

        try {
            if ($request->status !== 'pending') {
                throw new \Exception("Withdrawal request must be in 'pending' status to reject. Current status: {$request->status}");
            }

            // Unlink commissions from this withdrawal request
            if ($request->commission_ids) {
                InstructorCommission::whereIn('id', $request->commission_ids)
                    ->update(['withdrawal_request_id' => null]);
            }

            $request->update([
                'status' => 'rejected',
                'rejection_reason' => $reason,
            ]);

            DB::commit();

            Log::info('Withdrawal request rejected', [
                'withdrawal_request_id' => $request->id,
                'reason' => $reason,
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to reject withdrawal request', [
                'withdrawal_request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Process withdrawal (mark as completed)
     *
     * @param WithdrawalRequest $request
     * @param int $processedBy
     * @param string $reference
     * @return bool
     */
    public function processWithdrawal(WithdrawalRequest $request, int $processedBy, string $reference): bool
    {
        DB::beginTransaction();

        try {
            if ($request->status !== 'approved') {
                throw new \Exception("Withdrawal request must be in 'approved' status to process. Current status: {$request->status}");
            }

            // Mark request as completed
            $request->update([
                'status' => 'completed',
                'processed_by' => $processedBy,
                'processed_at' => now(),
                'payment_reference' => $reference,
            ]);

            // Mark associated commissions as paid
            if ($request->commission_ids) {
                InstructorCommission::whereIn('id', $request->commission_ids)
                    ->update([
                        'status' => 'paid',
                        'paid_at' => now(),
                        'payment_reference' => $reference,
                    ]);
            }

            DB::commit();

            Log::info('Withdrawal request processed', [
                'withdrawal_request_id' => $request->id,
                'processed_by' => $processedBy,
                'payment_reference' => $reference,
                'amount' => $request->amount,
            ]);

            return true;
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to process withdrawal request', [
                'withdrawal_request_id' => $request->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get available balance for environment
     *
     * @param int $environmentId
     * @return float
     */
    public function getAvailableBalance(int $environmentId): float
    {
        return $this->instructorCommissionService->getAvailableBalance($environmentId);
    }

    /**
     * Validate withdrawal amount
     *
     * @param int $environmentId
     * @param float $amount
     * @return bool
     */
    public function validateWithdrawalAmount(int $environmentId, float $amount): bool
    {
        // Check minimum withdrawal amount
        $config = $this->environmentPaymentConfigService->getConfig($environmentId);

        if (!$config) {
            Log::warning('Payment config not found for withdrawal validation', [
                'environment_id' => $environmentId,
            ]);
            return false;
        }

        if ($amount < $config->minimum_withdrawal_amount) {
            Log::info('Withdrawal amount below minimum', [
                'environment_id' => $environmentId,
                'amount' => $amount,
                'minimum' => $config->minimum_withdrawal_amount,
            ]);
            return false;
        }

        // Check available balance
        $availableBalance = $this->getAvailableBalance($environmentId);

        if ($amount > $availableBalance) {
            Log::info('Withdrawal amount exceeds available balance', [
                'environment_id' => $environmentId,
                'amount' => $amount,
                'available_balance' => $availableBalance,
            ]);
            return false;
        }

        return true;
    }

    /**
     * Get withdrawal requests for environment
     *
     * @param int $environmentId
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getWithdrawalRequests(int $environmentId, array $filters = [])
    {
        $query = WithdrawalRequest::where('environment_id', $environmentId)
            ->with(['requestedBy', 'approvedBy', 'processedBy']);

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

        // Default ordering by newest first
        $query->orderBy('created_at', 'desc');

        return $query->get();
    }
}
