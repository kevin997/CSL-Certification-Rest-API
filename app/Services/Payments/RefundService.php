<?php

namespace App\Services\Payments;

use App\Models\Enrollment;
use App\Models\InstructorCommission;
use App\Models\Order;
use App\Models\PaymentGatewaySetting;
use App\Models\Transaction;
use App\Services\PaymentGateways\PaymentGatewayFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * RefundService — the single orchestrator for the KURSA refund flow (doc §9.9).
 *
 * Money moves BEFORE local success. The flow is:
 *   1. Validate the request (cumulative refunds may not exceed the original).
 *   2. Gateway-supported → initiate the gateway refund, create an immutable child
 *      refund transaction (purpose=refund, parent_transaction_id=parent uuid,
 *      negative amounts) in status `processing`; if the gateway confirms
 *      synchronously (Stripe `succeeded`, PayPal `COMPLETED`) confirm it now,
 *      otherwise the confirmation arrives later via the webhook (WebhookProcessor).
 *   3. Gateway-unsupported → caller returns 409 + manual instructions; the manual
 *      path (recordManualRefund) records an out-of-band refund directly.
 *
 * On confirmation the ledger effects are applied atomically: parent cumulative
 * refunded state, order status derivation, commission reversal/flagging, and the
 * access consequence (full course refund → unenroll; licence → manual note).
 */
class RefundService
{
    /**
     * Initiate a gateway refund against a completed (or partially-refunded)
     * parent transaction.
     *
     * @return array{status:string, ...} status is one of: ok, unsupported,
     *         invalid, gateway_error.
     */
    public function initiateRefund(Transaction $parent, ?float $amount, string $reason = ''): array
    {
        $validation = $this->validate($parent, $amount);
        if ($validation['status'] !== 'ok') {
            return $validation;
        }
        $amount = $validation['amount'];

        // Resolve the gateway that settled the parent.
        $settings = $parent->payment_gateway_setting_id
            ? PaymentGatewaySetting::find($parent->payment_gateway_setting_id)
            : null;

        if (! $settings) {
            return ['status' => 'invalid', 'message' => 'Payment gateway settings not found for this transaction'];
        }

        $gateway = PaymentGatewayFactory::create($parent->payment_method, $settings);

        if (! $gateway) {
            return ['status' => 'invalid', 'message' => 'Failed to initialize payment gateway'];
        }

        if (! $gateway->supportsRefunds()) {
            return [
                'status' => 'unsupported',
                'unsupported' => true,
                'gateway' => $parent->payment_method,
                'manual_instructions' => $this->manualInstructions($parent),
                'message' => ucfirst((string) $parent->payment_method)
                    . ' does not support automatic refunds. Record the refund out-of-band via the manual refund endpoint.',
            ];
        }

        $response = $gateway->processRefund($parent, $amount, $reason);

        if (($response['unsupported'] ?? false) === true) {
            return [
                'status' => 'unsupported',
                'unsupported' => true,
                'gateway' => $parent->payment_method,
                'manual_instructions' => $this->manualInstructions($parent),
                'message' => $response['message'] ?? 'Gateway does not support automatic refunds',
            ];
        }

        if (($response['success'] ?? false) !== true) {
            return [
                'status' => 'gateway_error',
                'message' => $response['message'] ?? 'Gateway refund failed',
                'error' => $response['error'] ?? null,
            ];
        }

        // Immutable child refund transaction — always created as `processing`;
        // it flips to `completed` only on confirmation.
        $child = $this->createChildRefund($parent, $amount, $reason, [
            'payment_method' => $parent->payment_method,
            'gateway_transaction_id' => $response['refund_id'] ?? null,
            'gateway_response' => $response['response'] ?? $response,
        ]);

        $effects = null;
        if (($response['confirmed'] ?? false) === true) {
            $effects = $this->confirmRefund($child);
        }

        return [
            'status' => 'ok',
            'confirmed' => (bool) ($response['confirmed'] ?? false),
            'transaction' => $child->fresh(),
            'parent' => $parent->fresh(),
            'effects' => $effects,
        ];
    }

    /**
     * Record an out-of-band (manual) refund for a gateway that cannot refund
     * programmatically. Confirmed immediately (the money already moved offline).
     *
     * @return array{status:string, ...}
     */
    public function recordManualRefund(Transaction $parent, ?float $amount, string $reason, string $notes): array
    {
        $validation = $this->validate($parent, $amount);
        if ($validation['status'] !== 'ok') {
            return $validation;
        }
        $amount = $validation['amount'];

        $child = $this->createChildRefund($parent, $amount, $reason, [
            'payment_method' => 'manual',
            'gateway_transaction_id' => null,
            'gateway_response' => null,
            'notes' => $notes,
        ]);

        $effects = $this->confirmRefund($child);

        return [
            'status' => 'ok',
            'confirmed' => true,
            'transaction' => $child->fresh(),
            'parent' => $parent->fresh(),
            'effects' => $effects,
        ];
    }

