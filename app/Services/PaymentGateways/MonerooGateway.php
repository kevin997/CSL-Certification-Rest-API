<?php

namespace App\Services\PaymentGateways;

use App\Models\Environment;
use App\Models\Invoice;
use App\Models\PaymentGatewaySetting;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Moneroo\Laravel\Payment as MonerooPayment;

class MonerooGateway implements PaymentGatewayInterface
{
    protected PaymentGatewaySetting $settings;
    protected ?string $publicKey = null;
    protected ?string $secretKey = null;
    protected ?string $webhookSecret = null;

    public function initialize(PaymentGatewaySetting $settings): void
    {
        $this->settings = $settings;
        $this->publicKey = $settings->getSetting('public_key');
        $this->secretKey = $settings->getSetting('secret_key');
        $this->webhookSecret = $settings->getSetting('webhook_secret');

        if ($settings->getSetting('test_mode', false)) {
            $this->publicKey = $settings->getSetting('test_public_key', $this->publicKey);
            $this->secretKey = $settings->getSetting('test_secret_key', $this->secretKey);
        }

        if (empty($this->secretKey)) {
            throw new \Exception('Moneroo secret key is missing. Please check your payment gateway settings.');
        }

        config(['moneroo.secretKey' => $this->secretKey]);

        if ($apiUrl = $settings->getSetting('api_url')) {
            config([
                'moneroo.devMode' => true,
                'moneroo.devBaseUrl' => rtrim($apiUrl, '/'),
            ]);
        }
    }

