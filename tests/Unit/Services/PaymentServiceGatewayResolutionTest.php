<?php

namespace Tests\Unit\Services;

use App\Models\Environment;
use App\Models\EnvironmentPaymentConfig;
use App\Models\PaymentGatewaySetting;
use App\Services\Payments\PaymentGatewayResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentServiceGatewayResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_centralized_tenant_resolves_the_providers_gateway_by_code_under_its_own_session(): void
    {
        $provider = Environment::factory()->create(['is_active' => true, 'is_centralized_payment_provider' => true]);
        $tenant = Environment::factory()->create(['is_active' => true]);
        EnvironmentPaymentConfig::factory()->create([
            'environment_id' => $tenant->id,
            'use_centralized_gateways' => true,
            'is_active' => true,
        ]);
        PaymentGatewaySetting::withoutGlobalScopes()->forceCreate([
            'environment_id' => $provider->id,
            'gateway_name' => 'TaraMoney',
            'code' => 'taramoney',
            'display_name' => 'TaraMoney',
            'status' => true,
            'is_default' => true,
            'mode' => 'live',
            'sort_order' => 0,
            'settings' => ['api_key' => 'k'],
        ]);

        // The condition under which the old code silently resolved nothing:
        // explicit filter on the effective env, scope filtering on the tenant.
        session(['current_environment_id' => $tenant->id]);

        $resolved = app(PaymentGatewayResolver::class)->forCode('taramoney', $tenant->id);

        $this->assertNotNull($resolved, 'processGatewayPayment must find the gateway it is told to charge');
        $this->assertSame($provider->id, $resolved->environment_id);
    }
}
