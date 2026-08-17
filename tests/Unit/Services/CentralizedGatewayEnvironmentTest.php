<?php

namespace Tests\Unit\Services;

use App\Models\Environment;
use App\Models\EnvironmentPaymentConfig;
use App\Services\EnvironmentPaymentConfigService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * The centralized gateway environment used to be the literal id 1. It is now
 * whichever environment owns the configured primary domain, so these tests pin
 * the resolution rules — including what happens when it cannot be resolved,
 * which decides where a tenant's money is routed.
 */
class CentralizedGatewayEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    private EnvironmentPaymentConfigService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(EnvironmentPaymentConfigService::class);

        config(['payments.centralized.environment_id' => null]);
        config(['payments.centralized.environment_domain' => 'bootcamps.csl-brands.com']);

        Cache::flush();
    }

    private function centralizedEnvironment(): Environment
    {
        return Environment::factory()->create([
            'primary_domain' => 'bootcamps.csl-brands.com',
            'is_active' => true,
        ]);
    }

    private function tenantUsingCentralizedGateways(): Environment
    {
        $tenant = Environment::factory()->create();

        EnvironmentPaymentConfig::factory()->create([
            'environment_id' => $tenant->id,
            'use_centralized_gateways' => true,
            'is_active' => true,
        ]);

        return $tenant;
    }

    public function test_it_resolves_the_centralized_environment_by_configured_domain(): void
    {
        $centralized = $this->centralizedEnvironment();

        $this->assertSame($centralized->id, $this->service->getCentralizedEnvironmentId());
    }

    public function test_it_does_not_resolve_to_environment_one_by_default(): void
    {
        // Environment 1 exists but is not the configured centralized environment.
        Environment::factory()->create(['primary_domain' => 'learning.csl-brands.com']);
        $centralized = $this->centralizedEnvironment();

        $this->assertNotSame(1, $this->service->getCentralizedEnvironmentId());
        $this->assertSame($centralized->id, $this->service->getCentralizedEnvironmentId());
    }

    public function test_an_explicit_id_override_wins_over_the_domain(): void
    {
        $this->centralizedEnvironment();
        config(['payments.centralized.environment_id' => 4242]);

        $this->assertSame(4242, $this->service->getCentralizedEnvironmentId());
    }

    public function test_it_returns_null_when_the_configured_domain_matches_no_environment(): void
    {
        $this->assertNull($this->service->getCentralizedEnvironmentId());
    }

    public function test_it_returns_null_when_the_domain_environment_is_inactive(): void
    {
        Environment::factory()->create([
            'primary_domain' => 'bootcamps.csl-brands.com',
            'is_active' => false,
        ]);

        $this->assertNull($this->service->getCentralizedEnvironmentId());
    }

    public function test_a_centralized_tenant_routes_to_the_centralized_environment(): void
    {
        $centralized = $this->centralizedEnvironment();
        $tenant = $this->tenantUsingCentralizedGateways();

        $this->assertSame($centralized->id, $this->service->getEffectiveEnvironmentId($tenant->id));
    }

    public function test_a_non_centralized_tenant_keeps_its_own_environment(): void
    {
        $this->centralizedEnvironment();

        $tenant = Environment::factory()->create();
        EnvironmentPaymentConfig::factory()->create([
            'environment_id' => $tenant->id,
            'use_centralized_gateways' => false,
            'is_active' => true,
        ]);

        $this->assertSame($tenant->id, $this->service->getEffectiveEnvironmentId($tenant->id));
    }

    /**
     * Falling back to the tenant's own environment surfaces as "no gateway
     * configured". Falling back to a fixed id would silently route the payment
     * into another tenant's gateway account.
     */
    public function test_an_unresolvable_centralized_environment_falls_back_to_the_tenant(): void
    {
        $tenant = $this->tenantUsingCentralizedGateways();

        $this->assertSame($tenant->id, $this->service->getEffectiveEnvironmentId($tenant->id));
    }

    public function test_the_centralized_environment_is_recognised_as_itself(): void
    {
        $centralized = $this->centralizedEnvironment();
        $other = Environment::factory()->create();

        $this->assertTrue($this->service->isCentralizedEnvironment($centralized->id));
        $this->assertFalse($this->service->isCentralizedEnvironment($other->id));
    }

    public function test_the_centralized_environment_does_not_route_away_from_itself(): void
    {
        $centralized = $this->centralizedEnvironment();

        EnvironmentPaymentConfig::factory()->create([
            'environment_id' => $centralized->id,
            'use_centralized_gateways' => true,
            'is_active' => true,
        ]);

        $this->assertSame(
            $centralized->id,
            $this->service->getEffectiveEnvironmentId($centralized->id)
        );
    }

    public function test_the_provider_flag_wins_over_the_configured_domain(): void
    {
        $byDomain = $this->centralizedEnvironment();
        $flagged = Environment::factory()->create(['is_centralized_payment_provider' => true]);

        $this->assertNotSame($byDomain->id, $flagged->id);
        $this->assertSame($flagged->id, $this->service->getCentralizedEnvironmentId());
    }

    public function test_an_inactive_flagged_environment_falls_back_to_the_configured_domain(): void
    {
        $byDomain = $this->centralizedEnvironment();
        Environment::factory()->create([
            'is_centralized_payment_provider' => true,
            'is_active' => false,
        ]);

        $this->assertSame($byDomain->id, $this->service->getCentralizedEnvironmentId());
    }

    public function test_setting_a_provider_clears_the_previous_one(): void
    {
        $first = Environment::factory()->create(['is_centralized_payment_provider' => true]);
        $second = Environment::factory()->create();

        $this->service->setCentralizedEnvironment($second->id);

        $this->assertFalse($first->fresh()->is_centralized_payment_provider);
        $this->assertTrue($second->fresh()->is_centralized_payment_provider);
        $this->assertSame($second->id, $this->service->getCentralizedEnvironmentId());
    }

    public function test_a_new_provider_stops_borrowing_gateways_from_anyone(): void
    {
        $environment = Environment::factory()->create();
        EnvironmentPaymentConfig::factory()->create([
            'environment_id' => $environment->id,
            'use_centralized_gateways' => true,
            'is_active' => true,
        ]);

        $this->service->setCentralizedEnvironment($environment->id);

        $this->assertFalse($this->service->isCentralized($environment->id));
    }

    public function test_an_inactive_environment_cannot_become_the_provider(): void
    {
        $inactive = Environment::factory()->create(['is_active' => false]);

        $this->expectException(\Exception::class);

        $this->service->setCentralizedEnvironment($inactive->id);
    }

    public function test_switching_provider_reroutes_existing_borrowers(): void
    {
        $this->centralizedEnvironment();
        $tenant = $this->tenantUsingCentralizedGateways();
        $newProvider = Environment::factory()->create();

        $this->service->setCentralizedEnvironment($newProvider->id);

        $this->assertSame($newProvider->id, $this->service->getEffectiveEnvironmentId($tenant->id));
    }
}