    public function createPayment(Transaction $transaction, array $paymentData = []): array
    {
        try {
            $callbackEnvironmentId = $this->settings->environment_id
                ?: ($transaction->environment_id ?: session('current_environment_id') ?: 'platform');
            $returnUrl = $paymentData['return_url']
                ?? $paymentData['success_url']
                ?? $this->settings->success_url
                ?? route('api.transactions.callback.success', [
                    'environment_id' => $callbackEnvironmentId,
                    'transaction_id' => $transaction->transaction_id,
                ]);

            $nameParts = preg_split('/\s+/', trim((string) $transaction->customer_name), 2) ?: [];
            $firstName = $paymentData['customer']['first_name'] ?? $nameParts[0] ?? 'Customer';
            $lastName = $paymentData['customer']['last_name'] ?? $nameParts[1] ?? 'Customer';

            $requestData = [
                'amount' => (int) round((float) $transaction->total_amount),
                'currency' => strtoupper($transaction->currency ?: 'XAF'),
                'description' => $transaction->description ?: "Payment for transaction {$transaction->transaction_id}",
                'return_url' => $returnUrl,
                'customer' => [
                    'email' => $paymentData['customer']['email'] ?? $transaction->customer_email ?? 'customer@example.com',
                    'first_name' => $firstName ?: 'Customer',
                    'last_name' => $lastName ?: 'Customer',
                    'phone' => $paymentData['customer']['phone'] ?? $paymentData['phone'] ?? null,
                ],
                'metadata' => array_filter([
                    'transaction_id' => (string) $transaction->transaction_id,
                    'internal_id' => (string) $transaction->id,
                    'order_id' => $transaction->order_id ? (string) $transaction->order_id : null,
                    'invoice_id' => $transaction->invoice_id ? (string) $transaction->invoice_id : null,
                    'environment_id' => $transaction->environment_id ? (string) $transaction->environment_id : null,
                ], fn ($value) => $value !== null && $value !== ''),
            ];

            if (!empty($paymentData['methods']) && is_array($paymentData['methods'])) {
                $requestData['methods'] = $paymentData['methods'];
            }

            Log::info('[MonerooGateway] Initializing payment', [
                'transaction_id' => $transaction->transaction_id,
                'amount' => $requestData['amount'],
                'currency' => $requestData['currency'],
                'return_url' => $returnUrl,
            ]);

            $payment = (new MonerooPayment())->init($requestData);
            $paymentArray = $this->objectToArray($payment);
            $gatewayTransactionId = $this->dataGet($paymentArray, 'id')
                ?? $this->dataGet($paymentArray, 'transaction_id')
                ?? $this->dataGet($paymentArray, 'payment_id');
            $checkoutUrl = $this->dataGet($paymentArray, 'checkout_url')
                ?? $this->dataGet($paymentArray, 'checkoutUrl')
                ?? $this->dataGet($paymentArray, 'payment_url');

            if (!$checkoutUrl) {
                Log::error('[MonerooGateway] Checkout URL missing from response', [
                    'transaction_id' => $transaction->transaction_id,
                    'response' => $paymentArray,
                ]);

                return [
                    'success' => false,
                    'message' => 'Moneroo checkout URL missing from response.',
                    'response' => $paymentArray,
                ];
            }

            $transaction->gateway_transaction_id = $gatewayTransactionId ?: $transaction->gateway_transaction_id;
            $transaction->payment_gateway_setting_id = $this->settings->id;
            $transaction->gateway_response = $paymentArray;
            $transaction->save();

            return [
                'success' => true,
                'message' => 'Moneroo payment initialized successfully',
                'transaction_id' => $gatewayTransactionId,
                'checkout_url' => $checkoutUrl,
                'type' => 'payment_url',
                'value' => $checkoutUrl,
                'payment_method' => 'moneroo',
                'payment_type' => 'moneroo',
                'amount' => $transaction->total_amount,
                'currency' => $transaction->currency,
                'response' => $paymentArray,
            ];
        } catch (\Throwable $e) {
            Log::error('[MonerooGateway] Payment initialization failed', [
                'transaction_id' => $transaction->transaction_id,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to create Moneroo payment: ' . $e->getMessage(),
            ];
        }
    }

    public function processPayment(Transaction $transaction, array $paymentData = []): array
    {
        return $this->createPayment($transaction, $paymentData);
    }

    public function verifyPayment(string $transactionId): array
    {
        try {
            $payment = (new MonerooPayment())->verify($transactionId);
            $paymentArray = $this->objectToArray($payment);

            return [
                'success' => true,
                'transaction_id' => $transactionId,
                'status' => $this->dataGet($paymentArray, 'status'),
                'amount' => $this->dataGet($paymentArray, 'amount'),
                'currency' => $this->dataGet($paymentArray, 'currency'),
                'response' => $paymentArray,
            ];
        } catch (\Throwable $e) {
            Log::error('[MonerooGateway] Payment verification failed', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Failed to verify Moneroo payment: ' . $e->getMessage(),
            ];
        }
    }

    public function processRefund(Transaction $transaction, ?float $amount = null, string $reason = ''): array
    {
        return [
            'success' => false,
            'message' => 'Moneroo refunds are not implemented in this gateway yet.',
        ];
    }

    public function getConfig(): array
    {
        return [
            'name' => 'Moneroo',
            'code' => 'moneroo',
            'mode' => $this->settings->mode,
            'public_key' => $this->publicKey,
            'webhook_url' => $this->settings->webhook_url,
        ];
    }

    public function verifyWebhookSignature($payload, string $signature, string $secret): bool
    {
        $secret = $secret ?: $this->webhookSecret;
        if (!$secret || !$signature) {
            return false;
        }

        $signature = preg_replace('/^sha256=/i', '', $signature);
        $expected = hash_hmac('sha256', is_string($payload) ? $payload : json_encode($payload), $secret);

        return hash_equals($expected, $signature);
    }

    public function createInvoicePaymentLink(Invoice $invoice)
    {
        try {
            $transaction = new Transaction();
            $transaction->invoice_id = $invoice->id;
            $transaction->environment_id = $invoice->environment_id;
            $transaction->customer_id = $invoice->customer_id;
            $transaction->amount = $invoice->total_amount;
            $transaction->total_amount = $invoice->total_amount;
            $transaction->currency = $invoice->currency ?? 'XAF';
            $transaction->description = 'Payment for invoice #' . $invoice->invoice_number;
            $transaction->status = Transaction::STATUS_PENDING;
            $transaction->payment_method = 'moneroo';
            $transaction->transaction_id = 'TXN_' . Str::uuid();
            $transaction->save();

            $result = $this->createPayment($transaction, []);

            return $result['checkout_url'] ?? '';
        } catch (\Throwable $e) {
            Log::error('[MonerooGateway] Failed to create invoice payment link', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
            ]);

            return '';
        }
    }

    private function objectToArray($value): array
    {
        return json_decode(json_encode($value), true) ?: [];
    }

    private function dataGet(array $data, string $key)
    {
        foreach (explode('.', $key) as $segment) {
            if (!is_array($data) || !array_key_exists($segment, $data)) {
                return null;
            }

            $data = $data[$segment];
        }

        return $data;
    }
}
