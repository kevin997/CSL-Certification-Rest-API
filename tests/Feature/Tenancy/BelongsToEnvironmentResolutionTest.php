<?php

namespace Tests\Feature\Tenancy;

use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\User;
use App\Traits\BelongsToEnvironment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * detectEnvironmentId() used to fall back to the first active environment,
 * stamping new rows into a stranger's tenant whenever nothing resolved.
 */
class BelongsToEnvironmentResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_null_instead_of_the_first_active_environment(): void
    {
        Environment::factory()->create();
        session()->forget('current_environment_id');

        $this->assertNull(BelongsToEnvironment::detectEnvironmentId());
    }

    public function test_it_prefers_the_resolved_context_over_request_input(): void
    {
        $host = Environment::factory()->create(['primary_domain' => 'acme.test']);
        $other = Environment::factory()->create();

        Route::middleware('api')->get('/api/_echo', fn () => response()->json(['ok' => true]));
        $this->getJson('/api/_echo?environment_id='.$other->id, ['X-Frontend-Domain' => 'acme.test']);

        $this->assertSame($host->id, BelongsToEnvironment::detectEnvironmentId());
    }

    /**
     * Several endpoints name their environment in the request rather than
     * relying on the host. Unchecked, that let any authenticated caller stamp a
     * row into a tenant they do not belong to.
     */
    public function test_a_request_supplied_environment_id_is_accepted_for_a_member(): void
    {
        $environment = Environment::factory()->create();
        $user = User::factory()->create();
        EnvironmentUser::create([
            'environment_id' => $environment->id,
            'user_id' => $user->id,
            'role' => 'learner',
        ]);

        $this->actingAs($user)
            ->postJson('/api/integration-interests', [
                'integration_id' => 'zoom',
                'environment_id' => $environment->id,
            ])
            ->assertCreated();

        $this->assertDatabaseHas('integration_interests', [
            'user_id' => $user->id,
            'environment_id' => $environment->id,
        ]);
    }

    public function test_a_request_supplied_environment_id_is_refused_for_a_non_member(): void
    {
        $victim = Environment::factory()->create();
        $stranger = User::factory()->create();

        // Pin the response, not just the absent row: refusing by letting a NOT
        // NULL constraint fail would satisfy assertDatabaseMissing while
        // answering 500 and leaking the schema under debug.
        $this->actingAs($stranger)
            ->postJson('/api/integration-interests', [
                'integration_id' => 'zoom',
                'environment_id' => $victim->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('integration_interests', [
            'user_id' => $stranger->id,
            'environment_id' => $victim->id,
        ]);
    }

    /**
     * Platform staff administer tenants they hold no membership row in, and
     * admin screens filter by environment, so the membership rule must not
     * turn those into refusals.
     */
    public function test_platform_staff_may_name_an_environment_they_do_not_belong_to(): void
    {
        $environment = Environment::factory()->create();
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($admin)
            ->postJson('/api/integration-interests', [
                'integration_id' => 'zoom',
                'environment_id' => $environment->id,
            ])
            ->assertCreated();
    }
}
