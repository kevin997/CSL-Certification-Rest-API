<?php

namespace Tests\Unit\Services;

use App\Models\Environment;
use App\Models\EnvironmentPaymentConfig;
use App\Models\PaymentGatewaySetting;
use App\Services\Payments\PaymentGatewayResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentGatewayResolverTest extends TestCase
{
    use RefreshDatabase;

    private PaymentGatewayResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(PaymentGatewayResolver::class);
    }

    private function gatewayFor(Environment $environment, string $code = 'taramoney', bool $status = true): PaymentGatewaySetting
    {
        return PaymentGatewaySetting::withoutGlobalScopes()->forceCreate([
            'environment_id' => $environment->id,
            'gateway_name' => 'TaraMoney',
            'code' => $code,
            'display_name' => 'TaraMoney',
            'status' => $status,
            'is_default' => false,
            'mode' => 'live',
            'sort_order' => 0,
            'settings' => ['api_key' => 'k'],
        ]);
    }

    /** A tenant that borrows the provider's gateways. */
    private function centralizedPair(): array
    {
        $provider = Environment::factory()->create(['is_active' => true, 'is_centralized_payment_provider' => true]);
        $tenant = Environment::factory()->create(['is_active' => true]);
        EnvironmentPaymentConfig::factory()->create([
            'environment_id' => $tenant->id,
            'use_centralized_gateways' => true,
            'is_active' => true,
        ]);

        return [$provider, $tenant];
    }

    public function test_it_finds_the_providers_gateway_for_a_centralized_tenant(): void
    {
        [$provider, $tenant] = $this->centralizedPair();
        $gateway = $this->gatewayFor($provider);

        $found = $this->resolver->forId($gateway->id, $tenant->id);

        $this->assertNotNull($found);
        $this->assertSame($gateway->id, $found->id);
    }

    /**
     * The regression that matters. EnvironmentScope filters on the session
     * environment, so without an explicit bypass this returns null — which is
     * exactly what broke checkout in production.
     */
    public function test_it_still_resolves_when_the_session_names_the_tenant(): void
    {
        [$provider, $tenant] = $this->centralizedPair();
        $gateway = $this->gatewayFor($provider);

        session(['current_environment_id' => $tenant->id]);

        $this->assertNotNull($this->resolver->forId($gateway->id, $tenant->id));
        $this->assertNotNull($this->resolver->forCode('taramoney', $tenant->id));
        $this->assertCount(1, $this->resolver->listFor($tenant->id));
    }

    public function test_it_resolves_with_no_session_at_all(): void
    {
        [$provider, $tenant] = $this->centralizedPair();
        $gateway = $this->gatewayFor($provider);

        session()->forget('current_environment_id');

        $this->assertNotNull($this->resolver->forId($gateway->id, $tenant->id));
    }

    public function test_a_non_centralized_environment_resolves_its_own_gateway(): void
    {
        $environment = Environment::factory()->create(['is_active' => true]);
        $gateway = $this->gatewayFor($environment);

        session(['current_environment_id' => $environment->id]);

        $this->assertSame($gateway->id, $this->resolver->forId($gateway->id, $environment->id)->id);
    }

    public function test_it_refuses_a_gateway_belonging_to_an_unrelated_environment(): void
    {
        $other = Environment::factory()->create(['is_active' => true]);
        $mine = Environment::factory()->create(['is_active' => true]);
        $theirs = $this->gatewayFor($other);

        $this->assertNull($this->resolver->forId($theirs->id, $mine->id));
    }

    public function test_disabled_gateways_are_excluded_unless_asked_for(): void
    {
        $environment = Environment::factory()->create(['is_active' => true]);
        $this->gatewayFor($environment, 'taramoney', false);

        $this->assertNull($this->resolver->forCode('taramoney', $environment->id));
        $this->assertNotNull($this->resolver->forCode('taramoney', $environment->id, false));
    }

    public function test_effective_environment_id_is_exposed_for_callers_that_need_it(): void
    {
        [$provider, $tenant] = $this->centralizedPair();

        $this->assertSame($provider->id, $this->resolver->effectiveEnvironmentId($tenant->id));
    }
}