    /**
     * Confirm a refund child (from synchronous gateway response, a webhook, or a
     * manual record) and apply all ledger effects atomically. Idempotent: a
     * second call after the child is already completed is a no-op.
     *
     * @return array Effects report (commission reversal, order/access changes).
     */
    public function confirmRefund(Transaction $child): array
    {
        return DB::transaction(function () use ($child) {
            /** @var Transaction $locked */
            $locked = Transaction::withoutGlobalScopes()->whereKey($child->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === Transaction::STATUS_COMPLETED && $locked->verified_at) {
                return ['already_applied' => true];
            }

            $locked->status = Transaction::STATUS_COMPLETED;
            $locked->verified_at = $locked->verified_at ?: now();
            $locked->refunded_at = $locked->refunded_at ?: now();
            $locked->save();

            $parent = Transaction::withoutGlobalScopes()
                ->where('transaction_id', $locked->parent_transaction_id)
                ->lockForUpdate()
                ->first();

            if (! $parent) {
                Log::warning('[RefundService] Confirmed refund with no resolvable parent', [
                    'refund_transaction_id' => $locked->transaction_id,
                    'parent_transaction_id' => $locked->parent_transaction_id,
                ]);

                return ['parent_missing' => true];
            }

            // Cumulative refunded = confirmed (completed) children only.
            $cumulative = $parent->refundedAmount([Transaction::STATUS_COMPLETED]);
            $isFull = ($cumulative + 0.01) >= (float) $parent->total_amount;

            $parent->status = $isFull
                ? Transaction::STATUS_REFUNDED
                : Transaction::STATUS_PARTIALLY_REFUNDED;
            $parent->refunded_at = now();
            if ($locked->refund_reason) {
                $parent->refund_reason = $locked->refund_reason;
            }
            $parent->save();

            $commissionReport = $this->reverseCommissions($parent);
            $orderReport = $this->deriveOrder($parent, $isFull);
            $accessReport = $this->applyAccessConsequence($parent, $isFull);

            return [
                'cumulative_refunded' => round($cumulative, 2),
                'is_full_refund' => $isFull,
                'parent_status' => $parent->status,
                'commissions' => $commissionReport,
                'order' => $orderReport,
                'access' => $accessReport,
            ];
        });
    }

    /**
     * Reverse instructor commissions tied to the parent transaction. Outstanding
     * (unsettled) liabilities become `reversed`; already-paid/withdrawn ones are
     * NOT clawed back — instead a negative-adjustment note is recorded and the
     * commission is flagged in the report for finance follow-up (doc §9.9).
     */
    protected function reverseCommissions(Transaction $parent): array
    {
        $commissions = InstructorCommission::where('transaction_id', $parent->id)->get();
        $report = ['reversed' => [], 'flagged_paid' => []];

        foreach ($commissions as $commission) {
            if (in_array($commission->status, InstructorCommission::UNSETTLED_STATUSES, true)) {
                $commission->status = InstructorCommission::STATUS_REVERSED;
                $commission->notes = trim(($commission->notes ? $commission->notes . "\n" : '')
                    . 'Reversed on refund of transaction ' . $parent->transaction_id . ' at ' . now()->toDateTimeString());
                $commission->save();
                $report['reversed'][] = $commission->id;
            } elseif ($commission->status === InstructorCommission::STATUS_PAID) {
                // Already paid out / withdrawn — do NOT touch the row. Record a
                // negative-adjustment note and flag for manual settlement.
                $commission->notes = trim(($commission->notes ? $commission->notes . "\n" : '')
                    . 'NEGATIVE ADJUSTMENT REQUIRED: refund of transaction ' . $parent->transaction_id
                    . ' (already-paid commission of ' . $commission->instructor_payout_amount . ' ' . $commission->currency
                    . ') — reconcile out-of-band at ' . now()->toDateTimeString());
                $commission->save();
                $report['flagged_paid'][] = [
                    'commission_id' => $commission->id,
                    'payout_amount' => (float) $commission->instructor_payout_amount,
                    'currency' => $commission->currency,
                ];
                Log::warning('[RefundService] Refund affects an already-paid commission; manual reconciliation required', [
                    'commission_id' => $commission->id,
                    'transaction_id' => $parent->transaction_id,
                ]);
            }
        }

        return $report;
    }

    /**
     * Derive the order status from the cumulative refund: full → refunded;
     * partial → order stays completed but payment_status = partially_refunded.
     */
    protected function deriveOrder(Transaction $parent, bool $isFull): array
    {
        if (! $parent->order_id) {
            return ['order_id' => null];
        }

        $order = Order::find($parent->order_id);
        if (! $order) {
            return ['order_id' => $parent->order_id, 'found' => false];
        }

        // The orders table tracks a single `status` column (no separate
        // payment_status). Full refund → refunded; partial → partially_refunded.
        $order->status = $isFull
            ? Order::STATUS_REFUNDED
            : Order::STATUS_PARTIALLY_REFUNDED;
        $order->notes = trim(($order->notes ? $order->notes . "\n" : '')
            . ($isFull ? 'Fully refunded' : 'Partially refunded') . ' at ' . now()->toDateTimeString());
        $order->save();

        return [
            'order_id' => $order->id,
            'status' => $order->status,
        ];
    }

