<?php

namespace Tests\Feature\Support\Tenancy;

use App\Models\Environment;
use App\Models\User;
use App\Support\Tenancy\EnvironmentResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The parts of the resolver the middleware test cannot reach: the cookie-mode
 * session binding, and the ability parsing that the token binding rests on.
 *
 * These go through the real HTTP pipeline rather than calling the resolver
 * directly, because whether the session is started when DetectEnvironment runs
 * is exactly the thing under test.
 */
class EnvironmentResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->get('/api/_echo', fn () => response()->json(['ok' => true]));
    }

    public function test_a_session_binding_on_the_shared_host_resolves_for_a_member(): void
    {
        $environment = Environment::factory()->create();
        $user = User::factory()->create();
        $user->environments()->attach($environment->id, ['joined_at' => now()]);

        $this->actingAs($user)
            ->withSession(['current_environment_id' => $environment->id])
            ->getJson('/api/_echo', ['X-Frontend-Domain' => 'app.getkursa.space'])
            ->assertOk()
            ->assertJsonPath('environment.id', $environment->id)
            ->assertJsonPath('environment.source', 'binding');
    }

    public function test_a_session_binding_on_the_shared_host_is_ignored_for_a_non_member(): void
    {
        // DetectEnvironment writes current_environment_id from whatever host the
        // request arrived on, so the key alone proves no membership check ever ran.
        $environment = Environment::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->withSession(['current_environment_id' => $environment->id])
            ->getJson('/api/_echo', ['X-Frontend-Domain' => 'app.getkursa.space'])
            ->assertOk()
            ->assertJsonPath('environment.source', 'none')
            ->assertJsonMissingPath('environment.id');
    }

    public function test_a_session_binding_on_the_shared_host_resolves_for_the_owner(): void
    {
        $owner = User::factory()->create();
        $environment = Environment::factory()->create(['owner_id' => $owner->id]);

        $this->actingAs($owner)
            ->withSession(['current_environment_id' => $environment->id])
            ->getJson('/api/_echo', ['X-Frontend-Domain' => 'app.getkursa.space'])
            ->assertOk()
            ->assertJsonPath('environment.id', $environment->id)
            ->assertJsonPath('environment.source', 'binding');
    }

    public function test_a_session_binding_to_an_inactive_environment_is_ignored(): void
    {
        $environment = Environment::factory()->create(['is_active' => false]);
        $user = User::factory()->create();
        $user->environments()->attach($environment->id, ['joined_at' => now()]);

        $this->actingAs($user)
            ->withSession(['current_environment_id' => $environment->id])
            ->getJson('/api/_echo', ['X-Frontend-Domain' => 'app.getkursa.space'])
            ->assertOk()
            ->assertJsonPath('environment.source', 'none');
    }

    public function test_a_malformed_binding_ability_does_not_mask_a_well_formed_one(): void
    {
        $this->assertSame(
            7,
            EnvironmentResolver::environmentIdFromAbilities(['environment_id:abc', 'environment_id:7'])
        );
    }

    public function test_a_token_with_no_binding_ability_yields_no_environment_id(): void
    {
        $this->assertNull(EnvironmentResolver::environmentIdFromAbilities(['*']));
    }
}
