<?php

namespace App\Http\Controllers\Api\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\SsoProvider;
use App\Models\User;
use App\Support\Sso\OidcClient;
use App\Support\Sso\SamlClient;
use App\Support\Tenancy\EnvironmentResolver;
use App\Support\Tenancy\SwitchTokenIssuer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * SSO sign-in for academy environments (OIDC and SAML 2.0). The handoff never
 * places a session token in a URL: the callback hands the browser a one-time
 * switch token which the tenant's /auth/switch page exchanges for a real
 * session — the same proven handoff academy switching already uses.
 */
class SsoAuthController extends Controller
{
    private const STATE_PREFIX = 'sso_state:';

    private const STATE_TTL_SECONDS = 600;

    public function __construct(
        private readonly OidcClient $oidc,
        private readonly SamlClient $saml,
    ) {}

    public function redirect(Request $request, SsoProvider $provider): RedirectResponse
    {
        abort_unless($provider->enabled, 404);

        $environment = $this->resolveEnvironment($request, $provider);

        $state = Str::random(48);
        $nonce = Str::random(32);

        $payload = [
            'provider_id' => $provider->id,
            'environment_id' => $environment->id,
            'nonce' => $nonce,
        ];

        if ($provider->driver === 'oidc') {
            Cache::put(self::STATE_PREFIX.$state, $payload, now()->addSeconds(self::STATE_TTL_SECONDS));

            return redirect()->away(
                $this->oidc->authorizationUrl($provider, route('sso.callback'), $state, $nonce)
            );
        }

        if ($provider->driver === 'saml') {
            $login = $this->saml->loginRedirect($provider, $state);
            // The request id lets the ACS enforce InResponseTo — the response
            // must answer the AuthnRequest we just emitted, not an attacker-
            // initiated IdP-unbound sign-in.
            $payload['saml_request_id'] = $login['request_id'];
            Cache::put(self::STATE_PREFIX.$state, $payload, now()->addSeconds(self::STATE_TTL_SECONDS));

            return redirect()->away($login['url']);
        }

        abort(404);
    }

    /** OIDC: GET callback with ?code&state. */
    public function callback(Request $request): RedirectResponse
    {
        abort_if($request->filled('error'), 403, 'Identity provider refused the sign-in');

        $validated = $request->validate([
            'code' => 'required|string',
            'state' => 'required|string',
        ]);

        [$provider, $environment, $payload] = $this->consumeState($validated['state']);
        abort_unless($provider->driver === 'oidc', 404);

        $claims = $this->oidc->claimsFromCode(
            $provider,
            $validated['code'],
            route('sso.callback'),
            $payload['nonce']
        );

        return $this->provisionedSwitchRedirect(
            $provider,
            $environment,
            (string) ($claims['email'] ?? ''),
            $claims['name'] ?? $claims['preferred_username'] ?? null
        );
    }

    /** SAML: HTTP-POST assertion consumer service. */
    public function samlAcs(Request $request, SsoProvider $provider): RedirectResponse
    {
        $validated = $request->validate([
            'SAMLResponse' => 'required|string',
            'RelayState' => 'required|string',
        ]);

        [$provider, $environment, $payload] = $this->consumeState($validated['RelayState']);
        abort_unless($provider->driver === 'saml', 404);

        // SP-initiated only: without a stored request id there is nothing to
        // bind the response to — an IdP-initiated sign-in is not accepted.
        abort_unless(! empty($payload['saml_request_id']), 403, 'Sign-in failed');

        try {
            $claims = $this->saml->claimsFromResponse($provider, $payload['saml_request_id']);
        } catch (RuntimeException $e) {
            // A rejected assertion is a client failure, not a server error —
            // and never an internal detail worth surfacing to the browser.
            Log::warning('SAML response rejected', [
                'provider_id' => $provider->id,
                'reason' => $e->getMessage(),
            ]);
            abort(403, 'Sign-in failed');
        }

        return $this->provisionedSwitchRedirect($provider, $environment, (string) ($claims['email'] ?? ''), $claims['name'] ?? null);
    }

    /** SP metadata XML the IdP imports to register this academy. */
    public function metadata(SsoProvider $provider): HttpResponse
    {
        abort_unless($provider->driver === 'saml', 404);

        return response($this->saml->metadata($provider), 200, [
            'Content-Type' => 'application/samlmetadata+xml',
        ]);
    }

    /**
     * @return array{0: SsoProvider, 1: Environment, 2: array<string, mixed>}
     */
    private function consumeState(string $state): array
    {
        // Pull, not get: a state is consumed once, so a replayed callback fails.
        $payload = Cache::pull(self::STATE_PREFIX.$state);
        abort_unless(is_array($payload), 403, 'Unknown or expired sign-in state');

        $provider = SsoProvider::find($payload['provider_id']);
        $environment = Environment::find($payload['environment_id']);
        abort_unless($provider && $provider->enabled && $environment && $environment->is_active, 404);

        return [$provider, $environment, $payload];
    }

    private function resolveEnvironment(Request $request, SsoProvider $provider): Environment
    {
        $resolver = app(EnvironmentResolver::class);
        $environment = $request->filled('environment_id')
            ? $resolver->explicitEnvironment($request)
            : $resolver->resolve($request)->environment;

        // The provider must belong to the academy the visitor is on — otherwise
        // a guessed provider id could enrol sign-ins into the wrong tenant.
        abort_unless(
            $environment && (int) $provider->environment_id === (int) $environment->id,
            404
        );

        return $environment;
    }

    private function provisionedSwitchRedirect(
        SsoProvider $provider,
        Environment $environment,
        string $email,
        ?string $name,
    ): RedirectResponse {
        $email = strtolower($email);
        abort_unless($email !== '', 403, 'Identity provider did not return an email address');

        $user = User::where('email', $email)->first();

        if (! $user && ! $provider->auto_provision) {
            abort(403, 'No account exists for this email and provisioning is disabled');
        }

        if (! $user) {
            $user = User::create([
                'name' => $name ?? $email,
                'email' => $email,
                'password' => Str::random(40),
                'role' => UserRole::LEARNER,
            ]);
            // The IdP already attested the email; it is not in $fillable.
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        $isMember = EnvironmentUser::where('user_id', $user->id)
            ->where('environment_id', $environment->id)
            ->exists();

        if (! $isMember) {
            if (! $provider->auto_provision) {
                abort(403, 'This account is not a member of the academy');
            }

            EnvironmentUser::create([
                'environment_id' => $environment->id,
                'user_id' => $user->id,
                'role' => $provider->default_role,
                'joined_at' => now(),
                'is_account_setup' => true,
            ]);
        }

        Log::info('SSO sign-in', [
            'provider_id' => $provider->id,
            'environment_id' => $environment->id,
            'user_id' => $user->id,
        ]);

        $issuer = app(SwitchTokenIssuer::class);
        $token = $issuer->issue($user, $environment, 60);

        return redirect()->away($issuer->redirectUrl($environment, $token));
    }
}
