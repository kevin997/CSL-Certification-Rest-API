<?php

namespace Tests\Feature\Auth;

use App\Models\Environment;
use App\Models\SocialAuthProvider;
use App\Models\SocialIdentity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Tests\TestCase;

class SocialAuthTest extends TestCase
{
    use RefreshDatabase;

    private function makeProvider(Environment $environment, string $provider = 'google'): SocialAuthProvider
    {
        return SocialAuthProvider::create([
            'environment_id' => $environment->id,
            'provider' => $provider,
            'client_id' => 'client-'.$provider,
            'client_secret' => 'secret-'.$provider,
            'enabled' => true,
        ]);
    }

    public function test_provider_settings_return_callback_urls_without_secrets(): void
    {
        $owner = User::factory()->create();
        $environment = Environment::factory()->create(['owner_id' => $owner->id]);
        $this->makeProvider($environment);

        $response = $this->actingAs($owner)
            ->withHeader('X-Frontend-Domain', $environment->primary_domain)
            ->getJson('/api/social-auth/settings');

        $response->assertOk()
            ->assertJsonPath('data.allow_public_signup', false)
            ->assertJsonPath('data.providers.0.provider', 'google')
            ->assertJsonMissingPath('data.providers.0.client_secret');

        $this->actingAs($owner)
            ->withHeader('X-Frontend-Domain', $environment->primary_domain)
            ->putJson('/api/social-auth/providers/google', [
                'client_id' => 'new-google-client',
                'client_secret' => 'new-google-secret',
                'enabled' => true,
            ])
            ->assertOk()
            ->assertJsonPath('data.client_id', 'new-google-client')
            ->assertJsonPath('data.client_secret_configured', true)
            ->assertJsonMissingPath('data.client_secret');

        $stored = SocialAuthProvider::firstOrFail();
        $this->assertSame('new-google-secret', $stored->client_secret);
        $this->assertNotSame('new-google-secret', $stored->getRawOriginal('client_secret'));

        $this->actingAs($owner)
            ->withHeader('X-Frontend-Domain', $environment->primary_domain)
            ->putJson('/api/social-auth/settings/signup', ['allow_public_signup' => true])
            ->assertOk()
            ->assertJsonPath('data.allow_public_signup', true);

        $this->assertTrue($environment->fresh()->allow_public_signup);
    }

    public function test_non_staff_cannot_change_social_auth_settings(): void
    {
        $learner = User::factory()->create();
        $environment = Environment::factory()->create();

        $this->actingAs($learner)
            ->withHeader('X-Frontend-Domain', $environment->primary_domain)
            ->putJson('/api/social-auth/settings/signup', ['allow_public_signup' => true])
            ->assertForbidden();
    }

    public function test_redirect_binds_provider_and_environment_to_state_and_uses_pkce(): void
    {
        $environment = Environment::factory()->create();
        $this->makeProvider($environment);

        $response = $this->withHeader('X-Frontend-Domain', $environment->primary_domain)
            ->get("/api/auth/social/google/redirect?environment_id={$environment->id}");

        $response->assertRedirect();
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertNotEmpty($query['state']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame([
            'environment_id' => $environment->id,
            'provider' => 'google',
        ], session('social_auth.environment:'.hash('sha256', $query['state'])));
    }

    public function test_facebook_and_linkedin_drivers_build_provider_redirects(): void
    {
        $environment = Environment::factory()->create();

        foreach (['facebook', 'linkedin'] as $provider) {
            $this->makeProvider($environment, $provider);
            $response = $this->get("/api/auth/social/{$provider}/redirect?environment_id={$environment->id}");

            $response->assertRedirect();
            $this->assertNotEmpty(parse_url($response->headers->get('Location'), PHP_URL_HOST));
        }
    }

    public function test_social_login_creates_a_learner_membership_and_switches_to_the_academy(): void
    {
        $environment = Environment::factory()->create(['allow_public_signup' => true]);
        $this->makeProvider($environment);
        $state = 'google-state';
        $this->withSession([
            'state' => $state,
            'social_auth.environment:'.hash('sha256', $state) => [
                'environment_id' => $environment->id,
                'provider' => 'google',
            ],
        ]);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-subject-1',
            'email' => 'learner@example.com',
            'email_verified' => true,
            'name' => 'Google Learner',
        ]));

        $response = $this->get('/api/auth/social/google/callback?state='.$state);

        $response->assertRedirect();
        $this->assertStringContainsString('/auth/switch?token=', $response->headers->get('Location'));

        $user = User::where('email', 'learner@example.com')->firstOrFail();
        $this->assertDatabaseHas('environment_user', [
            'environment_id' => $environment->id,
            'user_id' => $user->id,
            'role' => 'learner',
        ]);
        $this->assertDatabaseHas('social_identities', [
            'provider' => 'google',
            'provider_user_id' => 'google-subject-1',
            'user_id' => $user->id,
        ]);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_social_login_does_not_create_an_account_when_signup_is_closed(): void
    {
        $environment = Environment::factory()->create();
        $this->makeProvider($environment);
        $state = 'google-closed-state';
        $this->withSession([
            'state' => $state,
            'social_auth.environment:'.hash('sha256', $state) => [
                'environment_id' => $environment->id,
                'provider' => 'google',
            ],
        ]);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-subject-2',
            'email' => 'new-learner@example.com',
            'email_verified' => true,
            'name' => 'New Learner',
        ]));

        $this->get('/api/auth/social/google/callback?state='.$state)
            ->assertRedirectContains('social_error=social_signup_disabled');

        $this->assertDatabaseMissing('users', ['email' => 'new-learner@example.com']);
        $this->assertDatabaseMissing('environment_user', ['environment_id' => $environment->id]);
    }

    public function test_social_callback_rejects_state_not_bound_to_the_browser_session(): void
    {
        $environment = Environment::factory()->create(['allow_public_signup' => true]);
        $this->makeProvider($environment);
        $state = 'browser-bound-state';
        $this->withSession([
            'state' => 'another-browser-state',
            'social_auth.environment:'.hash('sha256', $state) => [
                'environment_id' => $environment->id,
                'provider' => 'google',
            ],
        ]);
        Socialite::fake('google', SocialiteUser::fake([
            'id' => 'google-subject-3',
            'email' => 'attacker@example.com',
            'email_verified' => true,
        ]));

        $this->get('/api/auth/social/google/callback?state='.$state)->assertForbidden();
        $this->assertDatabaseMissing('users', ['email' => 'attacker@example.com']);
    }

    public function test_social_callback_rejects_state_from_another_provider(): void
    {
        $environment = Environment::factory()->create(['allow_public_signup' => true]);
        $this->makeProvider($environment, 'google');
        $state = 'mismatched-provider-state';
        $this->withSession([
            'state' => $state,
            'social_auth.environment:'.hash('sha256', $state) => [
                'environment_id' => $environment->id,
                'provider' => 'google',
            ],
        ]);

        $this->get('/api/auth/social/facebook/callback?state='.$state)->assertForbidden();
        $this->assertSame(0, SocialIdentity::count());
    }
}
