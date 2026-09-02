<?php

namespace Tests\Feature\Auth;

use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Switching used to redirect to https://{primary_domain}/auth/switch, a dead
 * address while the tenant's domain is not live. The redirect now follows
 * TenantUrl, so a pending domain lands on the shared host and the exchange
 * there rebinds the session in place.
 */
class AcademySwitchRedirectTest extends TestCase
{
    use RefreshDatabase;

    private function bearer(User $user, ?int $environmentId = null): array
    {
        $abilities = $environmentId ? ['environment_id:'.$environmentId] : [];

        return ['Authorization' => 'Bearer '.$user->createToken('t', $abilities)->plainTextToken];
    }

    public function test_a_pending_domain_redirects_to_the_shared_host(): void
    {
        $user = User::factory()->create();
        $target = Environment::factory()->create(['primary_domain' => 'bravo.getkursa.space']);
        EnvironmentUser::create(['environment_id' => $target->id, 'user_id' => $user->id, 'role' => 'learner']);

        $response = $this->postJson('/api/auth/academy-switch-token', ['target_environment_id' => $target->id], $this->bearer($user))
            ->assertOk();

        $this->assertStringStartsWith('https://app.getkursa.space/auth/switch?token=', $response->json('redirect_url'));
        $this->assertStringContainsString('environment_id='.$target->id, $response->json('redirect_url'));
    }

    public function test_a_live_domain_redirects_to_the_tenant_domain(): void
    {
        $user = User::factory()->create();
        $target = Environment::factory()->create(['primary_domain' => 'bravo.getkursa.space', 'domain_verified_at' => now()]);
        EnvironmentUser::create(['environment_id' => $target->id, 'user_id' => $user->id, 'role' => 'learner']);

        $response = $this->postJson('/api/auth/academy-switch-token', ['target_environment_id' => $target->id], $this->bearer($user));

        $this->assertStringStartsWith('https://bravo.getkursa.space/auth/switch?token=', $response->json('redirect_url'));
        $this->assertStringNotContainsString('environment_id=', $response->json('redirect_url'));
    }

    public function test_the_exchange_reports_whether_the_account_is_set_up(): void
    {
        $user = User::factory()->create();
        $target = Environment::factory()->create();
        EnvironmentUser::create(['environment_id' => $target->id, 'user_id' => $user->id, 'role' => 'owner', 'is_account_setup' => false]);

        $token = $this->postJson('/api/auth/academy-switch-token', ['target_environment_id' => $target->id], $this->bearer($user))
            ->json('token');

        $exchange = $this->postJson('/api/auth/validate-switch-token', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('environment_id', $target->id)
            ->assertJsonPath('is_account_setup', false);

        $this->getJson('/api/user', ['Authorization' => 'Bearer '.$exchange->json('token')])
            ->assertOk()
            ->assertJsonPath('environment_id', $target->id)
            ->assertJsonPath('is_account_setup', false);
    }

    public function test_a_switch_token_is_single_use(): void
    {
        $user = User::factory()->create();
        $target = Environment::factory()->create(['owner_id' => $user->id]);

        $token = $this->postJson('/api/auth/academy-switch-token', ['target_environment_id' => $target->id], $this->bearer($user))
            ->json('token');

        $this->postJson('/api/auth/validate-switch-token', ['token' => $token])->assertOk();
        $this->postJson('/api/auth/validate-switch-token', ['token' => $token])->assertStatus(401);
    }
}
