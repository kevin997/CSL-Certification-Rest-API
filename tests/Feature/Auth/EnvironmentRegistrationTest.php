<?php

namespace Tests\Feature\Auth;

use App\Models\Environment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class EnvironmentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_signup_creates_a_learner_membership_and_tenant_switch_url(): void
    {
        $environment = Environment::factory()->create(['allow_public_signup' => true]);

        $response = $this->withHeader('X-Frontend-Domain', $environment->primary_domain)
            ->postJson('/api/auth/environment-register', [
                'name' => 'Ada Learner',
                'email' => 'ADA@example.com',
                'password' => 'training-password',
                'password_confirmation' => 'training-password',
                'environment_id' => $environment->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonMissingPath('data.token');

        $user = User::where('email', 'ada@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('training-password', $user->password));
        $this->assertDatabaseHas('environment_user', [
            'environment_id' => $environment->id,
            'user_id' => $user->id,
            'role' => 'learner',
            'is_account_setup' => true,
        ]);
        $this->assertStringContainsString('/auth/switch?token=', $response->json('redirect_url'));
    }

    public function test_signup_is_closed_by_default(): void
    {
        $environment = Environment::factory()->create();

        $this->withHeader('X-Frontend-Domain', $environment->primary_domain)
            ->postJson('/api/auth/environment-register', [
                'name' => 'Learner',
                'email' => 'learner@example.com',
                'password' => 'training-password',
                'password_confirmation' => 'training-password',
                'environment_id' => $environment->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'learner@example.com']);
    }

    public function test_signup_cannot_attach_a_new_account_to_a_different_tenant(): void
    {
        $current = Environment::factory()->create(['allow_public_signup' => true]);
        $other = Environment::factory()->create(['allow_public_signup' => true]);

        $this->withHeader('X-Frontend-Domain', $current->primary_domain)
            ->postJson('/api/auth/environment-register', [
                'name' => 'Learner',
                'email' => 'learner@example.com',
                'password' => 'training-password',
                'password_confirmation' => 'training-password',
                'environment_id' => $other->id,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('users', ['email' => 'learner@example.com']);
    }

    public function test_legacy_registration_cannot_bypass_the_signup_setting(): void
    {
        $environment = Environment::factory()->create();

        $this->withHeader('X-Frontend-Domain', $environment->primary_domain)
            ->postJson('/api/register', [
                'name' => 'Learner',
                'email' => 'legacy-learner@example.com',
                'password' => 'training-password',
                'password_confirmation' => 'training-password',
                'device_name' => 'test-client',
                'environment_id' => $environment->id,
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('users', ['email' => 'legacy-learner@example.com']);
    }

    public function test_authenticated_join_requires_public_signup_to_be_enabled(): void
    {
        $environment = Environment::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/environments/{$environment->id}/join")
            ->assertForbidden();

        $this->assertDatabaseMissing('environment_user', [
            'environment_id' => $environment->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_join_route_respects_the_signup_setting(): void
    {
        $environment = Environment::factory()->create();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson("/api/environments/{$environment->id}/join")
            ->assertForbidden();

        $this->assertDatabaseMissing('environment_user', [
            'environment_id' => $environment->id,
            'user_id' => $user->id,
        ]);
    }

    public function test_password_login_joins_an_existing_global_user_when_signup_is_open(): void
    {
        $environment = Environment::factory()->create(['allow_public_signup' => true]);
        $user = User::factory()->create([
            'email' => 'existing@example.com',
            'password' => 'training-password',
        ]);

        $this->withHeader('X-Frontend-Domain', $environment->primary_domain)
            ->postJson('/api/tokens', [
                'email' => $user->email,
                'password' => 'training-password',
                'device_name' => 'test-client',
                'environment_id' => $environment->id,
            ])
            ->assertOk()
            ->assertJsonPath('environment_id', $environment->id)
            ->assertJsonStructure(['token']);

        $this->assertDatabaseHas('environment_user', [
            'environment_id' => $environment->id,
            'user_id' => $user->id,
            'role' => 'learner',
        ]);
    }
}
