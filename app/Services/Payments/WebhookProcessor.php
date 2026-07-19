<?php

namespace App\Services\Payments;

use App\Models\PaymentAttempt;
use App\Models\ProviderEvent;
use App\Models\Transaction;
use App\Services\PaymentService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * WebhookProcessor — the single, authoritative settlement engine for verified
 * gateway events. Implements the KURSA licensing plan §11 ten-step payment
 * completion algorithm:
 *
 *   1. Receive a signed / server-to-server verified gateway event (CALLER's job:
 *      the caller MUST have already verified the webhook signature or performed a
 *      trusted S2S status check before calling settle()).
 *   2. Persist the provider event uniquely (gateway, provider_event_id). A second
 *      delivery of an already-PROCESSED event is a no-op ('duplicate').
 *   3. Resolve the immutable payment attempt (if any) for the transaction.
 *   4. Validate expected amount, currency, merchant and environment. A mismatch
 *      marks the event failed and does NOT settle.
 *   5. Lock the transaction row FOR UPDATE inside a DB transaction.
 *   6. Ignore an already-applied event; settlement is idempotent AND monotonic —
 *      a completed/failed/refunded transaction never regresses from a stale event.
 *   7. Mark the payment attempt paid.
 *   8. Activate/settle the transaction and fulfil the order/licence atomically,
 *      reusing PaymentService's existing settlement internals (no duplication).
 *   9. Non-financial side effects (emails, provisioning) run after commit.
 *  10. Record audit / reconciliation data (the ProviderEvent row is the ledger).
 *
 * Browser callbacks NEVER call this class — only signed webhooks and trusted
 * server-to-server verification (VerifyPendingPayments) may settle.
 */
class WebhookProcessor
{
    public function __construct(private PaymentService $paymentService)
    {
    }

