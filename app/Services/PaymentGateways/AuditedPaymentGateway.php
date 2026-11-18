<?php

namespace App\Services\PaymentGateways;

use App\Models\AuditLog;
use App\Models\Invoice;
use App\Models\PaymentGatewaySetting;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Throwable;

class AuditedPaymentGateway implements PaymentGatewayInterface
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
        private readonly PaymentGatewaySetting $settings
    ) {
    }

    public function initialize(PaymentGatewaySetting $settings): void
    {
        $this->gateway->initialize($settings);
    }

    public function createPayment(Transaction $transaction, array $paymentData = []): array
    {
        return $this->audit('create_payment', $transaction, $paymentData, fn () => $this->gateway->createPayment($transaction, $paymentData));
    }

    public function processPayment(Transaction $transaction, array $paymentData = []): array
    {
        return $this->audit('process_payment', $transaction, $paymentData, fn () => $this->gateway->processPayment($transaction, $paymentData));
    }

    public function verifyPayment(string $transactionId): array
    {
        return $this->audit(
            'verify_payment',
            null,
            ['transaction_id' => $transactionId],
            fn () => $this->gateway->verifyPayment($transactionId)
        );
    }

    public function processRefund(Transaction $transaction, ?float $amount = null, string $reason = ''): array
    {
        return $this->audit(
            'process_refund',
            $transaction,
            ['amount' => $amount, 'reason' => $reason],
            fn () => $this->gateway->processRefund($transaction, $amount, $reason)
        );
    }

    public function supportsRefunds(): bool
    {
        return $this->gateway->supportsRefunds();
    }

    public function getConfig(): array
    {
        return $this->gateway->getConfig();
    }

    public function verifyWebhookSignature($payload, string $signature, string $secret): bool
    {
        return $this->audit(
            'verify_webhook_signature',
            null,
            ['payload' => $payload, 'signature' => $signature, 'secret' => $secret],
            fn () => $this->gateway->verifyWebhookSignature($payload, $signature, $secret)
        );
    }

    public function createInvoicePaymentLink(Invoice $invoice)
    {
        return $this->audit(
            'create_invoice_payment_link',
            null,
            ['invoice_id' => $invoice->id, 'invoice_number' => $invoice->invoice_number ?? null],
            fn () => $this->gateway->createInvoicePaymentLink($invoice),
            Invoice::class,
            (string) $invoice->getKey(),
            $invoice->environment_id ?? $this->settings->environment_id,
            $invoice->user_id ?? null
        );
    }

    private function audit(
        string $action,
        ?Transaction $transaction,
        array $requestData,
        callable $operation,
        ?string $entityType = null,
        ?string $entityId = null,
        ?int $environmentId = null,
        ?int $userId = null
    ): mixed {
        $source = 'payment_gateway:' . $this->settings->code;
        $startedAt = microtime(true);
        $entityType ??= $transaction ? Transaction::class : null;
        $entityId ??= $transaction ? (string) $transaction->getKey() : null;
        $environmentId ??= $transaction?->environment_id ?? $this->settings->environment_id;
        $userId ??= $transaction?->customer_id;

        try {
            $response = $operation();

            // Routine status checks do not change the payment. Keep audit rows
            // for provider-confirmed final results and transaction transitions.
            $routineVerification = $action === 'verify_payment'
                && (!is_array($response)
                    || ($response['verified'] ?? false) !== true
                    || !in_array(strtolower((string) ($response['status'] ?? '')), [
                        'completed', 'succeeded', 'success', 'paid',
                        'failed', 'failure', 'cancelled', 'canceled', 'expired',
                    ], true));

            if ($routineVerification) {
                return $response;
            }

            $this->recordAudit(
                $source,
                $action,
                $this->requestPayload($transaction, $requestData),
                is_array($response) ? $response : ['result' => $response],
                [
                    'gateway_setting_id' => $this->settings->id,
                    'gateway_name' => $this->settings->gateway_name,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                ],
                $this->statusFromResponse($response),
                $entityType,
                $entityId,
                $environmentId,
                $userId,
                $this->notesFromResponse($response)
            );

            return $response;
        } catch (Throwable $e) {
            $this->recordAudit(
                $source,
                $action,
                $this->requestPayload($transaction, $requestData),
                null,
                [
                    'gateway_setting_id' => $this->settings->id,
                    'gateway_name' => $this->settings->gateway_name,
                    'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
                    'exception' => get_class($e),
                ],
                AuditLog::STATUS_ERROR,
                $entityType,
                $entityId,
                $environmentId,
                $userId,
                $e->getMessage()
            );

            throw $e;
        }
    }

    /**
     * Audit logging is observability, never part of the payment transaction.
     * A failed insert (long user agent, schema drift, database hiccup) must not
     * turn a successful gateway payment into an error response.
     */
    private function recordAudit(
        string $source,
        string $action,
        ?array $requestData,
        ?array $responseData,
        ?array $metadata,
        ?string $status,
        ?string $entityType,
        ?string $entityId,
        ?int $environmentId,
        ?int $userId,
        ?string $notes
    ): void {
        try {
            AuditLog::logPaymentGatewayOperation(
                $source,
                $action,
                $requestData,
                $responseData,
                $metadata,
                $status,
                $entityType,
                $entityId,
                $environmentId,
                $userId,
                $notes
            );
        } catch (Throwable $auditException) {
            Log::warning('Payment gateway audit logging failed', [
                'source' => $source,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'error' => $auditException->getMessage(),
            ]);
        }
    }

    private function requestPayload(?Transaction $transaction, array $requestData): array
    {
        return [
            'transaction' => $transaction ? [
                'id' => $transaction->id,
                'transaction_id' => $transaction->transaction_id,
                'gateway_transaction_id' => $transaction->gateway_transaction_id,
                'order_id' => $transaction->order_id,
                'amount' => $transaction->amount,
                'total_amount' => $transaction->total_amount,
                'currency' => $transaction->currency,
                'status' => $transaction->status,
                'payment_method' => $transaction->payment_method,
            ] : null,
            'payment_data' => $requestData,
        ];
    }

    private function statusFromResponse(mixed $response): string
    {
        if (!is_array($response)) {
            return AuditLog::STATUS_SUCCESS;
        }

        if (($response['success'] ?? false) === true) {
            return AuditLog::STATUS_SUCCESS;
        }

        return AuditLog::STATUS_FAILURE;
    }

    private function notesFromResponse(mixed $response): ?string
    {
        if (!is_array($response)) {
            return null;
        }

        return $response['message'] ?? $response['error'] ?? $response['error_details'] ?? null;
    }
}
