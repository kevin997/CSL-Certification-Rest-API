<?php

namespace App\Support\Sso;

use App\Models\SsoProvider;
use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Minimal OIDC relying party: discovery, authorization redirect and code
 * exchange with id_token signature verification against the issuer JWKS.
 * Dependency-free on purpose — the platform only needs the code flow.
 */
final class OidcClient
{
    /**
     * @return array{authorization_endpoint: string, token_endpoint: string, jwks_uri: string, issuer: string}
     */
    public function discovery(SsoProvider $provider): array
    {
        $document = Cache::remember(
            "sso_discovery:{$provider->id}",
            now()->addHour(),
            function () use ($provider) {
                $response = Http::timeout(10)->get(
                    rtrim($provider->issuer_url, '/').'/.well-known/openid-configuration'
                );

                if (! $response->successful()) {
                    throw new RuntimeException("OIDC discovery failed for provider {$provider->id}");
                }

                return $response->json();
            }
        );

        foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri', 'issuer'] as $key) {
            if (empty($document[$key])) {
                throw new RuntimeException("OIDC discovery document for provider {$provider->id} is missing {$key}");
            }
        }

        return $document;
    }

    public function authorizationUrl(SsoProvider $provider, string $redirectUri, string $state, string $nonce): string
    {
        $discovery = $this->discovery($provider);

        return $discovery['authorization_endpoint'].'?'.http_build_query([
            'client_id' => $provider->client_id,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => implode(' ', $provider->scopeList()),
            'state' => $state,
            'nonce' => $nonce,
        ]);
    }

    /**
     * Exchanges the authorization code and returns verified id_token claims.
     *
     * @return array<string, mixed>
     */
    public function claimsFromCode(SsoProvider $provider, string $code, string $redirectUri, string $expectedNonce): array
    {
        $discovery = $this->discovery($provider);

        $response = Http::asForm()->timeout(10)->post($discovery['token_endpoint'], [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $redirectUri,
            'client_id' => $provider->client_id,
            'client_secret' => $provider->client_secret,
        ]);

        if (! $response->successful() || empty($response->json('id_token'))) {
            throw new RuntimeException("OIDC token exchange failed for provider {$provider->id}");
        }

        $keys = JWK::parseKeySet($this->jwks($discovery['jwks_uri'], $provider));
        $claims = (array) JWT::decode($response->json('id_token'), $keys);

        if (($claims['iss'] ?? null) !== $discovery['issuer']) {
            throw new RuntimeException('OIDC id_token issuer mismatch');
        }

        $audience = (array) ($claims['aud'] ?? []);
        if (! in_array($provider->client_id, $audience, true)) {
            throw new RuntimeException('OIDC id_token audience mismatch');
        }

        if (($claims['nonce'] ?? null) !== $expectedNonce) {
            throw new RuntimeException('OIDC id_token nonce mismatch');
        }

        return $claims;
    }

    /**
     * @return array<string, mixed>
     */
    private function jwks(string $jwksUri, SsoProvider $provider): array
    {
        // Short cache: key rotation tolerates a few minutes of staleness; a
        // longer cache turns IdP key rotation into a sign-in outage.
        return Cache::remember("sso_jwks:{$provider->id}", now()->addMinutes(10), function () use ($jwksUri, $provider) {
            $response = Http::timeout(10)->get($jwksUri);

            if (! $response->successful()) {
                throw new RuntimeException("OIDC JWKS fetch failed for provider {$provider->id}");
            }

            $jwks = $response->json();
            if (empty($jwks['keys'])) {
                throw new InvalidArgumentException("OIDC JWKS for provider {$provider->id} contains no keys");
            }

            return $jwks;
        });
    }
}
