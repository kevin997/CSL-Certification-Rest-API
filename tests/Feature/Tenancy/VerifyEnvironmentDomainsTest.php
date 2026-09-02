<?php

namespace Tests\Feature\Tenancy;

use App\Models\Environment;
use App\Support\Tenancy\DomainProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Links move from the shared host to a tenant's own domain only once that
 * domain answers. The command sets that flag and must never clear it, so a
 * momentarily unreachable domain cannot send live links back to the shared host.
 */
class VerifyEnvironmentDomainsTest extends TestCase
{
    use RefreshDatabase;

    private function fakeProbe(array $liveHosts): void
    {
        $this->app->instance(DomainProbe::class, new class($liveHosts) implements DomainProbe
        {
            public array $asked = [];

            public function __construct(private array $live) {}

            public function isLive(string $host): bool
            {
                $this->asked[] = $host;

                return in_array($host, $this->live, true);
            }
        });
    }

    public function test_it_stamps_environments_whose_domain_answers_and_leaves_the_others_null(): void
    {
        $this->fakeProbe(['live.getkursa.space']);
        $live = Environment::factory()->create(['primary_domain' => 'live.getkursa.space']);
        $dead = Environment::factory()->create(['primary_domain' => 'dead.getkursa.space']);

        $this->artisan('environments:verify-domains')->assertSuccessful();

        $this->assertNotNull($live->fresh()->domain_verified_at);
        $this->assertNull($dead->fresh()->domain_verified_at);
    }

    public function test_it_never_probes_or_clears_an_already_verified_environment(): void
    {
        $this->fakeProbe([]);
        $verified = Environment::factory()->create([
            'primary_domain' => 'ok.getkursa.space',
            'domain_verified_at' => now()->subDay(),
        ]);
        Environment::factory()->create([
            'primary_domain' => 'inactive.getkursa.space',
            'is_active' => false,
        ]);

        $this->artisan('environments:verify-domains')->assertSuccessful();

        $this->assertNotNull($verified->fresh()->domain_verified_at);
        $this->assertSame([], $this->app->make(DomainProbe::class)->asked);
    }
}
