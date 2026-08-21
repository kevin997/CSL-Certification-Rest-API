<?php

namespace Tests\Feature\Api;

use App\Models\Environment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Environments resolve by numeric id, primary domain or additional domain.
 *
 * resolveByIdentifier used to also match a `subdomain` column. That column has
 * never existed, so every non-numeric identifier raised SQLSTATE[42S22] and
 * /api/storefront/{domain}/... returned 500 -- only numeric ids ever worked.
 * Nothing exercised domain resolution, so the query was never executed by a
 * test and the dead clause survived.
 */
class EnvironmentResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_by_numeric_id(): void
    {
        $environment = Environment::factory()->create();

        $this->assertSame(
            $environment->id,
            Environment::resolveByIdentifier((string) $environment->id)?->id
        );
    }

    public function test_it_resolves_by_primary_domain_case_insensitively(): void
    {
        $environment = Environment::factory()->create(['primary_domain' => 'acme.test']);

        $this->assertSame($environment->id, Environment::resolveByIdentifier('acme.test')?->id);
        $this->assertSame($environment->id, Environment::resolveByIdentifier('ACME.test')?->id);
    }

    public function test_it_resolves_by_an_additional_domain(): void
    {
        $environment = Environment::factory()->create([
            'primary_domain' => 'acme.test',
            'additional_domains' => ['shop.acme.test'],
        ]);

        $this->assertSame($environment->id, Environment::resolveByIdentifier('shop.acme.test')?->id);
    }

    public function test_an_unknown_domain_resolves_to_null(): void
    {
        Environment::factory()->create(['primary_domain' => 'acme.test']);

        $this->assertNull(Environment::resolveByIdentifier('nope.test'));
    }
}
