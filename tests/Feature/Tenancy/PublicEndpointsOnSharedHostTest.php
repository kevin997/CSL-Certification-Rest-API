<?php

namespace Tests\Feature\Tenancy;

use App\Models\Branding;
use App\Models\Environment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * On the shared host the hostname identifies nobody, so public endpoints take
 * an explicit identifier. On a tenant host the host still wins.
 */
class PublicEndpointsOnSharedHostTest extends TestCase
{
    use RefreshDatabase;

    private function branded(array $attributes = []): Environment
    {
        $environment = Environment::factory()->create($attributes);
        Branding::factory()->create([
            'environment_id' => $environment->id,
            'company_name' => 'Acme Academy',
            'primary_color' => '#123456',
            'is_active' => true,
        ]);

        return $environment;
    }

    public function test_public_branding_resolves_by_environment_id_on_the_shared_host(): void
    {
        $environment = $this->branded();

        $this->getJson('/api/branding/public?environment_id='.$environment->id, ['X-Frontend-Domain' => 'app.getkursa.space'])
            ->assertOk()
            ->assertJsonPath('data.company_name', 'Acme Academy')
            ->assertJsonPath('environment.id', $environment->id);
    }

    public function test_public_branding_ignores_the_identifier_on_a_tenant_host(): void
    {
        $host = $this->branded(['primary_domain' => 'acme.test']);
        $other = $this->branded();

        $this->getJson('/api/branding/public?environment_id='.$other->id, ['X-Frontend-Domain' => 'acme.test'])
            ->assertOk()
            ->assertJsonPath('environment.id', $host->id);
    }

    public function test_public_branding_refuses_an_inactive_identifier(): void
    {
        $environment = $this->branded(['is_active' => false]);

        $this->getJson('/api/branding/public?environment_id='.$environment->id, ['X-Frontend-Domain' => 'app.getkursa.space'])
            ->assertStatus(404);
    }

    public function test_environment_status_and_popups_accept_the_identifier_on_the_shared_host(): void
    {
        $environment = $this->branded();

        $this->getJson('/api/environment/status?environment_id='.$environment->id, ['X-Frontend-Domain' => 'app.getkursa.space'])
            ->assertOk()
            ->assertJsonPath('data.id', $environment->id);

        $this->getJson('/api/branding/public/popups?domain='.$environment->id, ['X-Frontend-Domain' => 'app.getkursa.space'])
            ->assertOk();
    }
}
