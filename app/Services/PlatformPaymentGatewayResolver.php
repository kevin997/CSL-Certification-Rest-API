<?php

namespace App\Services;

use App\Models\PaymentGatewaySetting;
use App\Services\PaymentGateways\PaymentGatewayFactory;
use App\Services\PaymentGateways\PaymentGatewayInterface;
use Illuminate\Support\Facades\Log;

class PlatformPaymentGatewayResolver
{
    public function __construct(private PaymentGatewayFactory $gatewayFactory)
    {
    }

    public function resolve(string $gatewayCode): array
    {
        $settings = PaymentGatewaySetting::withoutGlobalScopes()
            ->whereNull('environment_id')
            ->where('code', $gatewayCode)
            ->where('status', true)
            ->orderByDesc('is_default')
            ->first();

        if (!$settings) {
            return [
                'success' => false,
                'message' => "Platform payment gateway '{$gatewayCode}' is not configured",
            ];
        }

        $gateway = $this->gatewayFactory->create($gatewayCode, $settings);

        if (!$gateway instanceof PaymentGatewayInterface) {
            Log::warning('Platform payment gateway creation failed', [
                'gateway_code' => $gatewayCode,
                'settings_id' => $settings->id,
            ]);

            return [
                'success' => false,
                'message' => "Platform payment gateway '{$gatewayCode}' is not supported",
            ];
        }

        return [
            'success' => true,
            'gateway' => $gateway,
            'settings' => $settings,
        ];
    }
}
