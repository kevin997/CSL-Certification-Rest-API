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

        /*
         * The plan asks for a PLATFORM gateway here (environment_id NULL) with
         * the same code and different secrets, to prove the webhook does not
         * fall through to it. That cannot be built under this suite:
         * 2026_04_30_120000_allow_platform_payment_gateway_settings only runs
         * its ALTER for mysql and pgsql, so on the SQLite test driver
         * environment_id remains NOT NULL and platform rows are unrepresentable.
         * The plan forbids schema changes, so the fall-through half is asserted
         * indirectly: resolving to the provider's row means the `?:` platform
         * branch was never reached.
         */

        // Webhooks carry no session; the URL carries the tenant id.
        session()->forget('current_environment_id');

        $resolved = app(PaymentGatewayResolver::class)->forCode('taramoney', $tenant->id);

        $this->assertNotNull($resolved);
        $this->assertSame($tenantGateway->id, $resolved->id, 'must not fall through to the platform gateway');
        $this->assertSame('tenant-secret', $resolved->settings['webhook_secret'] ?? null);
    }
}
