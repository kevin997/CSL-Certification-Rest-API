<?php

namespace Tests\Feature;

use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SessionAuthRoleConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_session_user_return_the_member_environment_role(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create([
            'role' => 'individual_teacher',
        ]);
        $environment = Environment::factory()->create([
            'owner_id' => $owner->id,
        ]);
        EnvironmentUser::query()->create([
            'environment_id' => $environment->id,
            'user_id' => $member->id,
            'role' => 'learner',
        ]);

        $login = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/login',
        ])->postJson('/api/session/login', [
            'email' => $member->email,
            'password' => 'password',
            'environment_id' => $environment->id,
        ]);

        $login->assertOk()
            ->assertJsonPath('role', 'learner')
            ->assertJsonPath('user.role', 'learner')
            ->assertJsonPath('user_role', 'individual_teacher')
            ->assertJsonPath('environment_role', 'learner');

        $this->withSession(['current_environment_id' => $environment->id])
            ->actingAs($member)
            ->getJson('/api/session/user')
            ->assertOk()
            ->assertJsonPath('role', 'learner')
            ->assertJsonPath('user.role', 'learner')
            ->assertJsonPath('user_role', 'individual_teacher')
            ->assertJsonPath('environment_role', 'learner');
    }

    public function test_session_user_returns_the_owner_effective_role_without_a_membership(): void
    {
        $owner = User::factory()->create([
            'role' => 'company_teacher',
        ]);
        $environment = Environment::factory()->create([
            'owner_id' => $owner->id,
        ]);

        $this->withSession(['current_environment_id' => $environment->id])
            ->actingAs($owner)
            ->getJson('/api/session/user')
            ->assertOk()
            ->assertJsonPath('role', 'company_teacher')
            ->assertJsonPath('user.role', 'company_teacher')
            ->assertJsonPath('user_role', 'company_teacher')
            ->assertJsonPath('environment_role', null)
            ->assertJsonPath('is_owner', true);
    }

    public function test_session_user_resolves_the_environment_from_a_token_ability(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create([
            'role' => 'individual_teacher',
        ]);
        $environment = Environment::factory()->create([
            'owner_id' => $owner->id,
        ]);
        EnvironmentUser::query()->create([
            'environment_id' => $environment->id,
            'user_id' => $member->id,
            'role' => 'learner',
        ]);
        $token = $member->createToken('session-role-test', [
            'environment_id:'.$environment->id,
        ])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/session/user')
            ->assertOk()
            ->assertJsonPath('environment_id', $environment->id)
            ->assertJsonPath('role', 'learner')
            ->assertJsonPath('user.role', 'learner')
            ->assertJsonPath('user_role', 'individual_teacher')
            ->assertJsonPath('environment_role', 'learner');
    }

    public function test_token_login_returns_the_member_environment_role(): void
    {
        [$member, $environment] = $this->memberWithLearnerEnvironmentRole();

        $this->postJson('/api/tokens', [
            'email' => $member->email,
            'password' => 'password',
            'device_name' => 'session-role-test',
            'environment_id' => $environment->id,
        ])
            ->assertOk()
            ->assertJsonPath('role', 'learner')
            ->assertJsonPath('user.role', 'learner')
            ->assertJsonPath('user_role', 'individual_teacher')
            ->assertJsonPath('environment_role', 'learner');
    }

    public function test_token_login_with_environment_credentials_resolves_the_matched_environment(): void
    {
        [$member, $environment] = $this->memberWithLearnerEnvironmentRole();
        EnvironmentUser::query()
            ->where('environment_id', $environment->id)
            ->where('user_id', $member->id)
            ->update([
                'environment_email' => $member->email,
                'environment_password' => Hash::make('environment-secret'),
                'use_environment_credentials' => true,
            ]);

        $login = $this->postJson('/api/tokens', [
            'email' => $member->email,
            'password' => 'environment-secret',
            'device_name' => 'matched-environment-test',
        ]);

        $login->assertOk()
            ->assertJsonPath('environment_id', $environment->id)
            ->assertJsonPath('role', 'learner')
            ->assertJsonPath('user.role', 'learner')
            ->assertJsonPath('user_role', 'individual_teacher')
            ->assertJsonPath('environment_role', 'learner');

        $this->withToken($login->json('token'))
            ->getJson('/api/session/user')
            ->assertOk()
            ->assertJsonPath('environment_id', $environment->id)
            ->assertJsonPath('role', 'learner');
    }

    public function test_session_user_rejects_a_stale_environment_membership(): void
    {
        [$member, $environment] = $this->memberWithLearnerEnvironmentRole();
        EnvironmentUser::query()
            ->where('environment_id', $environment->id)
            ->where('user_id', $member->id)
            ->delete();

        $this->withSession(['current_environment_id' => $environment->id])
            ->actingAs($member)
            ->getJson('/api/session/user')
            ->assertForbidden();
    }

    public function test_user_endpoint_returns_the_member_environment_role(): void
    {
        [$member, $environment] = $this->memberWithLearnerEnvironmentRole();

        $this->withSession(['current_environment_id' => $environment->id])
            ->actingAs($member)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('role', 'learner')
            ->assertJsonPath('user.role', 'learner')
            ->assertJsonPath('user_role', 'individual_teacher')
            ->assertJsonPath('environment_role', 'learner');
    }

    public function test_user_endpoint_rejects_a_stale_environment_membership(): void
    {
        [$member, $environment] = $this->memberWithLearnerEnvironmentRole();
        EnvironmentUser::query()
            ->where('environment_id', $environment->id)
            ->where('user_id', $member->id)
            ->delete();

        $this->withSession(['current_environment_id' => $environment->id])
            ->actingAs($member)
            ->getJson('/api/user')
            ->assertForbidden();
    }

    public function test_user_endpoint_preserves_marketplace_environment_details(): void
    {
        $owner = User::factory()->create([
            'role' => 'company_teacher',
        ]);
        $environment = Environment::factory()->create([
            'owner_id' => $owner->id,
        ]);
        $token = $owner->createToken('marketplace-auth', ['marketplace'])->plainTextToken;

        $this->withToken($token)
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('role', 'company_teacher')
            ->assertJsonPath('environment_id', null)
            ->assertJsonPath('environment.id', $environment->id)
            ->assertJsonPath('environment.name', $environment->name);
    }

    public function test_session_login_does_not_authenticate_or_poison_the_session_for_an_unauthorized_environment(): void
    {
        $candidate = User::factory()->create();
        $unrelatedOwner = User::factory()->create();
        $unauthorizedEnvironment = Environment::factory()->create([
            'owner_id' => $unrelatedOwner->id,
        ]);

        $response = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/login',
        ])->postJson('/api/session/login', [
            'email' => $candidate->email,
            'password' => 'password',
            'environment_id' => $unauthorizedEnvironment->id,
        ]);

        $response->assertUnprocessable()
            ->assertSessionMissing('current_environment_id');
        $this->assertGuest();
    }

    public function test_session_login_denial_preserves_an_existing_authenticated_context(): void
    {
        $existingUser = User::factory()->create();
        $existingEnvironment = Environment::factory()->create([
            'owner_id' => $existingUser->id,
        ]);
        $candidate = User::factory()->create();
        $unrelatedOwner = User::factory()->create();
        $unauthorizedEnvironment = Environment::factory()->create([
            'owner_id' => $unrelatedOwner->id,
        ]);

        $response = $this->withHeaders([
            'Origin' => 'http://localhost:3000',
            'Referer' => 'http://localhost:3000/login',
        ])->withSession(['current_environment_id' => $existingEnvironment->id])
            ->actingAs($existingUser)
            ->postJson('/api/session/login', [
                'email' => $candidate->email,
                'password' => 'password',
                'environment_id' => $unauthorizedEnvironment->id,
            ]);

        $response->assertUnprocessable()
            ->assertSessionHas('current_environment_id', $existingEnvironment->id);
        $this->assertAuthenticatedAs($existingUser);
    }

    public function test_session_login_bad_credentials_preserve_an_existing_authenticated_context(): void
    {
        $existingUser = User::factory()->create();
        $existingEnvironment = Environment::factory()->create([
            'owner_id' => $existingUser->id,
        ]);
        $requestedEnvironment = Environment::factory()->create([
            'owner_id' => User::factory()->create()->id,
        ]);

        $response = $this->withSession(['current_environment_id' => $existingEnvironment->id])
            ->actingAs($existingUser)
            ->postJson('/api/session/login', [
                'email' => 'missing@example.com',
                'password' => 'incorrect-password',
                'environment_id' => $requestedEnvironment->id,
            ]);

        $response->assertUnprocessable()
            ->assertSessionHas('current_environment_id', $existingEnvironment->id);
        $this->assertAuthenticatedAs($existingUser);
    }

    public function test_token_login_bad_credentials_preserve_an_existing_authenticated_context(): void
    {
        $existingUser = User::factory()->create();
        $existingEnvironment = Environment::factory()->create([
            'owner_id' => $existingUser->id,
        ]);
        $requestedEnvironment = Environment::factory()->create([
            'owner_id' => User::factory()->create()->id,
        ]);

        $response = $this->withSession(['current_environment_id' => $existingEnvironment->id])
            ->actingAs($existingUser)
            ->postJson('/api/tokens', [
                'email' => 'missing@example.com',
                'password' => 'incorrect-password',
                'device_name' => 'bad-credentials-token-test',
                'environment_id' => $requestedEnvironment->id,
            ]);

        $response->assertUnprocessable()
            ->assertSessionHas('current_environment_id', $existingEnvironment->id);
        $this->assertAuthenticatedAs($existingUser);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_token_login_does_not_authenticate_or_poison_the_session_for_an_unauthorized_environment(): void
    {
        $candidate = User::factory()->create();
        $unrelatedOwner = User::factory()->create();
        $unauthorizedEnvironment = Environment::factory()->create([
            'owner_id' => $unrelatedOwner->id,
        ]);

        $response = $this->postJson('/api/tokens', [
            'email' => $candidate->email,
            'password' => 'password',
            'device_name' => 'denied-token-test',
            'environment_id' => $unauthorizedEnvironment->id,
        ]);

        $response->assertUnprocessable()
            ->assertSessionMissing('current_environment_id');
        $this->assertGuest();
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_successful_token_login_does_not_create_a_web_session(): void
    {
        [$member, $environment] = $this->memberWithLearnerEnvironmentRole();

        $response = $this->postJson('/api/tokens', [
            'email' => $member->email,
            'password' => 'password',
            'device_name' => 'stateless-token-test',
            'environment_id' => $environment->id,
        ]);

        $response->assertOk()
            ->assertSessionMissing('current_environment_id');
        $this->assertGuest();
    }

    public function test_token_login_denial_preserves_an_existing_authenticated_context(): void
    {
        $existingUser = User::factory()->create();
        $existingEnvironment = Environment::factory()->create([
            'owner_id' => $existingUser->id,
        ]);
        $candidate = User::factory()->create();
        $unrelatedOwner = User::factory()->create();
        $unauthorizedEnvironment = Environment::factory()->create([
            'owner_id' => $unrelatedOwner->id,
        ]);

        $response = $this->withSession(['current_environment_id' => $existingEnvironment->id])
            ->actingAs($existingUser)
            ->postJson('/api/tokens', [
                'email' => $candidate->email,
                'password' => 'password',
                'device_name' => 'denied-token-test',
                'environment_id' => $unauthorizedEnvironment->id,
            ]);

        $response->assertUnprocessable()
            ->assertSessionHas('current_environment_id', $existingEnvironment->id);
        $this->assertAuthenticatedAs($existingUser);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_academy_switch_returns_the_member_environment_role(): void
    {
        [$member, $environment] = $this->memberWithLearnerEnvironmentRole();
        Cache::put('academy_switch_token:session-role-test', [
            'user_id' => $member->id,
            'target_environment_id' => $environment->id,
        ]);

        $this->postJson('/api/auth/validate-switch-token', [
            'token' => 'session-role-test',
            'device_name' => 'session-role-test',
        ])
            ->assertOk()
            ->assertJsonPath('role', 'learner')
            ->assertJsonPath('user.role', 'learner')
            ->assertJsonPath('user_role', 'individual_teacher')
            ->assertJsonPath('environment_role', 'learner');
    }

    /**
     * @return array{0: User, 1: Environment}
     */
    private function memberWithLearnerEnvironmentRole(): array
    {
        $owner = User::factory()->create();
        $member = User::factory()->create([
            'role' => 'individual_teacher',
        ]);
        $environment = Environment::factory()->create([
            'owner_id' => $owner->id,
        ]);
        EnvironmentUser::query()->create([
            'environment_id' => $environment->id,
            'user_id' => $member->id,
            'role' => 'learner',
        ]);

        return [$member, $environment];
    }
}