    /**
     * Settle (or fail) a transaction from a verified gateway event.
     *
     * @param  Transaction  $transaction  Already-resolved transaction (step 3 input).
     * @param  string  $outcome  'completed' or 'failed'.
     * @param  string  $gatewayStatus  Raw gateway status string for auditing.
     * @param  array  $payload  Raw (verified) gateway event payload.
     * @param  string|null  $notes  Optional note for failures.
     * @param  array  $options  gateway, provider_event_id, signature_valid,
     *                          event_type, gateway_environment_id.
     * @return string One of: processed, duplicate, failed, skipped, irrelevant.
     */
    public function settle(
        Transaction $transaction,
        string $outcome,
        string $gatewayStatus,
        array $payload,
        ?string $notes = null,
        array $options = []
    ): string {
        $gateway = $options['gateway']
            ?? optional($transaction->paymentGatewaySetting)->code
            ?? 'unknown';

        $providerEventId = $options['provider_event_id']
            ?? $this->extractProviderEventId($payload)
            ?? ($transaction->gateway_transaction_id ?: $transaction->transaction_id);

        $signatureValid = $options['signature_valid'] ?? true;

        // ---- Step 2: persist the provider event uniquely --------------------
        $event = $this->persistProviderEvent(
            $gateway,
            (string) $providerEventId,
            $options['event_type'] ?? ($payload['type'] ?? $payload['event'] ?? $gatewayStatus),
            $signatureValid,
            $payload,
            $transaction->environment_id
        );

        if ($event === null) {
            // Already PROCESSED previously — idempotent no-op.
            Log::info('[WebhookProcessor] Duplicate provider event ignored', [
                'gateway' => $gateway,
                'provider_event_id' => $providerEventId,
                'transaction_id' => $transaction->transaction_id,
            ]);

            return 'duplicate';
        }

        // ---- Step 4: validate amount / currency / merchant / environment ----
        $mismatch = $this->detectMismatch($transaction, $payload, $options);
        if ($mismatch !== null) {
            $event->status = ProviderEvent::STATUS_FAILED;
            $event->save();

            Log::warning('[WebhookProcessor] Rejected event on validation mismatch; NOT settling', [
                'gateway' => $gateway,
                'provider_event_id' => $providerEventId,
                'transaction_id' => $transaction->transaction_id,
                'reason' => $mismatch,
            ]);

            return 'failed';
        }

        try {
            // ---- Steps 5-8: lock, idempotent/monotonic check, settle -------
            $result = DB::transaction(function () use ($transaction, $outcome, $gatewayStatus, $payload, $notes, $event) {
                /** @var Transaction $locked */
                $locked = Transaction::withoutGlobalScopes()
                    ->whereKey($transaction->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $current = $locked->status;
                $terminal = [
                    Transaction::STATUS_COMPLETED,
                    Transaction::STATUS_REFUNDED,
                    Transaction::STATUS_PARTIALLY_REFUNDED,
                    Transaction::STATUS_FAILED,
                ];

                if ($outcome === 'completed') {
                    // Step 6: idempotent — already completed, do not re-fulfil.
                    if ($current === Transaction::STATUS_COMPLETED) {
                        return 'skipped';
                    }

                    // Monotonic — a failed/refunded transaction never regresses
                    // to completed from a stale event.
                    if (in_array($current, [
                        Transaction::STATUS_FAILED,
                        Transaction::STATUS_REFUNDED,
                        Transaction::STATUS_PARTIALLY_REFUNDED,
                    ], true)) {
                        Log::warning('[WebhookProcessor] Refusing to regress terminal transaction to completed', [
                            'transaction_id' => $locked->transaction_id,
                            'current_status' => $current,
                        ]);

                        return 'skipped';
                    }

                    // Step 7: mark the (latest) payment attempt paid.
                    $this->markLatestAttemptPaid($locked, $event->id);

                    // Step 8: settle + fulfil (reuses PaymentService internals).
                    $this->paymentService->applySettlement($locked, $gatewayStatus, $payload);

                    return 'processed';
                }

                // outcome === 'failed'
                if (in_array($current, $terminal, true)) {
                    // Never regress a completed/refunded transaction; failing an
                    // already-failed one is a no-op.
                    return 'skipped';
                }

                $this->markLatestAttemptFailed($locked);
                $this->paymentService->applyFailure(
                    $locked,
                    $gatewayStatus,
                    $payload,
                    $notes ?? 'Payment failed'
                );

                return 'processed';
            });

            // ---- Step 10: finalise the ledger row --------------------------
            $event->status = $result === 'processed'
                ? ProviderEvent::STATUS_PROCESSED
                : ProviderEvent::STATUS_SKIPPED;
            $event->processed_at = now();
            $event->save();

            // ---- Step 9: non-financial side effects after commit -----------
            DB::afterCommit(function () use ($event, $transaction, $result) {
                Log::info('[WebhookProcessor] Settlement finalised', [
                    'gateway' => $event->gateway,
                    'provider_event_id' => $event->provider_event_id,
                    'transaction_id' => $transaction->transaction_id,
                    'result' => $result,
                ]);
            });

            return $result;
        } catch (Throwable $e) {
            // Transient / unexpected failure: leave the ledger row NON-terminal
            // so the provider's retry (or VerifyPendingPayments) reprocesses it,
            // and re-throw so the webhook wrapper returns a retryable 5xx.
            $event->status = ProviderEvent::STATUS_FAILED;
            $event->save();

            Log::error('[WebhookProcessor] Settlement error; event left for retry', [
                'gateway' => $gateway,
                'provider_event_id' => $providerEventId,
                'transaction_id' => $transaction->transaction_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Step 2 helper. Returns the ProviderEvent to process, or null when the
     * event was already PROCESSED (true duplicate). A previously received/failed
     * row is reused so that a transient error does not permanently lose an event.
     */
    private function persistProviderEvent(
        string $gateway,
        string $providerEventId,
        ?string $eventType,
        bool $signatureValid,
        array $payload,
        ?int $environmentId
    ): ?ProviderEvent {
        $existing = ProviderEvent::where('gateway', $gateway)
            ->where('provider_event_id', $providerEventId)
            ->first();

        if ($existing && $existing->status === ProviderEvent::STATUS_PROCESSED) {
            return null;
        }

        $event = $existing ?: new ProviderEvent([
            'gateway' => $gateway,
            'provider_event_id' => $providerEventId,
        ]);

        $event->gateway = $gateway;
        $event->provider_event_id = $providerEventId;
        $event->environment_id = $environmentId;
        $event->event_type = $eventType;
        $event->signature_valid = $signatureValid;
        $event->payload = $payload;
        $event->status = ProviderEvent::STATUS_RECEIVED;
        $event->attempts = ($event->attempts ?? 0) + 1;

        try {
            $event->save();
        } catch (QueryException $e) {
            // Concurrent insert of the same (gateway, provider_event_id).
            $winner = ProviderEvent::where('gateway', $gateway)
                ->where('provider_event_id', $providerEventId)
                ->first();

            if ($winner && $winner->status === ProviderEvent::STATUS_PROCESSED) {
                return null;
            }

            if (! $winner) {
                throw $e;
            }

            return $winner;
        }

        return $event;
    }

    /**
     * Step 4 helper. Returns a human reason string on mismatch, or null when the
     * event is consistent with the transaction. Amount/currency are only checked
     * when the transaction carries the expected snapshot (backward compatible).
     */
    private function detectMismatch(Transaction $transaction, array $payload, array $options): ?string
    {
        $expectedAmount = $transaction->expected_amount !== null
            ? (float) $transaction->expected_amount
            : null;
        $providerAmount = $this->extractAmount($payload);

        if ($expectedAmount !== null && $providerAmount !== null
            && abs($providerAmount - $expectedAmount) > 0.01) {
            return sprintf('amount mismatch (expected %.2f, got %.2f)', $expectedAmount, $providerAmount);
        }

        $expectedCurrency = $transaction->expected_currency
            ? strtoupper((string) $transaction->expected_currency)
            : null;
        $providerCurrency = $this->extractCurrency($payload);

        if ($expectedCurrency !== null && $providerCurrency !== null
            && $expectedCurrency !== $providerCurrency) {
            return sprintf('currency mismatch (expected %s, got %s)', $expectedCurrency, $providerCurrency);
        }

        $gatewayEnvId = $options['gateway_environment_id'] ?? null;
        if ($transaction->merchant_environment_id !== null && $gatewayEnvId !== null
            && (int) $transaction->merchant_environment_id !== (int) $gatewayEnvId) {
            return 'merchant/environment mismatch';
        }

        return null;
    }

    private function markLatestAttemptPaid(Transaction $transaction, int $providerEventId): void
    {
        $attempt = PaymentAttempt::where('transaction_id', $transaction->id)
            ->orderByDesc('id')
            ->first();

        if ($attempt && $attempt->status !== PaymentAttempt::STATUS_PAID) {
            $attempt->markPaid($providerEventId);
        }
    }

    private function markLatestAttemptFailed(Transaction $transaction): void
    {
        $attempt = PaymentAttempt::where('transaction_id', $transaction->id)
            ->orderByDesc('id')
            ->first();

        if ($attempt && ! in_array($attempt->status, [
            PaymentAttempt::STATUS_PAID,
            PaymentAttempt::STATUS_FAILED,
        ], true)) {
            $attempt->markFailed();
        }
    }

    private function extractProviderEventId(array $payload): ?string
    {
        $id = $payload['id']
            ?? $payload['event_id']
            ?? $payload['paymentId']
            ?? $payload['payment_ref']
            ?? $payload['reference']
            ?? $payload['transaction_id']
            ?? ($payload['data']['id'] ?? null)
            ?? ($payload['metadata']['transaction_id'] ?? null);

        return $id !== null ? (string) $id : null;
    }

    private function extractAmount(array $payload): ?float
    {
        $amount = $payload['amount']
            ?? $payload['total']
            ?? $payload['total_amount']
            ?? ($payload['data']['amount'] ?? null);

        return $amount !== null && is_numeric($amount) ? (float) $amount : null;
    }

    private function extractCurrency(array $payload): ?string
    {
        $currency = $payload['currency']
            ?? ($payload['data']['currency'] ?? null);

        return $currency !== null ? strtoupper((string) $currency) : null;
    }
}
