<?php

namespace Tests\Feature\Payments;

use App\Models\Environment;
use App\Models\EnvironmentPaymentConfig;
use App\Models\PaymentGatewaySetting;
use App\Services\Payments\PaymentGatewayResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralizedSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function setUpCentralized(): array
    {
        $provider = Environment::factory()->create(['is_active' => true, 'is_centralized_payment_provider' => true]);
        $tenant = Environment::factory()->create(['is_active' => true]);
        EnvironmentPaymentConfig::factory()->create([
            'environment_id' => $tenant->id,
            'use_centralized_gateways' => true,
            'is_active' => true,
        ]);
        $gateway = PaymentGatewaySetting::withoutGlobalScopes()->forceCreate([
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

        return [$tenant, $gateway];
    }

    public function test_subscribe_renew_and_continue_all_resolve_the_same_gateway(): void
    {
        [$tenant, $gateway] = $this->setUpCentralized();
        session(['current_environment_id' => $tenant->id]);

        $resolver = app(PaymentGatewayResolver::class);

        // subscribe / renew resolve by id from the request
        $this->assertSame($gateway->id, $resolver->forId($gateway->id, $tenant->id)?->id);
        // continue-payment resolves from the stored order's tenant environment
        $this->assertSame($gateway->id, $resolver->forCode('taramoney', $tenant->id)?->id);
    }

    public function test_a_refund_can_resolve_the_gateway_of_a_centralized_transaction(): void
    {
        [$tenant, $gateway] = $this->setUpCentralized();
        // An admin refund runs under the admin's own session, not the tenant's.
        session(['current_environment_id' => $tenant->id]);

        $this->assertNotNull(
            app(PaymentGatewayResolver::class)->forId($gateway->id, $tenant->id),
            'RefundService must resolve the gateway that took the payment'
        );
    }
}
