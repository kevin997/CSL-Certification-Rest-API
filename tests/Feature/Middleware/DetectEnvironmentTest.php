<?php

namespace Tests\Feature\Middleware;

use App\Models\Environment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * DetectEnvironment is global middleware and had no test. The echo route below
 * returns a JSON body with no `environment` key (unlike /api/health, whose body
 * already carries one), so whatever the middleware stamps on it is exactly what
 * it resolved.
 */
class DetectEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->get('/api/_echo', fn () => response()->json(['ok' => true]));
    }

    public function test_a_tenant_host_resolves_by_the_frontend_domain_header(): void
    {
        $environment = Environment::factory()->create(['primary_domain' => 'acme.test']);

        $response = $this->getJson('/api/_echo', ['X-Frontend-Domain' => 'ACME.test']);

        $response->assertOk()->assertJsonPath('environment.id', $environment->id)
            ->assertJsonPath('environment.source', 'host');
    }

    public function test_an_inactive_environment_is_not_resolved(): void
    {
        Environment::factory()->create(['primary_domain' => 'acme.test', 'is_active' => false]);

        $this->getJson('/api/_echo', ['X-Frontend-Domain' => 'acme.test'])
            ->assertOk()->assertJsonPath('environment.source', 'none');
    }

    public function test_a_host_alias_resolves_by_exact_match_only(): void
    {
        $environment = Environment::factory()->create(['primary_domain' => 'learning.csl-brands.com']);

        $this->getJson('/api/_echo', ['X-Frontend-Domain' => 'csl-certification.vercel.app'])
            ->assertJsonPath('environment.id', $environment->id);

        // The old substring loop matched any header that contained (or was contained in) an alias.
        $this->getJson('/api/_echo', ['X-Frontend-Domain' => 'vercel.app'])
            ->assertJsonPath('environment.source', 'none');
    }

    public function test_the_shared_host_resolves_from_the_bearer_token_binding(): void
    {
        $environment = Environment::factory()->create();
        $user = User::factory()->create();
        $token = $user->createToken('web-client', ['environment_id:'.$environment->id])->plainTextToken;

        $this->getJson('/api/_echo', [
            'X-Frontend-Domain' => 'app.getkursa.space',
            'Authorization' => 'Bearer '.$token,
        ])->assertJsonPath('environment.id', $environment->id)
            ->assertJsonPath('environment.source', 'binding');
    }

    public function test_the_shared_host_ignores_a_binding_to_an_inactive_environment(): void
    {
        $environment = Environment::factory()->create(['is_active' => false]);
        $user = User::factory()->create();
        $token = $user->createToken('web-client', ['environment_id:'.$environment->id])->plainTextToken;

        $this->getJson('/api/_echo', [
            'X-Frontend-Domain' => 'app.getkursa.space',
            'Authorization' => 'Bearer '.$token,
        ])->assertJsonPath('environment.source', 'none');
    }

    public function test_the_shared_host_without_a_principal_resolves_nothing_even_with_a_client_supplied_id(): void
    {
        $environment = Environment::factory()->create();

        $this->getJson('/api/_echo?environment_id='.$environment->id, [
            'X-Frontend-Domain' => 'www.app.getkursa.space',
            'X-Environment-Id' => (string) $environment->id,
        ])->assertJsonPath('environment.source', 'none')
            ->assertJsonMissingPath('environment.id');
    }

    public function test_the_response_carries_domain_verified_at(): void
    {
        // Asserting null would also pass with the key absent, which is how this
        // test passed against the old middleware. Freeze a real timestamp so the
        // assertion fails unless the key is present and serialised as ISO-8601.
        $verifiedAt = Carbon::parse('2026-03-04 05:06:07', 'UTC');
        $this->travelTo($verifiedAt);

        Environment::factory()->create([
            'primary_domain' => 'acme.test',
            'domain_verified_at' => $verifiedAt,
        ]);

        $this->getJson('/api/_echo', ['X-Frontend-Domain' => 'acme.test'])
            ->assertOk()
            ->assertJsonPath('environment.domain_verified_at', $verifiedAt->toIso8601String());
    }

    public function test_an_unverified_domain_is_reported_as_null(): void
    {
        Environment::factory()->create([
            'primary_domain' => 'acme.test',
            'domain_verified_at' => null,
        ]);

        $this->getJson('/api/_echo', ['X-Frontend-Domain' => 'acme.test'])
            ->assertOk()
            ->assertJsonPath('environment.domain_verified_at', null)
            ->assertJsonStructure(['environment' => ['domain_verified_at']]);
    }
}
