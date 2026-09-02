<?php

namespace Tests\Feature\Tenancy;

use App\Models\Environment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * findByDomain() used to evaluate as
 * (primary_domain = d) OR (additional_domains ⊇ d AND is_active), so an
 * inactive environment still matched on its primary domain. The resolver and
 * every public endpoint go through findActiveByDomain(), which fixes the
 * precedence, lowercases the lookup, and is what findByDomain() now delegates to.
 */
class EnvironmentFindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenancy_config_carries_the_shared_hosts_and_subdomain_base(): void
    {
        $this->assertSame(['app.getkursa.space', 'www.app.getkursa.space'], config('tenancy.shared_hosts'));
        $this->assertSame('app.getkursa.space', config('tenancy.shared_host'));
        $this->assertSame('getkursa.space', config('tenancy.subdomain_base'));
        $this->assertSame(['csl-brands.com', 'cfpcsl.com'], config('tenancy.legacy_subdomain_bases'));
        $this->assertSame('log', config('tenancy.environment_guard'));
    }

    public function test_an_inactive_environment_does_not_match_on_its_primary_domain(): void
    {
        Environment::factory()->create(['primary_domain' => 'acme.test', 'is_active' => false]);

        $this->assertNull(Environment::findActiveByDomain('acme.test'));
        $this->assertNull(Environment::findByDomain('acme.test'));
    }

    public function test_an_active_environment_matches_case_insensitively_on_primary_and_additional_domains(): void
    {
        $environment = Environment::factory()->create([
            'primary_domain' => 'acme.test',
            'additional_domains' => ['learn.acme.test'],
        ]);

        $this->assertSame($environment->id, Environment::findActiveByDomain('ACME.test')?->id);
        $this->assertSame($environment->id, Environment::findActiveByDomain('learn.acme.test')?->id);
    }

    public function test_find_active_returns_null_for_an_inactive_environment(): void
    {
        $environment = Environment::factory()->create(['is_active' => false]);

        $this->assertNull(Environment::findActive($environment->id));
        $this->assertNull(Environment::findActive(999_999));
    }

    public function test_a_new_environment_has_no_live_domain_until_verified(): void
    {
        $environment = Environment::factory()->create();

        $this->assertNull($environment->domain_verified_at);
        $this->assertFalse($environment->isDomainLive());

        $environment->forceFill(['domain_verified_at' => now()])->save();

        $this->assertTrue($environment->fresh()->isDomainLive());
    }

    public function test_saving_a_domain_change_forgets_the_lowercased_cache_key(): void
    {
        $environment = Environment::factory()->create(['primary_domain' => 'Acme.test']);
        $this->assertSame($environment->id, Environment::findActiveByDomain('acme.test')?->id);

        $environment->update(['primary_domain' => 'bravo.test']);

        $this->assertNull(Environment::findActiveByDomain('acme.test'));
        $this->assertSame($environment->id, Environment::findActiveByDomain('bravo.test')?->id);
    }
}
