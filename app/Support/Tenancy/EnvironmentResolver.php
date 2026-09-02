<?php

namespace App\Support\Tenancy;

use App\Models\Environment;
use App\Models\EnvironmentUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * The one place that decides which environment a request is for.
 *
 * Tenant host: the host is authoritative. Shared host: the environment is the
 * authenticated principal's binding, minted at login or switch after a
 * membership check. Client-supplied identifiers are never consulted here; the
 * public endpoints that accept one call explicitEnvironment() themselves.
 */
final class EnvironmentResolver
{
    public const REQUEST_ATTRIBUTE = 'tenancy.context';

    private const ABILITY_PREFIX = 'environment_id:';

    /**
     * The environment this request is for.
     *
     * On a shared host the hostname identifies nobody, so the binding on the
     * authenticated principal decides. Everywhere else the host decides, with
     * config('tenancy.host_aliases') mapping a platform frontend onto the
     * tenant domain it stands for.
     */
    public function resolve(Request $request): EnvironmentContext
    {
        $host = $this->frontendHost($request);

        if ($this->isSharedHost($host)) {
            $environment = $this->bindingEnvironment();

            return $environment
                ? new EnvironmentContext($environment, EnvironmentContext::SOURCE_BINDING, $host)
                : EnvironmentContext::none($host);
        }

        $environment = Environment::findActiveByDomain($host);

        if (! $environment) {
            $aliases = (array) config('tenancy.host_aliases', []);
            $alias = $aliases[$host] ?? null;

            if (is_string($alias) && $alias !== '') {
                $environment = Environment::findActiveByDomain($alias);
            }
        }

        return $environment
            ? new EnvironmentContext($environment, EnvironmentContext::SOURCE_HOST, $host)
            : EnvironmentContext::none($host);
    }

    /**
     * The host the browser is on: the explicit header, then the Origin host,
     * then the Referer host, then the API's own host. Lowercased; the header
     * keeps its port so localhost:3000 style tenants keep working.
     */
    public function frontendHost(Request $request): string
    {
        $header = trim((string) $request->header('X-Frontend-Domain', ''));

        if ($header !== '') {
            return strtolower($header);
        }

        foreach (['Origin', 'Referer'] as $name) {
            $value = (string) $request->header($name, '');
            $host = $value !== '' ? (parse_url($value, PHP_URL_HOST) ?: '') : '';

            if ($host !== '') {
                return strtolower($host);
            }
        }

        return strtolower($request->getHost());
    }

    /**
     * Whether the host is one of config('tenancy.shared_hosts'). Matched in
     * full and again without the port, so app.getkursa.space:3000 counts.
     */
    public function isSharedHost(string $host): bool
    {
        $host = strtolower(trim($host));
        $bare = preg_replace('/:\d+$/', '', $host);

        foreach ((array) config('tenancy.shared_hosts', []) as $shared) {
            $shared = strtolower(trim((string) $shared));

            if ($shared !== '' && ($host === $shared || $bare === $shared)) {
                return true;
            }
        }

        return false;
    }

    /**
     * An environment named by the request itself: `environment_id` or `domain`
     * (numeric id, primary or additional domain). For unauthenticated public
     * endpoints only; active environments only.
     */
    public function explicitEnvironment(Request $request): ?Environment
    {
        foreach (['environment_id', 'domain'] as $name) {
            $identifier = trim((string) $request->query($name, ''));

            if ($identifier === '') {
                continue;
            }

            $environment = Environment::resolveByIdentifier($identifier);

            if ($environment && $environment->is_active) {
                return $environment;
            }
        }

        return null;
    }

    /**
     * The environment a token is bound to, from its `environment_id:{id}`
     * ability. A malformed ability is skipped rather than treated as the
     * answer, so one bad entry cannot mask a well-formed one behind it.
     *
     * @param  array<int, string>  $abilities
     */
    public static function environmentIdFromAbilities(array $abilities): ?int
    {
        foreach ($abilities as $ability) {
            if (! is_string($ability) || ! str_starts_with($ability, self::ABILITY_PREFIX)) {
                continue;
            }

            $id = substr($ability, strlen(self::ABILITY_PREFIX));

            if (ctype_digit($id)) {
                return (int) $id;
            }
        }

        return null;
    }

    /**
     * The environment the authenticated principal is bound to.
     *
     * The sanctum guard is asked explicitly: the default guard is `web`
     * (config/auth.php), which cannot see a bearer token from global middleware.
     *
     * The ability path is trusted as it stands, because minting the token
     * checked membership. The session path re-checks membership here, because
     * `current_environment_id` is not only written by SessionAuthController
     * after its own check — DetectEnvironment also writes it from whatever host
     * the request arrived on, so on its own the key proves nothing.
     */
    private function bindingEnvironment(): ?Environment
    {
        $user = Auth::guard('sanctum')->user();

        if (! $user) {
            return null;
        }

        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $id = self::environmentIdFromAbilities((array) $token->abilities);

            return $id ? Environment::findActive($id) : null;
        }

        if (session()->isStarted() && session()->has('current_environment_id')) {
            $environment = Environment::findActive((int) session('current_environment_id'));

            return $environment && $this->isMember($user, $environment) ? $environment : null;
        }

        return null;
    }

    /**
     * Whether the user owns the environment or holds a membership row in it.
     */
    private function isMember(Authenticatable $user, Environment $environment): bool
    {
        $userId = (int) $user->getAuthIdentifier();

        if ((int) $environment->owner_id === $userId) {
            return true;
        }

        return EnvironmentUser::query()
            ->where('environment_id', $environment->id)
            ->where('user_id', $userId)
            ->exists();
    }
}
