<?php

namespace Tests\Feature\Support\Tenancy;

use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\User;
use App\Support\Tenancy\EnvironmentResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * The parts of the resolver the middleware test cannot reach: what a session
 * alone does NOT buy on the shared host, and the ability parsing the token
 * binding rests on.
 *
 * These go through the real HTTP pipeline rather than calling the resolver
 * directly, because when the session is started relative to DetectEnvironment
 * is exactly the thing that decides the answer.
 */
class EnvironmentResolverTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->get('/api/_echo', fn () => response()->json(['ok' => true]));
    }

    /**
     * The shared host is bearer-only by design (spec D5: it cannot share cookies
     * with the API host), and DetectEnvironment is global middleware that runs
     * before StartSession, so a session can never be the binding there. This
     * pins that: a member with a session and no token resolves to nothing, and
     * the guard is what turns that into a refusal.
     */
    public function test_a_session_alone_is_not_a_binding_on_the_shared_host(): void
    {
        $environment = Environment::factory()->create();
        $user = User::factory()->create();
        EnvironmentUser::create([
            'environment_id' => $environment->id,
            'user_id' => $user->id,
            'role' => 'learner',
        ]);

        $this->actingAs($user)
            ->withSession(['current_environment_id' => $environment->id])
            ->getJson('/api/_echo', ['X-Frontend-Domain' => 'app.getkursa.space'])
            ->assertOk()
            ->assertJsonPath('environment.source', 'none');
    }

    public function test_a_bearer_ability_is_the_binding_on_the_shared_host(): void
    {
        $environment = Environment::factory()->create();
        $user = User::factory()->create();
        $token = $user->createToken('web-client', ['environment_id:'.$environment->id])->plainTextToken;

        $this->getJson('/api/_echo', [
            'X-Frontend-Domain' => 'app.getkursa.space',
            'Authorization' => 'Bearer '.$token,
        ])->assertOk()
            ->assertJsonPath('environment.id', $environment->id)
            ->assertJsonPath('environment.source', 'binding');
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
