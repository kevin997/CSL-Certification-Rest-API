<?php

namespace Tests\Feature\Auth;

use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * On a tenant host the host decides the environment. On the shared host there
 * is no host to decide, so login binds by membership: one membership binds,
 * several ask the client to choose, none is refused.
 */
class LoginBindingTest extends TestCase
{
    use RefreshDatabase;

    private function login(array $body, string $host): TestResponse
    {
        return $this->postJson('/api/tokens', $body + ['device_name' => 'web-client'], ['X-Frontend-Domain' => $host]);
    }

    private function member(Environment $environment, User $user, string $role = 'learner'): void
    {
        EnvironmentUser::create(['environment_id' => $environment->id, 'user_id' => $user->id, 'role' => $role]);
    }

    public function test_a_tenant_host_binds_a_member_who_sent_no_environment_id(): void
    {
        $environment = Environment::factory()->create(['primary_domain' => 'acme.test']);
        $user = User::factory()->create(['password' => bcrypt('secret-pass')]);
        $this->member($environment, $user);

        $this->login(['email' => $user->email, 'password' => 'secret-pass'], 'acme.test')
            ->assertOk()
            ->assertJsonPath('environment_id', $environment->id)
            ->assertJsonPath('requires_environment_selection', false);
    }

    public function test_the_shared_host_binds_the_single_membership(): void
    {
        $environment = Environment::factory()->create();
        $user = User::factory()->create(['password' => bcrypt('secret-pass')]);
        $this->member($environment, $user);

        $response = $this->login(['email' => $user->email, 'password' => 'secret-pass'], 'app.getkursa.space')
            ->assertOk()
            ->assertJsonPath('environment_id', $environment->id);

        $token = $response->json('token');
        $this->getJson('/api/user', ['Authorization' => 'Bearer '.$token, 'X-Frontend-Domain' => 'app.getkursa.space'])
            ->assertJsonPath('environment_id', $environment->id);
    }

    public function test_the_shared_host_asks_for_a_choice_when_there_are_several_memberships(): void
    {
        $owned = Environment::factory()->create();
        $joined = Environment::factory()->create();
        $user = User::factory()->create(['password' => bcrypt('secret-pass')]);
        $owned->update(['owner_id' => $user->id]);
        $this->member($joined, $user);

        $this->login(['email' => $user->email, 'password' => 'secret-pass'], 'app.getkursa.space')
            ->assertOk()
            ->assertJsonPath('environment_id', null)
            ->assertJsonPath('requires_environment_selection', true)
            ->assertJsonCount(2, 'environments');
    }

    public function test_the_shared_host_refuses_a_user_with_no_membership(): void
    {
        $user = User::factory()->create(['password' => bcrypt('secret-pass')]);

        $this->login(['email' => $user->email, 'password' => 'secret-pass'], 'app.getkursa.space')
            ->assertForbidden()
            ->assertJsonPath('code', 'no_environment');
    }

    public function test_the_shared_host_still_checks_membership_for_a_requested_environment(): void
    {
        $environment = Environment::factory()->create();
        $user = User::factory()->create(['password' => bcrypt('secret-pass')]);

        $this->login(['email' => $user->email, 'password' => 'secret-pass', 'environment_id' => $environment->id], 'app.getkursa.space')
            ->assertStatus(422);
    }

    public function test_platform_staff_log_in_on_the_shared_host_without_a_binding(): void
    {
        $admin = User::factory()->create(['password' => bcrypt('secret-pass'), 'role' => 'super_admin']);

        $this->login(['email' => $admin->email, 'password' => 'secret-pass'], 'app.getkursa.space')
            ->assertOk()
            ->assertJsonPath('environment_id', null)
            ->assertJsonPath('requires_environment_selection', false);
    }

    public function test_session_login_on_the_shared_host_binds_the_single_membership(): void
    {
        $environment = Environment::factory()->create();
        $user = User::factory()->create(['password' => bcrypt('secret-pass')]);
        $this->member($environment, $user);

        // Origin localhost:3000 makes the request stateful in the test app (it can
        // share cookies with the API host); X-Frontend-Domain outranks it for the
        // tenancy host, so this exercises the shared-host rules on a session login.
        $this->postJson('/api/session/login', [
            'email' => $user->email, 'password' => 'secret-pass', 'device_name' => 'web-client',
        ], ['X-Frontend-Domain' => 'app.getkursa.space', 'Origin' => 'http://localhost:3000', 'Referer' => 'http://localhost:3000/login'])
            ->assertOk()
            ->assertJsonPath('environment_id', $environment->id);

        $this->assertSame($environment->id, session('current_environment_id'));
    }
}
