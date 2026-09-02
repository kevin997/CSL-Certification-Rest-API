<?php

namespace Tests\Feature\Tenancy;

use App\Models\Environment;
use App\Models\User;
use App\Support\TenantDomainRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The marketplace builds its dashboard link from primary_domain, which is a
 * dead address for a tenant whose own domain is not live yet. The payloads now
 * carry the effective base URL alongside it, and the shared hosts are known to
 * the tenant domain registry that the admin gate and CORS narrowing consult.
 */
class MarketplaceEnvironmentUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_marketplace_user_payload_carries_the_effective_environment_url(): void
    {
        $owner = User::factory()->create();
        $environment = Environment::factory()->create([
            'owner_id' => $owner->id,
            'primary_domain' => 'acme.getkursa.space',
        ]);
        $token = $owner->createToken('marketplace-auth', ['marketplace'])->plainTextToken;

        $this->getJson('/api/user', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('environment.primary_domain', 'acme.getkursa.space')
            ->assertJsonPath('environment.url', 'https://app.getkursa.space');
    }

    public function test_the_marketplace_payload_uses_the_tenant_domain_once_it_is_verified(): void
    {
        $owner = User::factory()->create();
        $environment = Environment::factory()->create([
            'owner_id' => $owner->id,
            'primary_domain' => 'acme.getkursa.space',
            'domain_verified_at' => now(),
        ]);
        $token = $owner->createToken('marketplace-auth', ['marketplace'])->plainTextToken;

        $this->getJson('/api/user', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('environment.url', 'https://acme.getkursa.space');
    }

    public function test_the_registry_knows_the_shared_hosts(): void
    {
        $hosts = TenantDomainRegistry::getAllowedHosts();

        $this->assertContains('app.getkursa.space', $hosts);
        $this->assertContains('www.app.getkursa.space', $hosts);
    }
}
