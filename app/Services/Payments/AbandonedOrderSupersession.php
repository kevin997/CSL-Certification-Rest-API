<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\SalesFormSubmission;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Cancels abandoned payment attempts once a retry for the same cart is paid.
 *
 * A learner who abandons a mobile-money prompt and checks out again creates a
 * second sales-form submission and order. The first order stays pending with a
 * pending transaction; if the provider later settles that stale prompt the
 * learner is charged twice and a second Meta Purchase is reported.
 *
 * When a cart is paid, older pending orders for the same learner, academy and
 * cart are cancelled and their pending transactions marked failed. The webhook
 * processor refuses to move a failed transaction to completed, so a late
 * provider event cannot revive a superseded attempt.
 */
class AbandonedOrderSupersession
{
    /**
     * @return array<int, string> Order numbers of the cancelled attempts.
     */
    public function supersedeFor(Order $completed): array
    {
        if ($completed->type !== Order::TYPE_SALES_FORM) {
            return [];
        }

        $signature = $this->cartSignature((int) $completed->id);

        if ($signature === '') {
            return [];
        }

        $candidates = Order::withoutGlobalScopes()
            ->where('user_id', $completed->user_id)
            ->where('environment_id', $completed->environment_id)
            ->where('type', Order::TYPE_SALES_FORM)
            ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_PROCESSING])
            ->whereNull('deleted_at')
            ->where('id', '!=', $completed->id)
            ->where('id', '<', $completed->id)
            ->get();

        $cancelled = [];

        foreach ($candidates as $candidate) {
            if ($this->cartSignature((int) $candidate->id) !== $signature) {
                continue;
            }

            if ($this->supersede($candidate, $completed)) {
                $cancelled[] = (string) $candidate->order_number;
            }
        }

        return $cancelled;
    }

    /**
     * Identical products and quantities identify the same cart. Attempts can
     * live under different sales-form submissions, so submission id alone
     * cannot link them.
     */
    private function cartSignature(int $orderId): string
    {
        $items = DB::table('order_items')
            ->where('order_id', $orderId)
            ->get(['product_id', 'quantity'])
            ->map(fn ($item) => $item->product_id.':'.$item->quantity)
            ->sort()
            ->values()
            ->all();

        return implode('|', $items);
    }

    private function supersede(Order $candidate, Order $completed): bool
    {
        return DB::transaction(function () use ($candidate, $completed): bool {
            $order = Order::withoutGlobalScopes()
                ->whereKey($candidate->id)
                ->lockForUpdate()
                ->first();

            if (! $order || ! in_array($order->status, [Order::STATUS_PENDING, Order::STATUS_PROCESSING], true)) {
                return false;
            }

            $note = 'Superseded by completed payment '.$completed->order_number.'.';

            $order->status = Order::STATUS_CANCELLED;
            $order->notes = trim(($order->notes ? $order->notes."\n" : '').$note);
            $order->save();

            $transactions = Transaction::withoutGlobalScopes()
                ->where('order_id', $order->id)
                ->whereIn('status', [Transaction::STATUS_PENDING, Transaction::STATUS_PROCESSING])
                ->get();

            foreach ($transactions as $transaction) {
                $transaction->status = Transaction::STATUS_FAILED;
                $transaction->notes = trim(($transaction->notes ? $transaction->notes."\n" : '').$note);
                $transaction->save();
            }

            if ($transactions->isNotEmpty()) {
                Payment::withoutGlobalScopes()
                    ->whereIn('transaction_id', $transactions->pluck('transaction_id')->all())
                    ->whereIn('status', [Payment::STATUS_PENDING, Payment::STATUS_PROCESSING])
                    ->update(['status' => Payment::STATUS_FAILED]);
            }

            $this->cancelDuplicateSubmission($order, $completed);

            Log::info('Superseded abandoned order after a completed retry', [
                'superseded_order_id' => $order->id,
                'superseded_order_number' => $order->order_number,
                'completed_order_id' => $completed->id,
                'completed_order_number' => $completed->order_number,
                'failed_transactions' => $transactions->count(),
            ]);

            return true;
        });
    }

    /**
     * The abandoned attempt usually has its own sales-form submission (the
     * form was submitted again). Close it so it stops being "pending" and the
     * reminder jobs leave the learner alone.
     */
    private function cancelDuplicateSubmission(Order $superseded, Order $completed): void
    {
        $submissionId = $superseded->sales_form_submission_id;

        if (! $submissionId || (int) $submissionId === (int) $completed->sales_form_submission_id) {
            return;
        }

        $submission = SalesFormSubmission::withoutGlobalScopes()->find($submissionId);

        if (! $submission || $submission->status !== SalesFormSubmission::STATUS_PENDING) {
            return;
        }

        $stillOpen = Order::withoutGlobalScopes()
            ->where('sales_form_submission_id', $submissionId)
            ->whereIn('status', [Order::STATUS_PENDING, Order::STATUS_PROCESSING])
            ->exists();

        if ($stillOpen) {
            return;
        }

        $submission->status = SalesFormSubmission::STATUS_CANCELLED;
        $submission->save();
    }
}
