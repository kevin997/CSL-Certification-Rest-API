<?php

namespace App\Services;

use App\Models\Environment;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PlatformPaymentService
{
    public function __construct(private PlatformPaymentGatewayResolver $gatewayResolver)
    {
    }

    public function initiate(array $data): array
    {
        $gatewayCode = $data['gateway'] ?? $data['payment_method'] ?? 'taramoney';
        $gatewayResult = $this->gatewayResolver->resolve($gatewayCode);

        if (!$gatewayResult['success']) {
            return $gatewayResult;
        }

        $environment = Environment::find($data['environment_id']);
        if (!$environment) {
            return [
                'success' => false,
                'message' => 'Environment not found for platform payment',
            ];
        }

        $metadata = $data['metadata'] ?? [];
        $transaction = Transaction::create([
            'transaction_id' => $data['transaction_id'] ?? 'PXN_' . (string) Str::uuid(),
            'environment_id' => $environment->id,
            'payment_gateway_setting_id' => $gatewayResult['settings']->id,
            'invoice_id' => $data['invoice_id'] ?? null,
            'customer_id' => $data['customer_id'] ?? null,
            'customer_email' => $data['customer_email'] ?? null,
            'customer_name' => $data['customer_name'] ?? null,
            'amount' => $data['amount'],
            'fee_amount' => $data['fee_amount'] ?? 0,
            'tax_amount' => $data['tax_amount'] ?? 0,
            'tax_rate' => $data['tax_rate'] ?? 0,
            'tax_zone' => $data['tax_zone'] ?? null,
            'total_amount' => $data['total_amount'] ?? $data['amount'],
            'currency' => $data['currency'] ?? 'USD',
            'status' => Transaction::STATUS_PENDING,
            'payment_method' => $gatewayCode,
            'payment_method_details' => json_encode([
                'scope' => 'platform',
                'source_type' => $data['source_type'] ?? null,
                'source_id' => $data['source_id'] ?? null,
                'metadata' => $metadata,
            ]),
            'description' => $data['description'] ?? 'Platform payment',
            'country_code' => $environment->country_code,
            'state_code' => $environment->state_code,
            'created_by' => $data['created_by'] ?? null,
        ]);

        $paymentData = [
            'payment_method' => $gatewayCode,
            'customer_email' => $transaction->customer_email,
            'customer_name' => $transaction->customer_name,
            'success_url' => $data['success_url'] ?? null,
            'cancel_url' => $data['cancel_url'] ?? null,
            'metadata' => $metadata,
        ];

        $response = $gatewayResult['gateway']->createPayment($transaction, $paymentData);

        if (!($response['success'] ?? false)) {
            $transaction->update([
                'status' => Transaction::STATUS_FAILED,
                'gateway_response' => $response,
                'notes' => $response['message'] ?? 'Platform payment initiation failed',
            ]);

            return $response;
        }

        $transaction->refresh();

        Log::info('Platform payment initiated', [
            'transaction_id' => $transaction->transaction_id,
            'source_type' => $data['source_type'] ?? null,
            'source_id' => $data['source_id'] ?? null,
            'gateway' => $gatewayCode,
        ]);

        return [
            'success' => true,
            'message' => 'Platform payment initiated successfully',
            'transaction' => $transaction,
            'payment_data' => [
                'transaction_id' => $transaction->transaction_id,
                'gateway_transaction_id' => $transaction->gateway_transaction_id,
                'amount' => $transaction->total_amount,
                'currency' => $transaction->currency,
                'payment_method' => $gatewayCode,
                'payment_type' => $response['payment_type'] ?? $gatewayCode,
                'redirect_url' => $response['redirect_url'] ?? null,
                'general_link' => $response['general_link'] ?? null,
                'payment_links' => $response['payment_links'] ?? [],
                'whatsapp_link' => $response['whatsapp_link'] ?? null,
                'telegram_link' => $response['telegram_link'] ?? null,
                'dikalo_link' => $response['dikalo_link'] ?? null,
                'sms_link' => $response['sms_link'] ?? null,
                'card_link' => $response['card_link'] ?? null,
                'checkout_url' => $response['checkout_url'] ?? null,
            ],
            'gateway_response' => $response,
        ];
    }
}