    /**
     * Apply the access consequence. Full course refund → unenroll the learner
     * (data preserved, enrollment set to `dropped`). Licence transactions get NO
     * automatic change — a manual admin decision is required (doc §9.9).
     */
    protected function applyAccessConsequence(Transaction $parent, bool $isFull): array
    {
        if (in_array($parent->purpose, Transaction::LICENCE_PURPOSES, true)) {
            return [
                'type' => 'licence',
                'note' => 'licence adjustment is a manual admin decision',
            ];
        }

        if (! $isFull || ! in_array($parent->purpose, Transaction::REFUNDABLE_COURSE_PURPOSES, true)) {
            return ['type' => 'course', 'unenrolled' => false];
        }

        $order = $parent->order_id ? Order::find($parent->order_id) : null;
        if (! $order || ! $order->user_id) {
            return ['type' => 'course', 'unenrolled' => false, 'reason' => 'no order/user'];
        }

        $productIds = DB::table('order_items')->where('order_id', $order->id)->pluck('product_id');
        $courseIds = DB::table('product_courses')->whereIn('product_id', $productIds)->pluck('course_id');

        $dropped = 0;
        if ($courseIds->isNotEmpty()) {
            $dropped = Enrollment::where('user_id', $order->user_id)
                ->whereIn('course_id', $courseIds)
                ->where('environment_id', $order->environment_id)
                ->update(['status' => Enrollment::STATUS_DROPPED]);
        }

        return ['type' => 'course', 'unenrolled' => true, 'enrollments_dropped' => $dropped];
    }

    /**
     * Shared validation: parent must be a settled, refundable transaction and the
     * requested amount must not push cumulative refunds beyond the original.
     *
     * @return array{status:string, amount?:float, message?:string}
     */
    protected function validate(Transaction $parent, ?float $amount): array
    {
        if (! in_array($parent->status, [
            Transaction::STATUS_COMPLETED,
            Transaction::STATUS_PARTIALLY_REFUNDED,
        ], true)) {
            return ['status' => 'invalid', 'message' => 'Only a completed transaction can be refunded'];
        }

        if ($parent->purpose === Transaction::PURPOSE_REFUND) {
            return ['status' => 'invalid', 'message' => 'A refund transaction cannot itself be refunded'];
        }

        $total = (float) $parent->total_amount;
        $already = $parent->refundedAmount(); // completed + processing
        $remaining = round($total - $already, 2);

        if ($remaining <= 0) {
            return ['status' => 'invalid', 'message' => 'Transaction is already fully refunded'];
        }

        if ($amount === null) {
            $amount = $remaining;
        }

        $amount = round((float) $amount, 2);

        if ($amount <= 0) {
            return ['status' => 'invalid', 'message' => 'Refund amount must be greater than zero'];
        }

        if ($amount > $remaining + 0.001) {
            return [
                'status' => 'invalid',
                'message' => sprintf(
                    'Refund amount (%.2f) exceeds the refundable remainder (%.2f) of the original %.2f',
                    $amount,
                    $remaining,
                    $total
                ),
            ];
        }

        return ['status' => 'ok', 'amount' => $amount];
    }

    /**
     * Create the immutable child refund transaction (status `processing`).
     */
    protected function createChildRefund(Transaction $parent, float $amount, string $reason, array $opts): Transaction
    {
        $child = new Transaction();
        $child->environment_id = $parent->environment_id;
        $child->order_id = $parent->order_id;
        $child->invoice_id = $parent->invoice_id;
        $child->customer_id = $parent->customer_id;
        $child->customer_email = $parent->customer_email;
        $child->customer_name = $parent->customer_name;
        $child->payment_gateway_setting_id = $parent->payment_gateway_setting_id;
        $child->payment_method = $opts['payment_method'] ?? $parent->payment_method;
        $child->purpose = Transaction::PURPOSE_REFUND;
        $child->parent_transaction_id = $parent->transaction_id;
        $child->amount = -1 * $amount;
        $child->total_amount = -1 * $amount;
        $child->platform_fee_amount = 0;
        $child->currency = $parent->currency;
        $child->status = Transaction::STATUS_PROCESSING;
        $child->gateway_transaction_id = $opts['gateway_transaction_id'] ?? null;
        $child->gateway_response = $opts['gateway_response'] ?? null;
        $child->refund_reason = $reason;
        $child->description = 'Refund for transaction ' . $parent->transaction_id . ($reason !== '' ? ': ' . $reason : '');
        if (! empty($opts['notes'])) {
            $child->notes = $opts['notes'];
        }
        $child->merchant_environment_id = $parent->merchant_environment_id;
        $child->gateway_account_environment_id = $parent->gateway_account_environment_id;
        $child->save();

        return $child;
    }

    protected function manualInstructions(Transaction $parent): string
    {
        return 'Refund the ' . $parent->total_amount . ' ' . $parent->currency . ' payment directly in the '
            . ucfirst((string) $parent->payment_method)
            . ' dashboard, then POST /transactions/' . $parent->id
            . '/refund/manual with the amount, reason and confirmation notes to record it in the ledger.';
    }
}
