<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\PaymentGateways\PaymentGatewayFactory;
use App\Services\Payments\WebhookProcessor;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class VerifyPendingPayments extends Command
{
    protected $signature = 'payments:verify-pending';

    protected $description = 'Server-to-server verify pending payments <24h old for gateways with a trusted status API and route confirmations through WebhookProcessor';

    public function handle(): int
    {
        $transactions = Transaction::withoutGlobalScopes()
            ->where('status', Transaction::STATUS_PENDING)
            ->where('created_at', '>=', now()->subDay())
            ->with('paymentGatewaySetting')
            ->limit(200)
            ->get();

        foreach ($transactions as $transaction) {
            $settings = $transaction->paymentGatewaySetting;

            if (!$settings) {
                continue;
            }

            $code = $settings->code;

            // TRUSTED gateways for S2S verification = ONLY ['paypal']. Even though
            // LygosGateway exposes verifyPayment(), that implementation is a SIMULATION
            // that always returns 'completed' — trusting it would reintroduce the
            // fail-open bug, so Lygos and all others are SKIPPED here (their webhooks
            // settle them).
            if (!in_array($code, ['paypal'], true)) {
                Log::info('[VerifyPendingPayments] Skipping gateway without a trusted status API', [
                    'gateway' => $code,
                    'transaction_id' => $transaction->transaction_id,
                ]);
                continue;
            }

            $gateway = PaymentGatewayFactory::create($code, $settings);

            if (!$gateway) {
                continue;
            }

            $ref = $transaction->gateway_transaction_id ?: $transaction->transaction_id;

            try {
                $result = $gateway->verifyPayment($ref);

                $status = strtolower((string) ($result['status'] ?? ''));

                if (($result['verified'] ?? false) === true || in_array($status, ['completed', 'succeeded', 'success', 'paid'], true)) {
                    app(WebhookProcessor::class)->settle(
                        $transaction,
                        'completed',
                        (string) ($result['status'] ?? 'completed'),
                        is_array($result) ? $result : [],
                        null,
                        [
                            'gateway' => $code,
                            'signature_valid' => true,
                            'gateway_environment_id' => $settings->environment_id,
                        ]
                    );
                } elseif (in_array($status, ['failed', 'failure', 'cancelled', 'canceled', 'expired'], true)) {
                    app(WebhookProcessor::class)->settle(
                        $transaction,
                        'failed',
                        (string) ($result['status'] ?? 'failed'),
                        is_array($result) ? $result : [],
                        'Verified failed via S2S',
                        [
                            'gateway' => $code,
                            'signature_valid' => true,
                            'gateway_environment_id' => $settings->environment_id,
                        ]
                    );
                } else {
                    Log::info('[VerifyPendingPayments] Still pending / unverified', [
                        'gateway' => $code,
                        'transaction_id' => $transaction->transaction_id,
                        'status' => $result['status'] ?? null,
                    ]);
                    continue;
                }
            } catch (Throwable $e) {
                Log::error('[VerifyPendingPayments] Verification failed', [
                    'gateway' => $code,
                    'transaction_id' => $transaction->transaction_id,
                    'message' => $e->getMessage(),
                ]);
                continue;
            }
        }

        return self::SUCCESS;
    }
}
