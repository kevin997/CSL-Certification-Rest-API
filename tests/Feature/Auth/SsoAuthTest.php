<?php

namespace Tests\Feature\Auth;

use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\SsoProvider;
use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SsoAuthTest extends TestCase
{
    use RefreshDatabase;

    private function makeProvider(Environment $environment, array $overrides = []): SsoProvider
    {
        return SsoProvider::create(array_merge([
            'environment_id' => $environment->id,
            'name' => 'Acme SSO',
            'driver' => 'oidc',
            'issuer_url' => 'https://idp.example.com',
            'client_id' => 'client-123',
            'client_secret' => 'secret-abc',
            'enabled' => true,
            'auto_provision' => true,
            'default_role' => 'learner',
        ], $overrides));
    }

    /**
     * Signs test id_tokens with a throwaway RSA key served through a fake JWKS.
     *
     * @return array{private_key: string, jwks: array<string, mixed>}
     */
    private function rsaJwks(): array
    {
        $resource = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
            'private_key_bits' => 2048,
        ]);
        openssl_pkey_export($resource, $privateKey);
        $details = openssl_pkey_get_details($resource);

        $base64url = fn (string $bytes) => rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');

        return [
            'private_key' => $privateKey,
            'jwks' => ['keys' => [[
                'kty' => 'RSA',
                'kid' => 'test-kid',
                'use' => 'sig',
                'alg' => 'RS256',
                'n' => $base64url($details['rsa']['n']),
                'e' => $base64url($details['rsa']['e']),
            ]]],
        ];
    }

    private function fakeIdp(string $idToken): void
    {
        Http::fake([
            'idp.example.com/.well-known/openid-configuration' => Http::response([
                'issuer' => 'https://idp.example.com',
                'authorization_endpoint' => 'https://idp.example.com/authorize',
                'token_endpoint' => 'https://idp.example.com/token',
                'jwks_uri' => 'https://idp.example.com/jwks',
            ]),
            'idp.example.com/token' => Http::response([
                'id_token' => $idToken,
                'access_token' => 'ignored',
                'token_type' => 'Bearer',
            ]),
            'idp.example.com/jwks' => Http::response($this->fakeJwks),
        ]);
    }

    private array $fakeJwks = [];

    private function idToken(array $keys, array $overrides = []): string
    {
        $this->fakeJwks = $keys['jwks'];

        return JWT::encode(array_merge([
            'iss' => 'https://idp.example.com',
            'aud' => 'client-123',
            'sub' => 'idp-user-1',
            'email' => 'jane@example.com',
            'name' => 'Jane Doe',
            'nonce' => 'test-nonce',
            'exp' => time() + 600,
            'iat' => time(),
        ], $overrides), $keys['private_key'], 'RS256', 'test-kid');
    }

    public function test_redirect_builds_authorization_url_and_caches_state(): void
    {
        Http::fake([
            'idp.example.com/.well-known/openid-configuration' => Http::response([
                'issuer' => 'https://idp.example.com',
                'authorization_endpoint' => 'https://idp.example.com/authorize',
                'token_endpoint' => 'https://idp.example.com/token',
                'jwks_uri' => 'https://idp.example.com/jwks',
            ]),
        ]);

        $environment = Environment::factory()->create();
        $provider = $this->makeProvider($environment);

        $response = $this->get("/api/auth/sso/{$provider->id}/redirect?environment_id={$environment->id}");

        if ($response->exception) {
            $this->fail('Exception: '.get_class($response->exception).' — '.$response->exception->getMessage());
        }
        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringStartsWith('https://idp.example.com/authorize?', $location);
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertSame('client-123', $query['client_id']);
        $this->assertSame('code', $query['response_type']);
        $this->assertNotEmpty($query['state']);

        $cached = Cache::get('sso_state:'.$query['state']);
        $this->assertSame($provider->id, $cached['provider_id']);
        $this->assertSame($environment->id, $cached['environment_id']);
    }

    public function test_redirect_rejects_disabled_provider(): void
    {
        $environment = Environment::factory()->create();
        $provider = $this->makeProvider($environment, ['enabled' => false]);

        $this->get("/api/auth/sso/{$provider->id}/redirect?environment_id={$environment->id}")
            ->assertNotFound();
    }

    public function test_redirect_rejects_provider_of_another_environment(): void
    {
        $environment = Environment::factory()->create();
        $other = Environment::factory()->create();
        $provider = $this->makeProvider($other);

        $this->get("/api/auth/sso/{$provider->id}/redirect?environment_id={$environment->id}")
            ->assertNotFound();
    }

    public function test_callback_rejects_unknown_state(): void
    {
        $this->get('/api/auth/sso/callback?code=abc&state=nope')
            ->assertForbidden();
    }

    public function test_callback_provisions_user_and_redirects_to_switch(): void
    {
        $environment = Environment::factory()->create();
        $provider = $this->makeProvider($environment);
        $keys = $this->rsaJwks();
        $this->fakeIdp($this->idToken($keys));

        Cache::put('sso_state:test-state', [
            'provider_id' => $provider->id,
            'environment_id' => $environment->id,
            'nonce' => 'test-nonce',
        ], 600);

        $response = $this->get('/api/auth/sso/callback?code=abc&state=test-state');

        $response->assertRedirect();
        $location = $response->headers->get('Location');
        $this->assertStringContainsString('/auth/switch', $location);
        $this->assertStringContainsString('token=', $location);

        $user = User::where('email', 'jane@example.com')->first();
        $this->assertNotNull($user);
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('environment_user', [
            'user_id' => $user->id,
            'environment_id' => $environment->id,
            'role' => 'learner',
        ]);
    }

    public function test_callback_blocks_provisioning_when_disabled(): void
    {
        $environment = Environment::factory()->create();
        $provider = $this->makeProvider($environment, ['auto_provision' => false]);
        $keys = $this->rsaJwks();
        $this->fakeIdp($this->idToken($keys));

        Cache::put('sso_state:test-state', [
            'provider_id' => $provider->id,
            'environment_id' => $environment->id,
            'nonce' => 'test-nonce',
        ], 600);

        $this->get('/api/auth/sso/callback?code=abc&state=test-state')
            ->assertForbidden();

        $this->assertNull(User::where('email', 'jane@example.com')->first());
    }

    public function test_admin_can_manage_providers_scoped_to_current_environment(): void
    {
        $admin = User::factory()->create();
        $environment = Environment::factory()->create(['owner_id' => $admin->id]);
        $other = Environment::factory()->create();
        $foreign = $this->makeProvider($other);

        // Sanctum-acting + session env mirrors how environment-scoped admin
        // endpoints receive their tenant context.
        $this->actingAs($admin)
            ->withSession(['current_environment_id' => $environment->id])
            ->postJson('/api/sso-providers', [
                'name' => 'Acme SSO',
                'issuer_url' => 'https://idp.example.com',
                'client_id' => 'client-123',
                'client_secret' => 'secret-abc',
            ])
            ->assertCreated()
            ->assertJsonPath('data.environment_id', $environment->id)
            ->assertJsonMissingPath('data.client_secret');

        $this->actingAs($admin)
            ->withSession(['current_environment_id' => $environment->id])
            ->putJson("/api/sso-providers/{$foreign->id}", ['enabled' => true])
            ->assertNotFound();
    }

    public function test_non_staff_cannot_manage_sso_providers(): void
    {
        $learner = User::factory()->create();
        $environment = Environment::factory()->create();

        $this->actingAs($learner)
            ->withSession(['current_environment_id' => $environment->id])
            ->getJson('/api/sso-providers')
            ->assertForbidden();
    }

    public function test_staff_can_toggle_an_sso_provider_with_a_partial_update(): void
    {
        $owner = User::factory()->create();
        $environment = Environment::factory()->create(['owner_id' => $owner->id]);
        $provider = $this->makeProvider($environment);

        $this->actingAs($owner)
            ->withSession(['current_environment_id' => $environment->id])
            ->putJson("/api/sso-providers/{$provider->id}", ['enabled' => false])
            ->assertOk()
            ->assertJsonPath('data.enabled', false);
    }

    public function test_callback_existing_member_gets_switch_without_new_membership(): void
    {
        $environment = Environment::factory()->create();
        $provider = $this->makeProvider($environment);
        $user = User::factory()->create(['email' => 'jane@example.com']);
        EnvironmentUser::create([
            'environment_id' => $environment->id,
            'user_id' => $user->id,
            'role' => 'individual_teacher',
            'joined_at' => now(),
        ]);

        $keys = $this->rsaJwks();
        $this->fakeIdp($this->idToken($keys));

        Cache::put('sso_state:test-state', [
            'provider_id' => $provider->id,
            'environment_id' => $environment->id,
            'nonce' => 'test-nonce',
        ], 600);

        $response = $this->get('/api/auth/sso/callback?code=abc&state=test-state');

        $response->assertRedirect();
        $this->assertSame(1, EnvironmentUser::where('user_id', $user->id)->count());
    }
}
