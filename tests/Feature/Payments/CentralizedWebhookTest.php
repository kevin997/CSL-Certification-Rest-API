<?php

namespace Tests\Feature\Payments;

use App\Models\Environment;
use App\Models\EnvironmentPaymentConfig;
use App\Models\PaymentGatewaySetting;
use App\Services\Payments\PaymentGatewayResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralizedWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_webhook_for_a_tenant_resolves_the_providers_gateway_not_the_platform_one(): void
    {
        $provider = Environment::factory()->create(['is_active' => true, 'is_centralized_payment_provider' => true]);
        $tenant = Environment::factory()->create(['is_active' => true]);
        EnvironmentPaymentConfig::factory()->create([
            'environment_id' => $tenant->id,
            'use_centralized_gateways' => true,
            'is_active' => true,
        ]);

        $tenantGateway = PaymentGatewaySetting::withoutGlobalScopes()->forceCreate([
            'environment_id' => $provider->id,
            'gateway_name' => 'TaraMoney',
            'code' => 'taramoney',
            'display_name' => 'TaraMoney',
            'status' => true,
            'is_default' => true,
            'mode' => 'live',
            'sort_order' => 0,
            'settings' => ['webhook_secret' => 'tenant-secret'],
        ]);

        // A PLATFORM gateway with the SAME code and DIFFERENT secrets -- the
        // wrong answer the old fall-through produced. Representable since
        // 2026_08_18_000002 completed the nullable ALTER for SQLite.
        //
        // withoutEvents: the model's validateUniqueConstraints reports a clash
        // between a platform row and an environment row, though the database
        // constraint is composite (environment_id, code) and permits both.
        PaymentGatewaySetting::withoutEvents(fn () => PaymentGatewaySetting::withoutGlobalScopes()->forceCreate([
            'environment_id' => null,
            'gateway_name' => 'TaraMoney',
            'code' => 'taramoney',
            'display_name' => 'TaraMoney',
            'status' => true,
            'is_default' => true,
            'mode' => 'live',
            'sort_order' => 0,
            'settings' => ['webhook_secret' => 'platform-secret'],
        ]));

        // Webhooks carry no session; the URL carries the tenant id.
        session()->forget('current_environment_id');

        $resolved = app(PaymentGatewayResolver::class)->forCode('taramoney', $tenant->id);

        $this->assertNotNull($resolved);
        $this->assertSame($tenantGateway->id, $resolved->id, 'must not fall through to the platform gateway');
        $this->assertSame(
            'tenant-secret',
            $resolved->settings['webhook_secret'] ?? null,
            'verifying against the platform secret would reject a genuine webhook'
        );
    }
}
