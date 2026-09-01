# Shared App Host Tenancy — API Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let an environment owner, learner, or collaborator use the platform from the shared host `app.getkursa.space` before their own domain is live, without any tenant data leaking across environments.

**Architecture:** One `EnvironmentResolver` produces the environment context for every request (host on tenant hosts, login binding on shared hosts). One `EnsureEnvironmentResolved` guard refuses tenant routes without one. One `TenantUrl` builder decides which host every outbound link uses from a new `environments.domain_verified_at` column. Login and academy switching bind the environment on the shared host through the token abilities and switch tokens that already exist.

**Tech Stack:** PHP 8.3 / Laravel 12 / Sanctum 4 / Eloquent / PHPUnit 11 (sqlite in-memory, `CACHE_STORE=array`, `SESSION_DRIVER=array`, `MAIL_MAILER=array`).

**Spec:** `docs/superpowers/specs/2026-09-01-shared-app-host-tenancy-design.md` — read it first. This plan implements §5.2 and the API halves of §5.6, §6, §7, §8, §9.

## Global Constraints

- No new Composer dependencies. Do not change `composer.json`.
- Every task ends with `vendor/bin/pint --dirty --format agent` and the named tests green.
- Shared hosts default: `app.getkursa.space,www.app.getkursa.space`; canonical `app.getkursa.space`; subdomain base `getkursa.space`; legacy bases `csl-brands.com`, `cfpcsl.com`.
- Guard error code is exactly `environment_required` (HTTP 403). Login refusal code is exactly `no_environment` (HTTP 403). Checkout not ready code is exactly `checkout_not_ready` (HTTP 409). Clients branch on `code`, never on `message`.
- Client-supplied environment identifiers (`environment_id` input, `X-Environment-Id`, `?domain=`) are honoured only on unauthenticated public endpoints. Authenticated requests use the binding (token ability or login session).
- `primary_domain` keeps its meaning. Never write the shared host into `primary_domain` or `additional_domains`.
- The domain probe only sets `domain_verified_at`; only the admin endpoint clears it.
- Migrations are wrapped in `App\Helpers\MigrationHelper::columnExists()` like their siblings.
- Commit messages: imperative subject, `type(scope): effect`; body explains why. Sign every commit with the trailer lines below.

```
Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01GdsYLasvsZ1zMvK7aKzVqP
```

## File Structure

| File | Responsibility |
|---|---|
| `config/tenancy.php` (create) | Shared hosts, subdomain base, aliases, guard mode, probe settings, onboarding token TTL. |
| `database/migrations/2026_09_01_100000_add_domain_verified_at_to_environments_table.php` (create) | The liveness column and its backfill. |
| `app/Models/Environment.php` (modify) | `findActiveByDomain()`, `findActive()`, `isDomainLive()`, precedence fix, lowercase cache keys. |
| `app/Support/Tenancy/EnvironmentContext.php` (create) | Value object: environment, source, host. |
| `app/Support/Tenancy/EnvironmentResolver.php` (create) | The single request → environment resolution. |
| `app/Http/Middleware/DetectEnvironment.php` (modify) | Calls the resolver; keeps side effects; stamps `source` and `domain_verified_at`. |
| `app/Http/Middleware/EnsureEnvironmentResolved.php` (create) | Fail-closed guard, alias `environment.required`. |
| `bootstrap/app.php` (modify) | Alias registration. |
| `routes/api.php`, `routes/learner.php`, `routes/environment-auth.php` (modify) | Guard applied to tenant groups; new routes. |
| `app/Support/Tenancy/MembershipList.php` (create) | Owned ∪ member environments in the `/user/environments` shape. |
| `app/Support/Tenancy/LoginBinding.php`, `LoginBindingResolver.php`, `NoEnvironmentException.php` (create) | Which environment a login binds to. |
| `app/Http/Controllers/Api/TokenController.php`, `SessionAuthController.php`, `EnvironmentMembershipController.php` (modify) | Use the binding resolver / membership list. |
| `app/Support/Tenancy/TenantUrl.php` (create) | Effective base URL and link builder. |
| `app/Support/Tenancy/TenantDomain.php` (create) | Subdomain composition and validation. |
| `app/Support/Tenancy/SwitchTokenIssuer.php` (create) | One-time switch tokens for switching and onboarding. |
| `app/Http/Controllers/Api/Auth/AcademySwitchController.php` (modify) | Issuer + `TenantUrl`; `is_account_setup` in the exchange response. |
| `app/Services/Licensing/LicenceService.php`, `app/Http/Controllers/Api/LicenceController.php` (modify) | `redirect_url` on onboarding; `sign-in-link`; `TenantDomain::compose()`. |
| `app/Http/Controllers/Api/Onboarding/OnboardingController.php`, `StandaloneOnboardingController.php`, `DemoOnboardingController.php`, `SupportedOnboardingController.php`, `app/Http/Controllers/Api/ValidationController.php` (modify) | Subdomain base from `TenantDomain`. |
| Mail/Notification/Provider/Support/Blade link builders listed in Task 9 (modify) | `TenantUrl::to()`. |
| `app/Support/Tenancy/DomainProbe.php`, `DnsHttpDomainProbe.php` (create); `app/Console/Commands/VerifyEnvironmentDomains.php` (create); `routes/console.php` (modify); `app/Http/Controllers/Api/Admin/DomainVerificationController.php` (create); `app/Providers/AppServiceProvider.php` (modify) | Domain verification. |
| `app/Http/Controllers/Api/BrandingController.php`, `EnvironmentController.php`, `SubscriptionController.php`, `ProductLandingPageController.php`, `LandingPagePopupController.php`, `LegalPageController.php`, `ThirdPartyServiceController.php`, `app/Http/Middleware/BrandingMiddleware.php`, `app/Traits/BelongsToEnvironment.php` (modify) | Resolver + explicit identifier on public endpoints; no `LIKE`; no fallback tenant. |
| `app/Support/TenantDomainRegistry.php`, `app/Http/Controllers/Api/CustomerController.php` (modify) | Shared hosts known; `domain_verified_at` in admin payloads. |

---

### Task 1: Tenancy config, liveness column, and safe environment finders

**Files:**
- Create: `config/tenancy.php`
- Create: `database/migrations/2026_09_01_100000_add_domain_verified_at_to_environments_table.php`
- Modify: `app/Models/Environment.php:40-128`
- Test: `tests/Feature/Tenancy/EnvironmentFindersTest.php`

**Interfaces:**
- Produces: `config('tenancy.shared_hosts')`, `config('tenancy.shared_host')`, `config('tenancy.subdomain_base')`, `config('tenancy.legacy_subdomain_bases')`, `config('tenancy.host_aliases')`, `config('tenancy.environment_guard')`, `config('tenancy.domain_probe.http_timeout_seconds')`, `config('tenancy.onboarding_switch_token_ttl_seconds')`.
- Produces: `Environment::findActiveByDomain(string $domain): ?Environment`, `Environment::findActive(int $id): ?Environment`, `Environment::isDomainLive(): bool`, column `environments.domain_verified_at` (nullable timestamp, cast `datetime`).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Tenancy/EnvironmentFindersTest.php`:

```php
<?php

namespace Tests\Feature\Tenancy;

use App\Models\Environment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * findByDomain() used to evaluate as
 * (primary_domain = d) OR (additional_domains ⊇ d AND is_active), so an
 * inactive environment still matched on its primary domain. The resolver and
 * every public endpoint go through findActiveByDomain(), which fixes the
 * precedence, lowercases the lookup, and is what findByDomain() now delegates to.
 */
class EnvironmentFindersTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenancy_config_carries_the_shared_hosts_and_subdomain_base(): void
    {
        $this->assertSame(['app.getkursa.space', 'www.app.getkursa.space'], config('tenancy.shared_hosts'));
        $this->assertSame('app.getkursa.space', config('tenancy.shared_host'));
        $this->assertSame('getkursa.space', config('tenancy.subdomain_base'));
        $this->assertSame(['csl-brands.com', 'cfpcsl.com'], config('tenancy.legacy_subdomain_bases'));
        $this->assertSame('log', config('tenancy.environment_guard'));
    }

    public function test_an_inactive_environment_does_not_match_on_its_primary_domain(): void
    {
        Environment::factory()->create(['primary_domain' => 'acme.test', 'is_active' => false]);

        $this->assertNull(Environment::findActiveByDomain('acme.test'));
        $this->assertNull(Environment::findByDomain('acme.test'));
    }

    public function test_an_active_environment_matches_case_insensitively_on_primary_and_additional_domains(): void
    {
        $environment = Environment::factory()->create([
            'primary_domain' => 'acme.test',
            'additional_domains' => ['learn.acme.test'],
        ]);

        $this->assertSame($environment->id, Environment::findActiveByDomain('ACME.test')?->id);
        $this->assertSame($environment->id, Environment::findActiveByDomain('learn.acme.test')?->id);
    }

    public function test_find_active_returns_null_for_an_inactive_environment(): void
    {
        $environment = Environment::factory()->create(['is_active' => false]);

        $this->assertNull(Environment::findActive($environment->id));
        $this->assertNull(Environment::findActive(999_999));
    }

    public function test_a_new_environment_has_no_live_domain_until_verified(): void
    {
        $environment = Environment::factory()->create();

        $this->assertNull($environment->domain_verified_at);
        $this->assertFalse($environment->isDomainLive());

        $environment->forceFill(['domain_verified_at' => now()])->save();

        $this->assertTrue($environment->fresh()->isDomainLive());
    }

    public function test_saving_a_domain_change_forgets_the_lowercased_cache_key(): void
    {
        $environment = Environment::factory()->create(['primary_domain' => 'Acme.test']);
        $this->assertSame($environment->id, Environment::findActiveByDomain('acme.test')?->id);

        $environment->update(['primary_domain' => 'bravo.test']);

        $this->assertNull(Environment::findActiveByDomain('acme.test'));
        $this->assertSame($environment->id, Environment::findActiveByDomain('bravo.test')?->id);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Tenancy/EnvironmentFindersTest.php`
Expected: FAIL — `config('tenancy.shared_hosts')` is null; `findActiveByDomain` undefined.

- [ ] **Step 3: Create `config/tenancy.php`**

```php
<?php

/*
|--------------------------------------------------------------------------
| Tenancy
|--------------------------------------------------------------------------
|
| A tenant is normally identified by the hostname the browser is on. The
| shared hosts below serve every tenant instead: there the environment comes
| from the login binding (token ability or login session), and links built
| for an environment whose own domain is not live yet point at the shared
| host. See docs/superpowers/specs/2026-09-01-shared-app-host-tenancy-design.md.
*/

return [
    // Hosts that serve every tenant. Lowercase, no scheme, optional port.
    'shared_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('TENANCY_SHARED_HOSTS', 'app.getkursa.space,www.app.getkursa.space'))
    ))),

    // The canonical shared host used when building links.
    'shared_host' => env('TENANCY_SHARED_HOST', 'app.getkursa.space'),

    // Base under which new KURSA subdomains are composed.
    'subdomain_base' => env('TENANCY_SUBDOMAIN_BASE', 'getkursa.space'),

    // Bases still recognised as "one of ours" for environments created earlier.
    'legacy_subdomain_bases' => ['csl-brands.com', 'cfpcsl.com'],

    // Platform frontends that resolve to a fixed environment. Exact match on the
    // request host; replaces the substring loop DetectEnvironment used to carry.
    'host_aliases' => [
        'csl-certification.vercel.app' => 'learning.csl-brands.com',
        'learning.cfpcsl.com' => 'learning.csl-brands.com',
        'csl-certification-git-develop-kevin997s-projects.vercel.app' => 'learning.csl-brands.com',
    ],

    // 'log' records would-be refusals and lets the request through;
    // 'enforce' returns 403 { code: environment_required }.
    'environment_guard' => env('TENANCY_ENVIRONMENT_GUARD', 'log'),

    'domain_probe' => [
        'http_timeout_seconds' => (int) env('TENANCY_DOMAIN_PROBE_TIMEOUT', 5),
    ],

    // Lifetime of the one-time sign-in token minted at onboarding.
    'onboarding_switch_token_ttl_seconds' => (int) env('TENANCY_ONBOARDING_TOKEN_TTL', 300),
];
```

- [ ] **Step 4: Create the migration**

Run: `php artisan make:migration add_domain_verified_at_to_environments_table --table=environments --no-interaction`, then rename the generated file to `database/migrations/2026_09_01_100000_add_domain_verified_at_to_environments_table.php` and replace its content with:

```php
<?php

use App\Helpers\MigrationHelper;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Records when an environment's own domain was confirmed reachable. Links are
 * built for the tenant domain only when this is set; otherwise they point at
 * the shared host (config tenancy.shared_host).
 *
 * Existing rows are backfilled as verified: they are reachable today by
 * definition, and a null would silently move every existing tenant's emails to
 * the shared host on deploy.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! MigrationHelper::columnExists('environments', 'domain_verified_at')) {
            Schema::table('environments', function (Blueprint $table) {
                $table->timestamp('domain_verified_at')->nullable()->index()->after('is_active');
            });
        }

        DB::table('environments')
            ->whereNull('domain_verified_at')
            ->update(['domain_verified_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        if (MigrationHelper::columnExists('environments', 'domain_verified_at')) {
            Schema::table('environments', function (Blueprint $table) {
                $table->dropColumn('domain_verified_at');
            });
        }
    }
};
```

- [ ] **Step 5: Update the model**

In `app/Models/Environment.php`:

1. Add `'domain_verified_at',` to `$fillable` after `'is_active',`.
2. Add `'domain_verified_at' => 'datetime',` to `$casts`.
3. Replace the `$clearDomainCaches` closure and the two `Cache::forget` calls inside `boot()` so every key is lowercased:

```php
        $clearDomainCaches = function (Environment $env): void {
            foreach ($env->getAllDomains() as $domain) {
                Cache::forget('env_by_domain:'.strtolower((string) $domain));
            }
        };

        static::saved(function (Environment $env) use ($clearDomainCaches): void {
            $clearDomainCaches($env);

            // Also clear any previously registered domains that may have changed.
            if ($env->isDirty('primary_domain') && $env->getOriginal('primary_domain')) {
                Cache::forget('env_by_domain:'.strtolower((string) $env->getOriginal('primary_domain')));
            }

            if ($env->isDirty('additional_domains')) {
                $old = $env->getOriginal('additional_domains') ?? [];
                if (is_string($old)) {
                    $old = json_decode($old, true) ?? [];
                }
                foreach ((array) $old as $domain) {
                    Cache::forget('env_by_domain:'.strtolower((string) $domain));
                }
            }
        });
```

4. Replace `findByDomain()` with the pair below (keep `resolveByIdentifier()` as is):

```php
    /**
     * Resolve an active environment by any of its registered domains.
     *
     * The is_active predicate wraps the whole domain disjunction. The previous
     * query evaluated as (primary_domain = d) OR (additional ⊇ d AND active),
     * so an inactive environment still matched on its primary domain.
     */
    public static function findActiveByDomain(string $domain): ?self
    {
        $domain = strtolower(trim($domain));

        if ($domain === '') {
            return null;
        }

        return Cache::remember("env_by_domain:{$domain}", self::DOMAIN_CACHE_TTL, function () use ($domain): ?self {
            return static::where('is_active', true)
                ->where(function ($query) use ($domain) {
                    $query->whereRaw('LOWER(primary_domain) = ?', [$domain])
                        ->orWhereJsonContains('additional_domains', $domain);
                })
                ->first();
        });
    }

    /**
     * @deprecated Kept for existing callers; delegates to findActiveByDomain().
     */
    public static function findByDomain(string $domain): ?self
    {
        return static::findActiveByDomain($domain);
    }

    public static function findActive(int $id): ?self
    {
        return static::where('id', $id)->where('is_active', true)->first();
    }

    /**
     * Whether the environment's own domain has been confirmed reachable. Links
     * go to the shared host until then (see App\Support\Tenancy\TenantUrl).
     */
    public function isDomainLive(): bool
    {
        return $this->domain_verified_at !== null;
    }
```

- [ ] **Step 6: Run the test to verify it passes**

Run: `php artisan test --compact tests/Feature/Tenancy/EnvironmentFindersTest.php tests/Feature/Api/EnvironmentResolutionTest.php tests/Unit/Services/EnvironmentPaymentConfigServiceTest.php`
Expected: PASS

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add config/tenancy.php database/migrations/2026_09_01_100000_add_domain_verified_at_to_environments_table.php app/Models/Environment.php tests/Feature/Tenancy/EnvironmentFindersTest.php
git commit -m "feat(tenancy): add tenancy config, domain_verified_at, and active-only domain finders

findByDomain() evaluated as (primary = d) OR (additional ⊇ d AND active), so
an inactive environment still resolved on its primary domain. findActiveByDomain
fixes the precedence and lowercases the lookup; findByDomain delegates to it.
domain_verified_at records when a tenant's own domain is reachable; existing
rows are backfilled so no live tenant's links change on deploy.

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01GdsYLasvsZ1zMvK7aKzVqP"
```

---

### Task 2: `EnvironmentResolver`, `EnvironmentContext`, and `DetectEnvironment` on top of them

**Files:**
- Create: `app/Support/Tenancy/EnvironmentContext.php`
- Create: `app/Support/Tenancy/EnvironmentResolver.php`
- Modify: `app/Http/Middleware/DetectEnvironment.php:19-181`
- Test: `tests/Feature/Middleware/DetectEnvironmentTest.php`

**Interfaces:**
- Consumes: `Environment::findActiveByDomain()`, `Environment::findActive()`, `config('tenancy.*')` from Task 1.
- Produces:
  - `EnvironmentContext { ?Environment $environment; string $source ('host'|'binding'|'none'); string $host }`, `EnvironmentContext::none(string $host)`, `->resolved(): bool`.
  - `EnvironmentResolver::resolve(Request): EnvironmentContext`, `->frontendHost(Request): string`, `->isSharedHost(string): bool`, `->explicitEnvironment(Request): ?Environment`, `EnvironmentResolver::environmentIdFromAbilities(array): ?int`.
  - Request attribute `tenancy.context` holding the `EnvironmentContext`, set by `DetectEnvironment` on every request.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Middleware/DetectEnvironmentTest.php`:

```php
<?php

namespace Tests\Feature\Middleware;

use App\Models\Environment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * DetectEnvironment is global middleware and had no test. The echo route below
 * returns a JSON body with no `environment` key (unlike /api/health, whose body
 * already carries one), so whatever the middleware stamps on it is exactly what
 * it resolved.
 */
class DetectEnvironmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware('api')->get('/api/_echo', fn () => response()->json(['ok' => true]));
    }

    public function test_a_tenant_host_resolves_by_the_frontend_domain_header(): void
    {
        $environment = Environment::factory()->create(['primary_domain' => 'acme.test']);

        $response = $this->getJson('/api/_echo', ['X-Frontend-Domain' => 'ACME.test']);

        $response->assertOk()->assertJsonPath('environment.id', $environment->id)
            ->assertJsonPath('environment.source', 'host');
    }

    public function test_an_inactive_environment_is_not_resolved(): void
    {
        Environment::factory()->create(['primary_domain' => 'acme.test', 'is_active' => false]);

        $this->getJson('/api/_echo', ['X-Frontend-Domain' => 'acme.test'])
            ->assertOk()->assertJsonPath('environment.source', 'none');
    }

    public function test_a_host_alias_resolves_by_exact_match_only(): void
    {
        $environment = Environment::factory()->create(['primary_domain' => 'learning.csl-brands.com']);

        $this->getJson('/api/_echo', ['X-Frontend-Domain' => 'csl-certification.vercel.app'])
            ->assertJsonPath('environment.id', $environment->id);

        // The old substring loop matched any header that contained (or was contained in) an alias.
        $this->getJson('/api/_echo', ['X-Frontend-Domain' => 'vercel.app'])
            ->assertJsonPath('environment.source', 'none');
    }

    public function test_the_shared_host_resolves_from_the_bearer_token_binding(): void
    {
        $environment = Environment::factory()->create();
        $user = User::factory()->create();
        $token = $user->createToken('web-client', ['environment_id:'.$environment->id])->plainTextToken;

        $this->getJson('/api/_echo', [
            'X-Frontend-Domain' => 'app.getkursa.space',
            'Authorization' => 'Bearer '.$token,
        ])->assertJsonPath('environment.id', $environment->id)
            ->assertJsonPath('environment.source', 'binding');
    }

    public function test_the_shared_host_ignores_a_binding_to_an_inactive_environment(): void
    {
        $environment = Environment::factory()->create(['is_active' => false]);
        $user = User::factory()->create();
        $token = $user->createToken('web-client', ['environment_id:'.$environment->id])->plainTextToken;

        $this->getJson('/api/_echo', [
            'X-Frontend-Domain' => 'app.getkursa.space',
            'Authorization' => 'Bearer '.$token,
        ])->assertJsonPath('environment.source', 'none');
    }

    public function test_the_shared_host_without_a_principal_resolves_nothing_even_with_a_client_supplied_id(): void
    {
        $environment = Environment::factory()->create();

        $this->getJson('/api/_echo?environment_id='.$environment->id, [
            'X-Frontend-Domain' => 'www.app.getkursa.space',
            'X-Environment-Id' => (string) $environment->id,
        ])->assertJsonPath('environment.source', 'none');
    }

    public function test_the_response_carries_domain_verified_at(): void
    {
        $environment = Environment::factory()->create(['primary_domain' => 'acme.test']);

        $this->getJson('/api/_echo', ['X-Frontend-Domain' => 'acme.test'])
            ->assertJsonPath('environment.domain_verified_at', null);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Middleware/DetectEnvironmentTest.php`
Expected: FAIL — no `environment.source` key; alias test matches `vercel.app` by substring.

- [ ] **Step 3: Create `EnvironmentContext`**

`app/Support/Tenancy/EnvironmentContext.php`:

```php
<?php

namespace App\Support\Tenancy;

use App\Models\Environment;

/**
 * The outcome of resolving the environment for one request.
 *
 * `source` says where it came from: the request host (tenant host), the
 * authenticated principal's binding (shared host), or nothing.
 */
final class EnvironmentContext
{
    public const SOURCE_HOST = 'host';

    public const SOURCE_BINDING = 'binding';

    public const SOURCE_NONE = 'none';

    public function __construct(
        public readonly ?Environment $environment,
        public readonly string $source,
        public readonly string $host,
    ) {
    }

    public static function none(string $host): self
    {
        return new self(null, self::SOURCE_NONE, $host);
    }

    public function resolved(): bool
    {
        return $this->environment !== null;
    }
}
```

- [ ] **Step 4: Create `EnvironmentResolver`**

`app/Support/Tenancy/EnvironmentResolver.php`:

```php
<?php

namespace App\Support\Tenancy;

use App\Models\Environment;
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
     * @param  array<int, string>  $abilities
     */
    public static function environmentIdFromAbilities(array $abilities): ?int
    {
        foreach ($abilities as $ability) {
            if (is_string($ability) && str_starts_with($ability, self::ABILITY_PREFIX)) {
                $id = substr($ability, strlen(self::ABILITY_PREFIX));

                return ctype_digit($id) ? (int) $id : null;
            }
        }

        return null;
    }

    /**
     * The sanctum guard is asked explicitly: the default guard is `web`
     * (config/auth.php), which cannot see a bearer token from global middleware.
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
            return Environment::findActive((int) session('current_environment_id'));
        }

        return null;
    }
}
```


- [ ] **Step 5: Rewrite `DetectEnvironment::handle()`**

Replace lines 19-103 of `app/Http/Middleware/DetectEnvironment.php` (everything from the start of `handle()` through the `if (!$environment) { Log::warning(...) } else { ... }` block) with:

```php
    public function __construct(private readonly EnvironmentResolver $resolver)
    {
    }

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $context = $this->resolver->resolve($request);
        $request->attributes->set(EnvironmentResolver::REQUEST_ATTRIBUTE, $context);

        $environment = $context->environment;
        $domain = $context->host;
        $frontendDomainHeader = $request->header('X-Frontend-Domain');

        if (! $environment) {
            Log::debug('DetectEnvironment: No environment resolved for host', [
                'detected_domain' => $domain,
                'frontend_header' => $frontendDomainHeader,
            ]);
            // Do NOT fall back to an arbitrary environment — that silently leaks another
            // tenant's data. Routes that require a resolved environment carry the
            // `environment.required` middleware (EnsureEnvironmentResolved).
        }
```

Add `use App\Support\Tenancy\EnvironmentResolver;` to the imports and drop the now-unused `use Illuminate\Support\Facades\Cache;` only if nothing else in the file uses it (`touchLastActive()` does, so keep it). Keep lines 105-150 (view share, request merge, session write, auto-attach, heartbeat, credentials) unchanged.

In the response block, replace the `$environmentData` array with:

```php
                $environmentData = $environment ? [
                    'id' => $environment->id,
                    'is_demo' => $environment->is_demo,
                    'name' => $environment->name,
                    'primary_domain' => $environment->primary_domain,
                    'domain_verified_at' => $environment->domain_verified_at?->toIso8601String(),
                    'source' => $context->source,
                    'detected_domain' => $domain,
                    'header_domain' => $frontendDomainHeader,
                ] : [
                    'message' => 'No environment found',
                    'source' => $context->source,
                    'detected_domain' => $domain,
                    'header_domain' => $frontendDomainHeader,
                ];
```

- [ ] **Step 6: Run the tests**

Run: `php artisan test --compact tests/Feature/Middleware/DetectEnvironmentTest.php tests/Feature/PublicBrandingTest.php tests/Feature/PublicEnvironmentApiTest.php tests/Feature/Storefront`
Expected: PASS

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/Tenancy/EnvironmentContext.php app/Support/Tenancy/EnvironmentResolver.php app/Http/Middleware/DetectEnvironment.php tests/Feature/Middleware/DetectEnvironmentTest.php
git commit -m "feat(tenancy): resolve the environment through one resolver, with a shared-host binding

On a shared host (app.getkursa.space) the hostname identifies nobody, so the
environment is the authenticated principal's binding: the environment_id
token ability minted at login or switch. Tenant hosts keep resolving by host.
Host aliases match exactly instead of by substring. DetectEnvironment now
stamps the source and domain_verified_at on responses.

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01GdsYLasvsZ1zMvK7aKzVqP"
```

---

### Task 3: `EnsureEnvironmentResolved` guard on every tenant route

**Files:**
- Create: `app/Http/Middleware/EnsureEnvironmentResolved.php`
- Modify: `bootstrap/app.php:71-79` (alias), `routes/api.php`, `routes/learner.php`, `routes/environment-auth.php`
- Test: `tests/Feature/Middleware/EnsureEnvironmentResolvedTest.php`

**Interfaces:**
- Consumes: request attribute `tenancy.context` from Task 2.
- Produces: middleware alias `environment.required`; `EnsureEnvironmentResolved::CODE = 'environment_required'`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Middleware/EnsureEnvironmentResolvedTest.php`:

```php
<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\EnsureEnvironmentResolved;
use App\Models\Environment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Tenant routes used to run unscoped when no environment resolved: the global
 * scope simply did not apply. The guard turns that into a refusal, after a
 * log-only phase that surfaces routes which legitimately run without one.
 */
class EnsureEnvironmentResolvedTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Route::middleware(['api', 'auth:sanctum', 'environment.required'])
            ->get('/api/_guarded', fn () => response()->json(['ok' => true]));
    }

    private function bearer(User $user, array $abilities = []): array
    {
        return ['Authorization' => 'Bearer '.$user->createToken('t', $abilities)->plainTextToken];
    }

    public function test_enforce_mode_refuses_with_the_stable_code_when_nothing_resolved(): void
    {
        config(['tenancy.environment_guard' => 'enforce']);
        $user = User::factory()->create();

        $this->getJson('/api/_guarded', ['X-Frontend-Domain' => 'app.getkursa.space'] + $this->bearer($user))
            ->assertForbidden()
            ->assertJsonPath('code', EnsureEnvironmentResolved::CODE);
    }

    public function test_enforce_mode_passes_a_bound_request_on_the_shared_host(): void
    {
        config(['tenancy.environment_guard' => 'enforce']);
        $environment = Environment::factory()->create();
        $user = User::factory()->create();

        $this->getJson('/api/_guarded', ['X-Frontend-Domain' => 'app.getkursa.space']
            + $this->bearer($user, ['environment_id:'.$environment->id]))
            ->assertOk();
    }

    public function test_enforce_mode_passes_a_tenant_host_request(): void
    {
        config(['tenancy.environment_guard' => 'enforce']);
        Environment::factory()->create(['primary_domain' => 'acme.test']);
        $user = User::factory()->create();

        $this->getJson('/api/_guarded', ['X-Frontend-Domain' => 'acme.test'] + $this->bearer($user))
            ->assertOk();
    }

    public function test_platform_staff_pass_without_a_binding(): void
    {
        config(['tenancy.environment_guard' => 'enforce']);
        $admin = User::factory()->create(['role' => 'super_admin']);

        $this->getJson('/api/_guarded', ['X-Frontend-Domain' => 'manager.getkursa.space'] + $this->bearer($admin))
            ->assertOk();
    }

    public function test_log_mode_lets_the_request_through_and_logs(): void
    {
        config(['tenancy.environment_guard' => 'log']);
        Log::spy();
        $user = User::factory()->create();

        $this->getJson('/api/_guarded', ['X-Frontend-Domain' => 'app.getkursa.space'] + $this->bearer($user))
            ->assertOk();

        Log::shouldHaveReceived('warning')
            ->withArgs(fn ($message) => $message === 'tenancy.environment_required')
            ->once();
    }

    /**
     * Every auth:sanctum route either carries the guard or is on the explicit
     * list of identity/membership/platform routes that run without a binding.
     */
    public function test_every_authenticated_route_is_guarded_or_exempt(): void
    {
        $exemptUris = [
            'api/user', 'api/session/user', 'api/user/environments', 'api/environments/user',
            'api/environments/{id}/join', 'api/environments/{id}/leave',
            'api/auth/academy-switch-token', 'api/session/logout', 'api/session/marketplace-token',
            'api/tokens', 'api/logout', 'api/broadcasting/auth', 'api/environment-users/setup-account',
            'api/admin/sales/user', 'api/admin/sales/logout', 'api/_guarded',
        ];
        $exemptPrefixes = ['api/admin/'];
        $missing = [];

        foreach (Route::getRoutes() as $route) {
            $middleware = $route->gatherMiddleware();

            if (! in_array('auth:sanctum', $middleware, true)) {
                continue;
            }

            $uri = $route->uri();
            $exempt = in_array($uri, $exemptUris, true)
                || collect($exemptPrefixes)->contains(fn ($p) => str_starts_with($uri, $p));

            if (! $exempt && ! in_array('environment.required', $middleware, true)) {
                $missing[] = implode('|', $route->methods()).' '.$uri;
            }
        }

        $this->assertSame([], $missing, "auth:sanctum routes missing environment.required:\n".implode("\n", $missing));
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Middleware/EnsureEnvironmentResolvedTest.php`
Expected: FAIL — alias `environment.required` not registered (BindingResolutionException), then the route walk lists every unguarded group.

- [ ] **Step 3: Create the middleware**

`app/Http/Middleware/EnsureEnvironmentResolved.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Support\Tenancy\EnvironmentContext;
use App\Support\Tenancy\EnvironmentResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant routes must know which environment they act on. Without this guard an
 * unresolved request ran with no environment scope at all.
 *
 * Platform staff (admin, super_admin, sales_agent) work from hosts that are not
 * tenants and legitimately carry no binding.
 */
class EnsureEnvironmentResolved
{
    public const CODE = 'environment_required';

    private const PLATFORM_ROLES = [
        UserRole::ADMIN->value,
        UserRole::SUPER_ADMIN->value,
        UserRole::SALES_AGENT->value,
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $context = $request->attributes->get(EnvironmentResolver::REQUEST_ATTRIBUTE);

        if ($context instanceof EnvironmentContext && $context->resolved()) {
            return $next($request);
        }

        $user = $request->user();
        $role = $user?->role instanceof UserRole ? $user->role->value : $user?->role;

        if (is_string($role) && in_array($role, self::PLATFORM_ROLES, true)) {
            return $next($request);
        }

        if (config('tenancy.environment_guard') !== 'enforce') {
            Log::warning('tenancy.environment_required', [
                'method' => $request->method(),
                'route' => $request->path(),
                'host' => $context instanceof EnvironmentContext ? $context->host : null,
                'user_id' => $user?->id,
            ]);

            return $next($request);
        }

        return response()->json([
            'code' => self::CODE,
            'message' => 'No academy selected. Sign in to an academy or open it from its own address.',
        ], 403);
    }
}
```

- [ ] **Step 4: Register the alias**

In `bootstrap/app.php`, add `use App\Http\Middleware\EnsureEnvironmentResolved;` and inside `$middleware->alias([...])` add:

```php
            // Tenant routes refuse (or, in log mode, log) when no environment resolved.
            'environment.required' => EnsureEnvironmentResolved::class,
```

- [ ] **Step 5: Apply the guard to the tenant groups**

In `routes/api.php`, change each `Route::middleware('auth:sanctum')->group(` and `Route::middleware(['auth:sanctum'])->...->group(` to `Route::middleware(['auth:sanctum', 'environment.required'])->...->group(` **except** these, which stay as they are:

- line 362 group (`/environments/{id}/join`, `/leave`, `/user/environments`)
- line 369 group (`/auth/academy-switch-token`)
- line 1215 `prefix('admin')` group
- the single routes: `/user` (291), `/session/user` (332), `/session/logout`, `/session/marketplace-token`, `DELETE /tokens`, `/admin/sales/user`, `/admin/sales/logout`, `/broadcasting/auth` (123)

Single tenant routes with an inline `->middleware('auth:sanctum')` (for example `routes/api.php:956-957`, `1014`, `1100`) become `->middleware(['auth:sanctum', 'environment.required'])`. Inside the big group starting at line 461, the nested groups at 752 and 842 inherit the guard from the parent; leave them. Move `Route::put('/environment-users/setup-account', ...)` (line 1063) out of its guarded group into the unguarded membership group at line 362 so the route walk exempts it by URI.

Apply the same change to the `auth:sanctum` group in `routes/learner.php:34` and to any `auth:sanctum` route in `routes/environment-auth.php`.

Run `php artisan route:list --except-vendor | grep -c environment.required` and confirm the count is non-zero, then run the route-walk test and fix every route it lists until it passes.

- [ ] **Step 6: Run the tests**

Run: `php artisan test --compact tests/Feature/Middleware/EnsureEnvironmentResolvedTest.php tests/Feature/Onboarding/ValidateDomainTest.php`
Expected: PASS (including `test_every_route_middleware_alias_is_registered`).

- [ ] **Step 7: Run the whole suite once in log mode**

Run: `php artisan test --compact`
Expected: the same pass/fail set as before this task (the guard is in `log` mode by default, so nothing is refused). Record the baseline count in the task report.

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Middleware/EnsureEnvironmentResolved.php bootstrap/app.php routes/api.php routes/learner.php routes/environment-auth.php tests/Feature/Middleware/EnsureEnvironmentResolvedTest.php
git commit -m "feat(tenancy): guard tenant routes with environment.required, log-only by default

A request whose host resolved to no environment ran with no environment scope
at all, so any unknown host (Vercel previews today, the shared host tomorrow)
read every tenant's rows. Tenant route groups now carry the guard; identity,
membership, switch and admin routes are exempt and a route-walk test keeps the
list honest. TENANCY_ENVIRONMENT_GUARD=enforce turns the log into a 403.

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01GdsYLasvsZ1zMvK7aKzVqP"
```

---

### Task 4: Login binding on both login endpoints

**Files:**
- Create: `app/Support/Tenancy/MembershipList.php`, `app/Support/Tenancy/LoginBinding.php`, `app/Support/Tenancy/LoginBindingResolver.php`, `app/Support/Tenancy/NoEnvironmentException.php`
- Modify: `app/Http/Controllers/Api/TokenController.php:141-176`, `app/Http/Controllers/Api/SessionAuthController.php:142-200`, `app/Http/Controllers/Api/EnvironmentMembershipController.php:211-267`
- Test: `tests/Feature/Auth/LoginBindingTest.php`

**Interfaces:**
- Consumes: `EnvironmentResolver::frontendHost()`, `isSharedHost()`, `Environment::findActiveByDomain()`.
- Produces:
  - `MembershipList::for(User $user): \Illuminate\Support\Collection` of arrays `{environment: Environment, role: string, joined_at, is_owner: bool, branding: ?array}` (identical to today's `GET /user/environments` items).
  - `LoginBinding { ?int $environmentId; bool $requiresSelection; array $environments }`.
  - `LoginBindingResolver::resolve(User $user, ?int $requestedEnvironmentId, string $host): ?LoginBinding` — `null` means "no shared-host or host rule applies; keep the legacy branch". Throws `NoEnvironmentException` (code `no_environment`).
  - Login responses gain `requires_environment_selection: bool` and, when true, `environments: [...]`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Auth/LoginBindingTest.php`:

```php
<?php

namespace Tests\Feature\Auth;

use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * On a tenant host the host decides the environment. On the shared host there
 * is no host to decide, so login binds by membership: one membership binds,
 * several ask the client to choose, none is refused.
 */
class LoginBindingTest extends TestCase
{
    use RefreshDatabase;

    private function login(array $body, string $host): \Illuminate\Testing\TestResponse
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
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Auth/LoginBindingTest.php`
Expected: FAIL — `environment_id` null on the tenant-host case; no `requires_environment_selection` key; no 403 `no_environment`.

- [ ] **Step 3: Create the support classes**

`app/Support/Tenancy/MembershipList.php`:

```php
<?php

namespace App\Support\Tenancy;

use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Every environment a user can enter: the ones they own and the ones they were
 * added to. Same item shape as GET /user/environments, which now uses this.
 */
final class MembershipList
{
    /**
     * @return Collection<int, array{environment: Environment, role: string, joined_at: mixed, is_owner: bool, branding: ?array}>
     */
    public static function for(User $user): Collection
    {
        $brandingOf = static fn (Environment $environment): ?array => $environment->branding ? [
            'logo_path' => $environment->branding->logo_path,
            'favicon_path' => $environment->branding->favicon_path,
            'primary_color' => $environment->branding->primary_color,
        ] : null;

        $owned = Environment::where('owner_id', $user->id)
            ->with('branding')
            ->get()
            ->map(fn (Environment $environment) => [
                'environment' => $environment,
                'role' => 'owner',
                'joined_at' => $environment->created_at,
                'is_owner' => true,
                'branding' => $brandingOf($environment),
            ]);

        $member = EnvironmentUser::where('user_id', $user->id)
            ->with(['environment.branding'])
            ->get()
            ->filter(fn (EnvironmentUser $membership) => $membership->environment !== null)
            ->map(fn (EnvironmentUser $membership) => [
                'environment' => $membership->environment,
                'role' => $membership->role,
                'joined_at' => $membership->joined_at,
                'is_owner' => false,
                'branding' => $brandingOf($membership->environment),
            ]);

        // Both sides are plain arrays; toBase() avoids Eloquent's merge() calling getKey() on them.
        return $owned->toBase()
            ->merge($member->toBase())
            ->unique(fn (array $item) => $item['environment']->id)
            ->values();
    }

    /**
     * @return Collection<int, int> active environment ids
     */
    public static function activeIdsFor(User $user): Collection
    {
        return self::for($user)
            ->filter(fn (array $item) => (bool) $item['environment']->is_active)
            ->map(fn (array $item) => (int) $item['environment']->id)
            ->values();
    }
}
```

`app/Support/Tenancy/LoginBinding.php`:

```php
<?php

namespace App\Support\Tenancy;

final class LoginBinding
{
    /**
     * @param  array<int, array<string, mixed>>  $environments
     */
    public function __construct(
        public readonly ?int $environmentId,
        public readonly bool $requiresSelection = false,
        public readonly array $environments = [],
    ) {
    }

    public static function to(int $environmentId): self
    {
        return new self($environmentId);
    }

    public static function none(): self
    {
        return new self(null);
    }

    /**
     * @param  array<int, array<string, mixed>>  $environments
     */
    public static function select(array $environments): self
    {
        return new self(null, true, $environments);
    }
}
```

`app/Support/Tenancy/NoEnvironmentException.php`:

```php
<?php

namespace App\Support\Tenancy;

use RuntimeException;

/**
 * A user signed in on the shared host but belongs to no environment. Mapped to
 * 403 { code: no_environment } by the login controllers.
 */
final class NoEnvironmentException extends RuntimeException
{
    public const CODE = 'no_environment';

    public static function forUser(): self
    {
        return new self('You are not a member of any academy yet. Create one from the KURSA website.');
    }
}
```

`app/Support/Tenancy/LoginBindingResolver.php`:

```php
<?php

namespace App\Support\Tenancy;

use App\Enums\UserRole;
use App\Models\Environment;
use App\Models\User;

/**
 * Which environment a successful login binds to. Returns null when neither the
 * host nor the shared-host rules apply, so the caller keeps its legacy branch
 * (teacher auto-resolve on session login; no ability on token login).
 */
final class LoginBindingResolver
{
    private const PLATFORM_ROLES = [
        UserRole::ADMIN->value,
        UserRole::SUPER_ADMIN->value,
        UserRole::SALES_AGENT->value,
    ];

    public function __construct(private readonly EnvironmentResolver $resolver)
    {
    }

    /**
     * @throws NoEnvironmentException
     */
    public function resolve(User $user, ?int $requestedEnvironmentId, string $host): ?LoginBinding
    {
        if ($requestedEnvironmentId !== null) {
            // The caller verifies membership exactly as before.
            return LoginBinding::to($requestedEnvironmentId);
        }

        $memberships = MembershipList::activeIdsFor($user);

        if (! $this->resolver->isSharedHost($host)) {
            $hostEnvironment = Environment::findActiveByDomain($host);

            if ($hostEnvironment && $memberships->contains($hostEnvironment->id)) {
                return LoginBinding::to($hostEnvironment->id);
            }

            return null;
        }

        if ($memberships->count() === 1) {
            return LoginBinding::to($memberships->first());
        }

        if ($memberships->count() > 1) {
            $environments = MembershipList::for($user)
                ->filter(fn (array $item) => (bool) $item['environment']->is_active)
                ->values()
                ->all();

            return LoginBinding::select($environments);
        }

        $role = $user->role instanceof UserRole ? $user->role->value : $user->role;

        if (is_string($role) && in_array($role, self::PLATFORM_ROLES, true)) {
            return LoginBinding::none();
        }

        throw NoEnvironmentException::forUser();
    }
}
```

- [ ] **Step 4: Use `MembershipList` in `EnvironmentMembershipController::myEnvironments()`**

Replace the body of `myEnvironments()` (lines 213-267) with:

```php
        return response()->json([
            'environments' => MembershipList::for($request->user()),
        ], 200);
```

and add `use App\Support\Tenancy\MembershipList;`.

- [ ] **Step 5: Wire `TokenController::createToken()`**

Immediately before `// Check if environment ID is provided and verify user access` (line 141), insert:

```php
        $binding = null;

        try {
            $binding = app(LoginBindingResolver::class)->resolve(
                $user,
                $environmentId ? (int) $environmentId : null,
                app(EnvironmentResolver::class)->frontendHost($request),
            );
        } catch (NoEnvironmentException $e) {
            return response()->json([
                'code' => NoEnvironmentException::CODE,
                'message' => $e->getMessage(),
            ], 403);
        }

        if ($binding?->requiresSelection) {
            $userRoleValue = $user->role instanceof UserRole ? $user->role->value : $user->role;
            $abilities = $userRoleValue ? ['role:'.$userRoleValue] : [];
            $token = $user->createToken($request->device_name, $abilities)->plainTextToken;

            if ($authenticatedViaEnvironment) {
                $this->autoHealPassword($user, $request->password);
            }

            $authContext = EffectiveAuthContext::for($user, null);
            $responseUser = $user->toArray();
            $responseUser['role'] = $authContext['role'];

            return response()->json([
                'token' => $token,
                'user' => $responseUser,
                'environment_id' => null,
                ...$authContext,
                'is_account_setup' => null,
                'requires_environment_selection' => true,
                'environments' => $binding->environments,
            ]);
        }

        if ($binding !== null) {
            $environmentId = $binding->environmentId;
        }
```

Add `'requires_environment_selection' => false,` to the final `response()->json([...])` after `'is_account_setup' => $isAccountSetup,`. Add the imports `use App\Support\Tenancy\EnvironmentResolver; use App\Support\Tenancy\LoginBindingResolver; use App\Support\Tenancy\NoEnvironmentException;`.

- [ ] **Step 6: Wire `SessionAuthController::login()`**

Immediately before `if ($environmentId) {` (line 142), insert:

```php
        $binding = null;

        try {
            $binding = app(LoginBindingResolver::class)->resolve(
                $user,
                $environmentId ? (int) $environmentId : null,
                app(EnvironmentResolver::class)->frontendHost($request),
            );
        } catch (NoEnvironmentException $e) {
            return response()->json([
                'success' => false,
                'code' => NoEnvironmentException::CODE,
                'message' => $e->getMessage(),
            ], 403);
        }

        if ($binding?->requiresSelection) {
            if ($authenticatedViaEnvironment) {
                $this->autoHealPassword($user, $request->password);
            }

            Auth::login($user);
            $request->session()->regenerate();
            $request->session()->forget('current_environment_id');

            $authContext = EffectiveAuthContext::for($user, null);
            $responseUser = $user->toArray();
            $responseUser['role'] = $authContext['role'];

            return response()->json([
                'success' => true,
                'user' => $responseUser,
                'environment_id' => null,
                ...$authContext,
                'is_account_setup' => null,
                'api_token' => null,
                'requires_environment_selection' => true,
                'environments' => $binding->environments,
            ]);
        }

        if ($binding !== null) {
            $environmentId = $binding->environmentId;
        }
```

Add `'requires_environment_selection' => false,` to the final success response after `'api_token' => $apiToken,`. Add the same three imports.

- [ ] **Step 7: Run the tests**

Run: `php artisan test --compact tests/Feature/Auth/LoginBindingTest.php tests/Feature/SessionAuthRoleConsistencyTest.php tests/Feature/Api/EnvironmentResolutionTest.php`
Expected: PASS

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/Tenancy/MembershipList.php app/Support/Tenancy/LoginBinding.php app/Support/Tenancy/LoginBindingResolver.php app/Support/Tenancy/NoEnvironmentException.php app/Http/Controllers/Api/TokenController.php app/Http/Controllers/Api/SessionAuthController.php app/Http/Controllers/Api/EnvironmentMembershipController.php tests/Feature/Auth/LoginBindingTest.php
git commit -m "feat(auth): bind the environment at login by host, or by membership on the shared host

A login that sent no environment_id produced a token with no environment
ability, which the shared host cannot work with. On a tenant host the host
environment now binds when the user is a member. On the shared host a single
membership binds, several return the list with requires_environment_selection,
and none is refused with code no_environment.

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01GdsYLasvsZ1zMvK7aKzVqP"
```

---

### Task 5: `TenantUrl`, `TenantDomain`, and `SwitchTokenIssuer`

**Files:**
- Create: `app/Support/Tenancy/TenantUrl.php`, `app/Support/Tenancy/TenantDomain.php`, `app/Support/Tenancy/SwitchTokenIssuer.php`
- Test: `tests/Unit/Tenancy/TenantUrlTest.php`, `tests/Unit/Tenancy/TenantDomainTest.php`, `tests/Unit/Tenancy/SwitchTokenIssuerTest.php`

**Interfaces:**
- Produces:
  - `TenantUrl::isLive(Environment): bool`, `TenantUrl::base(Environment): string`, `TenantUrl::to(Environment, string $path = '/', array $query = []): string`, `TenantUrl::scheme(string $host): string`.
  - `TenantDomain::base(): string`, `TenantDomain::knownBases(): array`, `TenantDomain::compose(string $type, string $value): string` (throws `RuntimeException` on an invalid label), `TenantDomain::labelOf(string $host): ?string`, `TenantDomain::isValidLabel(string): bool`, `TenantDomain::isReservedLabel(string): bool`, `TenantDomain::isKursaSubdomain(string $host): bool`, `TenantDomain::RESERVED_LABELS`.
  - `SwitchTokenIssuer::issue(User $user, Environment $target, int $ttlSeconds, ?string $sourceEnvironmentId = null): string`, `SwitchTokenIssuer::redirectUrl(Environment $target, string $token): string`, `SwitchTokenIssuer::CACHE_PREFIX = 'academy_switch_token:'`.

- [ ] **Step 1: Write the failing tests**

`tests/Unit/Tenancy/TenantUrlTest.php`:

```php
<?php

namespace Tests\Unit\Tenancy;

use App\Models\Environment;
use App\Support\Tenancy\TenantUrl;
use Tests\TestCase;

class TenantUrlTest extends TestCase
{
    private function environment(array $attributes = []): Environment
    {
        return (new Environment)->forceFill($attributes + [
            'id' => 42,
            'primary_domain' => 'acme.getkursa.space',
            'domain_verified_at' => null,
        ]);
    }

    public function test_a_pending_domain_links_to_the_shared_host_with_the_environment_id(): void
    {
        $url = TenantUrl::to($this->environment(), '/auth/login');

        $this->assertSame('https://app.getkursa.space/auth/login?environment_id=42', $url);
        $this->assertSame('https://app.getkursa.space', TenantUrl::base($this->environment()));
        $this->assertFalse(TenantUrl::isLive($this->environment()));
    }

    public function test_a_live_domain_links_to_the_tenant_domain_without_the_environment_id(): void
    {
        $environment = $this->environment(['domain_verified_at' => now()]);

        $this->assertSame('https://acme.getkursa.space/auth/login', TenantUrl::to($environment, 'auth/login'));
        $this->assertSame('https://acme.getkursa.space', TenantUrl::base($environment));
    }

    public function test_query_parameters_are_appended_and_an_explicit_environment_id_is_not_duplicated(): void
    {
        $url = TenantUrl::to($this->environment(), '/auth/reset-password', ['token' => 'abc', 'environment_id' => 42]);

        $this->assertSame('https://app.getkursa.space/auth/reset-password?token=abc&environment_id=42', $url);
    }

    public function test_a_scheme_in_primary_domain_is_stripped(): void
    {
        $environment = $this->environment(['primary_domain' => 'https://acme.getkursa.space/', 'domain_verified_at' => now()]);

        $this->assertSame('https://acme.getkursa.space/dashboard', TenantUrl::to($environment, '/dashboard'));
    }

    public function test_localhost_hosts_use_http(): void
    {
        $environment = $this->environment(['primary_domain' => 'localhost:3000', 'domain_verified_at' => now()]);

        $this->assertSame('http://localhost:3000', TenantUrl::base($environment));
        $this->assertSame('http', TenantUrl::scheme('127.0.0.1'));
        $this->assertSame('https', TenantUrl::scheme('acme.getkursa.space'));
    }

    public function test_the_shared_host_is_configurable(): void
    {
        config(['tenancy.shared_host' => 'app.example.test']);

        $this->assertSame('https://app.example.test', TenantUrl::base($this->environment()));
    }
}
```

`tests/Unit/Tenancy/TenantDomainTest.php`:

```php
<?php

namespace Tests\Unit\Tenancy;

use App\Support\Tenancy\TenantDomain;
use RuntimeException;
use Tests\TestCase;

class TenantDomainTest extends TestCase
{
    public function test_a_subdomain_label_is_composed_under_the_configured_base(): void
    {
        $this->assertSame('acme.getkursa.space', TenantDomain::compose('subdomain', 'Acme'));
        $this->assertSame('acme.getkursa.space', TenantDomain::compose('subdomain', 'https://acme'));
    }

    public function test_a_fully_qualified_kursa_or_legacy_host_is_reduced_to_its_label_first(): void
    {
        $this->assertSame('acme.getkursa.space', TenantDomain::compose('subdomain', 'acme.getkursa.space'));
        $this->assertSame('acme.getkursa.space', TenantDomain::compose('subdomain', 'acme.csl-brands.com'));
    }

    public function test_the_base_is_configurable(): void
    {
        config(['tenancy.subdomain_base' => 'example.test']);

        $this->assertSame('acme.example.test', TenantDomain::compose('subdomain', 'acme'));
    }

    public function test_a_custom_domain_is_lowercased_and_stripped_of_its_scheme(): void
    {
        $this->assertSame('learn.acme.com', TenantDomain::compose('custom', 'https://Learn.Acme.com/'));
    }

    public function test_invalid_and_reserved_labels_are_rejected(): void
    {
        foreach (['ap', '-acme', 'acme-', 'ac me', 'a.b', 'app', 'www', 'manager'] as $label) {
            try {
                TenantDomain::compose('subdomain', $label);
                $this->fail("Expected {$label} to be rejected");
            } catch (RuntimeException $e) {
                $this->assertStringContainsString('subdomain', strtolower($e->getMessage()));
            }
        }
    }

    public function test_label_of_and_is_kursa_subdomain(): void
    {
        $this->assertSame('acme', TenantDomain::labelOf('acme.getkursa.space'));
        $this->assertSame('acme', TenantDomain::labelOf('acme.cfpcsl.com'));
        $this->assertNull(TenantDomain::labelOf('learn.acme.com'));
        $this->assertTrue(TenantDomain::isKursaSubdomain('acme.csl-brands.com'));
        $this->assertFalse(TenantDomain::isKursaSubdomain('getkursa.space'));
        $this->assertFalse(TenantDomain::isKursaSubdomain('learn.acme.com'));
    }
}
```

`tests/Unit/Tenancy/SwitchTokenIssuerTest.php`:

```php
<?php

namespace Tests\Unit\Tenancy;

use App\Models\Environment;
use App\Models\User;
use App\Support\Tenancy\SwitchTokenIssuer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SwitchTokenIssuerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_stores_the_token_payload_under_the_switch_prefix_and_builds_the_redirect(): void
    {
        $user = User::factory()->create();
        $environment = Environment::factory()->create();
        $issuer = new SwitchTokenIssuer;

        $token = $issuer->issue($user, $environment, 300, '7');

        $this->assertSame(64, strlen($token));
        $this->assertSame([
            'user_id' => $user->id,
            'target_environment_id' => $environment->id,
            'source_environment_id' => '7',
        ], collect(Cache::get(SwitchTokenIssuer::CACHE_PREFIX.$token))->except('created_at')->all());
        $this->assertSame(
            'https://app.getkursa.space/auth/switch?token='.$token.'&environment_id='.$environment->id,
            $issuer->redirectUrl($environment, $token)
        );
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Unit/Tenancy`
Expected: FAIL — classes not found.

- [ ] **Step 3: Create `TenantUrl`**

`app/Support/Tenancy/TenantUrl.php`:

```php
<?php

namespace App\Support\Tenancy;

use App\Models\Environment;

/**
 * Where a link for an environment should point. The tenant's own domain once
 * it is verified live; the shared host, with the environment id carried in the
 * query string, until then. Every outbound link in mail, notifications and
 * redirects is built here so the decision lives in one place.
 */
final class TenantUrl
{
    public static function isLive(Environment $environment): bool
    {
        return $environment->domain_verified_at !== null && filled($environment->primary_domain);
    }

    public static function base(Environment $environment): string
    {
        $host = self::isLive($environment)
            ? self::bareHost((string) $environment->primary_domain)
            : self::bareHost((string) config('tenancy.shared_host', 'app.getkursa.space'));

        return self::scheme($host).'://'.$host;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public static function to(Environment $environment, string $path = '/', array $query = []): string
    {
        if (! self::isLive($environment) && ! array_key_exists('environment_id', $query)) {
            $query['environment_id'] = $environment->id;
        }

        $path = '/'.ltrim($path, '/');
        $url = self::base($environment).($path === '/' ? '' : $path);

        if ($query === []) {
            return $url === self::base($environment) ? $url.'/' : $url;
        }

        return ($url === self::base($environment) ? $url.'/' : $url).'?'.http_build_query($query);
    }

    public static function scheme(string $host): string
    {
        $bare = preg_replace('/:\d+$/', '', strtolower($host));

        if (in_array($bare, ['localhost', '127.0.0.1'], true) || app()->environment('local')) {
            return 'http';
        }

        return 'https';
    }

    private static function bareHost(string $value): string
    {
        $value = preg_replace('#^https?://#i', '', trim($value));

        return strtolower(rtrim((string) $value, '/'));
    }
}
```

Note the root case: `to($env, '/')` yields `https://host/` (with a trailing slash) so it reads as a URL in messages; every other path is appended as given.

- [ ] **Step 4: Create `TenantDomain`**

`app/Support/Tenancy/TenantDomain.php`:

```php
<?php

namespace App\Support\Tenancy;

use RuntimeException;

/**
 * Composition and validation of tenant hostnames. A "subdomain" tenant is a
 * label under the configured base; earlier tenants live under the legacy
 * bases and are still recognised as KURSA subdomains.
 */
final class TenantDomain
{
    public const RESERVED_LABELS = [
        'app', 'www', 'api', 'idp', 'manager', 'admin', 'mail',
        'marketplace', 'ads', 'marketing', 'sales',
    ];

    private const LABEL_PATTERN = '/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])?$/';

    public static function base(): string
    {
        return strtolower(trim((string) config('tenancy.subdomain_base', 'getkursa.space')));
    }

    /**
     * @return array<int, string> current base first, then legacy bases
     */
    public static function knownBases(): array
    {
        $legacy = array_map(
            fn ($base) => strtolower(trim((string) $base)),
            (array) config('tenancy.legacy_subdomain_bases', [])
        );

        return array_values(array_unique(array_filter([self::base(), ...$legacy])));
    }

    /**
     * @throws RuntimeException when a subdomain label is invalid or reserved
     */
    public static function compose(string $type, string $value): string
    {
        $value = strtolower(trim(preg_replace('#^https?://#i', '', trim($value)) ?? ''));
        $value = rtrim($value, '/');

        if ($type !== 'subdomain') {
            return $value;
        }

        $label = self::labelOf($value) ?? $value;

        if (! self::isValidLabel($label)) {
            throw new RuntimeException('Invalid subdomain: use 3 to 63 letters, numbers or hyphens, not starting or ending with a hyphen.');
        }

        if (self::isReservedLabel($label)) {
            throw new RuntimeException('This subdomain is reserved.');
        }

        return $label.'.'.self::base();
    }

    /**
     * The label when the host sits directly under one of our bases, else null.
     */
    public static function labelOf(string $host): ?string
    {
        $host = strtolower(trim($host));

        foreach (self::knownBases() as $base) {
            $suffix = '.'.$base;

            if (str_ends_with($host, $suffix)) {
                $label = substr($host, 0, -strlen($suffix));

                return $label === '' ? null : $label;
            }
        }

        return null;
    }

    public static function isValidLabel(string $label): bool
    {
        return preg_match(self::LABEL_PATTERN, $label) === 1 && strlen($label) >= 3;
    }

    public static function isReservedLabel(string $label): bool
    {
        return in_array(strtolower($label), self::RESERVED_LABELS, true);
    }

    public static function isKursaSubdomain(string $host): bool
    {
        return self::labelOf($host) !== null;
    }
}
```

- [ ] **Step 5: Create `SwitchTokenIssuer`**

`app/Support/Tenancy/SwitchTokenIssuer.php`:

```php
<?php

namespace App\Support\Tenancy;

use App\Models\Environment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * One-time tokens exchanged by POST /auth/validate-switch-token for a session
 * bound to the target environment. Used by academy switching (60 s) and by
 * onboarding auto sign-in (config tenancy.onboarding_switch_token_ttl_seconds).
 */
final class SwitchTokenIssuer
{
    public const CACHE_PREFIX = 'academy_switch_token:';

    public function issue(User $user, Environment $target, int $ttlSeconds, ?string $sourceEnvironmentId = null): string
    {
        $token = Str::random(64);

        Cache::put(self::CACHE_PREFIX.$token, [
            'user_id' => $user->id,
            'target_environment_id' => $target->id,
            'source_environment_id' => $sourceEnvironmentId,
            'created_at' => now()->toIso8601String(),
        ], now()->addSeconds($ttlSeconds));

        return $token;
    }

    public function redirectUrl(Environment $target, string $token): string
    {
        return TenantUrl::to($target, '/auth/switch', ['token' => $token]);
    }
}
```

- [ ] **Step 6: Run the tests**

Run: `php artisan test --compact tests/Unit/Tenancy`
Expected: PASS

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/Tenancy/TenantUrl.php app/Support/Tenancy/TenantDomain.php app/Support/Tenancy/SwitchTokenIssuer.php tests/Unit/Tenancy
git commit -m "feat(tenancy): add TenantUrl, TenantDomain and SwitchTokenIssuer

TenantUrl decides which host a link uses from domain_verified_at, so every
mail and redirect stops concatenating primary_domain. TenantDomain composes
subdomains under the configured base and recognises the legacy ones; three
copies of formatDomain() and a hardcoded regex will collapse onto it.
SwitchTokenIssuer lets onboarding mint the same one-time token switching uses.

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01GdsYLasvsZ1zMvK7aKzVqP"
```

---

### Task 6: Academy switching through `SwitchTokenIssuer` and `TenantUrl`; `is_account_setup` after an exchange

**Files:**
- Modify: `app/Http/Controllers/Api/Auth/AcademySwitchController.php:95-133, 218-265`
- Modify: `routes/api.php:236-291` (the `/user` closure)
- Test: `tests/Feature/Auth/AcademySwitchRedirectTest.php`

**Interfaces:**
- Consumes: `SwitchTokenIssuer`, `TenantUrl` (Task 5); `EnvironmentResolver::environmentIdFromAbilities()` (Task 2).
- Produces: `POST /auth/academy-switch-token` → `redirect_url` on the shared host when the target domain is pending; `POST /auth/validate-switch-token` and `GET /user` → `is_account_setup` (bool|null).

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Auth/AcademySwitchRedirectTest.php`:

```php
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
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Auth/AcademySwitchRedirectTest.php`
Expected: FAIL — redirect starts with `https://bravo.getkursa.space` in the pending case; `is_account_setup` missing.

- [ ] **Step 3: Rewrite the token minting and redirect in `generateSwitchToken()`**

Replace lines 95-116 (from `// Generate a unique, short-lived token` through `$redirectUrl = ...`) with:

```php
        $expiresIn = 60;
        $issuer = app(SwitchTokenIssuer::class);
        $switchToken = $issuer->issue(
            $user,
            $targetEnvironment,
            $expiresIn,
            $request->header('X-Environment-Id'),
        );

        // A pending domain lands on the shared host; a live one on the tenant domain.
        $redirectUrl = $issuer->redirectUrl($targetEnvironment, $switchToken);
        $targetDomain = parse_url($redirectUrl, PHP_URL_HOST);
```

Add `use App\Support\Tenancy\SwitchTokenIssuer;` and remove the now-unused `use Illuminate\Support\Str;`. In `validateSwitchToken()`, replace `Cache::get("academy_switch_token:{$switchToken}")` and `Cache::forget("academy_switch_token:{$switchToken}")` with `Cache::get(SwitchTokenIssuer::CACHE_PREFIX.$switchToken)` and `Cache::forget(SwitchTokenIssuer::CACHE_PREFIX.$switchToken)`.

In the `validateSwitchToken()` response, add after `'environment_id' => $targetEnvironmentId,`:

```php
            'is_account_setup' => $environmentUser ? (bool) $environmentUser->is_account_setup : null,
```

- [ ] **Step 4: Add `is_account_setup` to `GET /user`**

In `routes/api.php`, inside the `/user` closure, after `$authContext = EffectiveAuthContext::for($user, $environmentId);` add:

```php
    $isAccountSetup = null;
    if ($environmentId) {
        $pivotValue = \App\Models\EnvironmentUser::where('environment_id', $environmentId)
            ->where('user_id', $user->id)
            ->value('is_account_setup');
        $isAccountSetup = $pivotValue === null ? null : (bool) $pivotValue;
    }
```

and add `'is_account_setup' => $isAccountSetup,` to the `array_merge($responseUser, [...])` array next to `'environment_id' => $environmentId,`.

- [ ] **Step 5: Run the tests**

Run: `php artisan test --compact tests/Feature/Auth/AcademySwitchRedirectTest.php tests/Feature/Auth`
Expected: PASS

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/Auth/AcademySwitchController.php routes/api.php tests/Feature/Auth/AcademySwitchRedirectTest.php
git commit -m "feat(auth): send academy switches to the shared host while the target domain is pending

The switch redirect was built from primary_domain, a dead address until the
tenant's DNS exists. It now goes through TenantUrl, so the existing
/auth/switch page rebinds the session in place on app.getkursa.space. The
exchange and GET /user return is_account_setup so a user who arrived by token
can be asked to set a password.

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01GdsYLasvsZ1zMvK7aKzVqP"
```

---

### Task 7: Onboarding auto sign-in (`redirect_url` and `sign-in-link`)

**Files:**
- Modify: `app/Services/Licensing/LicenceService.php` (add `onboardingSignInUrl()`), `app/Http/Controllers/Api/LicenceController.php:246-275` (add `redirect_url`; add `signInLink()`), `routes/api.php:177-184`
- Test: `tests/Feature/Onboarding/OnboardingSignInTest.php`

**Interfaces:**
- Consumes: `SwitchTokenIssuer`, `TenantUrl` (Task 5).
- Produces: `LicenceService::onboardingSignInUrl(Environment $environment): string`; `POST /onboarding/free|trial` 201 body gains `redirect_url`; `POST /licence-checkouts/{uuid}/sign-in-link` → `200 { redirect_url }` or `409 { code: 'checkout_not_ready' }` or `404`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Onboarding/OnboardingSignInTest.php`:

```php
<?php

namespace Tests\Feature\Onboarding;

use App\Models\Environment;
use App\Models\LicenceCheckout;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

/**
 * After onboarding the owner is taken to the shared host already signed in:
 * provisioning mints a one-time switch token and returns the switch URL. Paid
 * flows mint it on demand from a dedicated POST, never from the polled status.
 */
class OnboardingSignInTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Queue::fake();

        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('getChatId')->andReturn('-100');
        $telegram->shouldReceive('escapeMarkdownV2')->andReturnUsing(fn (string $text): string => $text);
        $telegram->shouldReceive('sendMessage')->andReturn(true);
        $this->app->instance(TelegramService::class, $telegram);
    }

    private function payload(): array
    {
        return [
            'name' => 'Semba Ghislaine',
            'email' => 'owner-'.uniqid().'@example.com',
            'environment_name' => 'Ma Classe De Chant',
            'domain_type' => 'subdomain',
            'domain' => 'ma-classe-'.substr(uniqid(), -6),
        ];
    }

    public function test_free_onboarding_returns_a_usable_one_time_sign_in_url_on_the_shared_host(): void
    {
        $response = $this->postJson('/api/onboarding/free', $this->payload(), ['Origin' => 'https://www.getkursa.space'])
            ->assertCreated();

        $redirect = $response->json('redirect_url');
        $environmentId = $response->json('environment_id');

        $this->assertStringStartsWith('https://app.getkursa.space/auth/switch?token=', $redirect);
        $this->assertStringContainsString('environment_id='.$environmentId, $redirect);

        parse_str(parse_url($redirect, PHP_URL_QUERY), $query);

        $exchange = $this->postJson('/api/auth/validate-switch-token', ['token' => $query['token']])
            ->assertOk()
            ->assertJsonPath('environment_id', $environmentId)
            ->assertJsonPath('is_account_setup', false);

        $this->assertNotEmpty($exchange->json('token'));
        $this->postJson('/api/auth/validate-switch-token', ['token' => $query['token']])->assertStatus(401);
    }

    public function test_trial_onboarding_also_returns_the_sign_in_url(): void
    {
        $this->postJson('/api/onboarding/trial', $this->payload())
            ->assertCreated()
            ->assertJsonStructure(['environment_id', 'domain', 'redirect_url', 'trial_ends_at']);
    }

    public function test_the_sign_in_link_is_refused_until_the_checkout_is_paid_and_provisioned(): void
    {
        $checkout = LicenceCheckout::create([
            'plan_type' => 'creator_monthly',
            'quoted_amount' => 20,
            'quoted_currency' => 'USD',
            'status' => LicenceCheckout::STATUS_PENDING_PAYMENT,
        ]);

        $this->postJson("/api/licence-checkouts/{$checkout->uuid}/sign-in-link")
            ->assertStatus(409)
            ->assertJsonPath('code', 'checkout_not_ready');

        $this->postJson('/api/licence-checkouts/00000000-0000-0000-0000-000000000000/sign-in-link')
            ->assertNotFound();
    }

    public function test_the_sign_in_link_mints_a_token_for_the_owner_once_paid(): void
    {
        $owner = User::factory()->create();
        $environment = Environment::factory()->create(['owner_id' => $owner->id]);
        $checkout = LicenceCheckout::create([
            'plan_type' => 'creator_monthly',
            'quoted_amount' => 20,
            'quoted_currency' => 'USD',
            'status' => LicenceCheckout::STATUS_PAID,
            'environment_id' => $environment->id,
        ]);

        $redirect = $this->postJson("/api/licence-checkouts/{$checkout->uuid}/sign-in-link")
            ->assertOk()
            ->json('redirect_url');

        $this->assertStringStartsWith('https://app.getkursa.space/auth/switch?token=', $redirect);

        parse_str(parse_url($redirect, PHP_URL_QUERY), $query);
        $this->postJson('/api/auth/validate-switch-token', ['token' => $query['token']])
            ->assertOk()
            ->assertJsonPath('environment_id', $environment->id)
            ->assertJsonPath('user.id', $owner->id);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Onboarding/OnboardingSignInTest.php`
Expected: FAIL — no `redirect_url`; `sign-in-link` route is 404 for every uuid.

- [ ] **Step 3: Add `onboardingSignInUrl()` to `LicenceService`**

After `provisionEnvironmentFromPayload()` add:

```php
    /**
     * One-time sign-in link for the environment owner, landing wherever
     * TenantUrl says the environment lives (the shared host until its own
     * domain is verified). The token is single-use and short-lived.
     */
    public function onboardingSignInUrl(Environment $environment): string
    {
        $owner = $environment->owner()->firstOrFail();
        $issuer = app(SwitchTokenIssuer::class);
        $token = $issuer->issue(
            $owner,
            $environment,
            (int) config('tenancy.onboarding_switch_token_ttl_seconds', 300),
        );

        return $issuer->redirectUrl($environment, $token);
    }
```

with `use App\Support\Tenancy\SwitchTokenIssuer;`.

- [ ] **Step 4: Return `redirect_url` from `LicenceController::onboard()` and add `signInLink()`**

In `onboard()`, change the `$body` array to:

```php
        $body = [
            'environment_id' => $environment->id,
            'domain' => $environment->primary_domain,
            'password_set_link_sent' => true,
            'redirect_url' => $this->licenceService->onboardingSignInUrl($environment),
        ];
```

Add the method after `checkoutStatus()`:

```php
    // ---------------------------------------------------------------------
    // D2. POST /licence-checkouts/{uuid}/sign-in-link  (public, throttled)
    // ---------------------------------------------------------------------
    /**
     * A one-time sign-in link for the owner of a paid checkout's environment.
     * Separate from the polled status endpoint so no one-time secret is
     * minted on every poll.
     */
    public function signInLink(string $uuid): JsonResponse
    {
        $checkout = LicenceCheckout::where('uuid', $uuid)->first();
        if (! $checkout) {
            return response()->json(['status' => 'error', 'message' => 'Checkout not found'], 404);
        }

        $environment = $checkout->status === LicenceCheckout::STATUS_PAID && $checkout->environment_id
            ? Environment::find($checkout->environment_id)
            : null;

        if (! $environment) {
            return response()->json([
                'code' => 'checkout_not_ready',
                'message' => 'The checkout has not been paid or the academy is not provisioned yet.',
            ], 409);
        }

        return response()->json([
            'redirect_url' => $this->licenceService->onboardingSignInUrl($environment),
        ]);
    }
```

In `routes/api.php`, inside the `Route::prefix('licence-checkouts')->group(...)` block (lines 177-184), add:

```php
    Route::post('/{uuid}/sign-in-link', [LicenceController::class, 'signInLink'])->middleware('throttle:30,1');
```

- [ ] **Step 5: Run the tests**

Run: `php artisan test --compact tests/Feature/Onboarding/OnboardingSignInTest.php tests/Feature/Licensing`
Expected: PASS

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Licensing/LicenceService.php app/Http/Controllers/Api/LicenceController.php routes/api.php tests/Feature/Onboarding/OnboardingSignInTest.php
git commit -m "feat(onboarding): return a one-time sign-in link so new owners land signed in

Free and trial onboarding now answer with redirect_url, a single-use switch
link on the shared host (or the tenant domain once live). Paid checkouts get
the same link from POST /licence-checkouts/{uuid}/sign-in-link, kept apart
from the polled status endpoint so no secret is minted per poll.

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01GdsYLasvsZ1zMvK7aKzVqP"
```

---

### Task 8: Compose every subdomain through `TenantDomain`

**Files:**
- Modify: `app/Services/Licensing/LicenceService.php:743-754`, `app/Http/Controllers/Api/Onboarding/StandaloneOnboardingController.php:212-230`, `app/Http/Controllers/Api/Onboarding/DemoOnboardingController.php:224-242`, `app/Http/Controllers/Api/Onboarding/SupportedOnboardingController.php` (its `formatDomain`), `app/Http/Controllers/Api/Onboarding/OnboardingController.php:160-236, 280-318`, `app/Http/Controllers/Api/ValidationController.php:20-78`, `app/Notifications/EnvironmentCreatedNotification.php:148-161`, `app/Mail/EnvironmentSetupMail.php:79-83`
- Test: `tests/Feature/Onboarding/SubdomainCompositionTest.php`; update `tests/Feature/Onboarding/ValidateDomainTest.php`

**Interfaces:**
- Consumes: `TenantDomain` (Task 5).
- Produces: `POST /onboarding/validate-domain` with `type=subdomain` accepts a bare label or a host under any known base and returns `{ success, available, domain, message, suggestions }` where `domain` is the composed host under the configured base.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Onboarding/SubdomainCompositionTest.php`:

```php
<?php

namespace Tests\Feature\Onboarding;

use App\Models\Environment;
use App\Services\TelegramService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class SubdomainCompositionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Queue::fake();
        $telegram = Mockery::mock(TelegramService::class);
        $telegram->shouldReceive('getChatId')->andReturn('-100');
        $telegram->shouldReceive('escapeMarkdownV2')->andReturnUsing(fn (string $t): string => $t);
        $telegram->shouldReceive('sendMessage')->andReturn(true);
        $this->app->instance(TelegramService::class, $telegram);
    }

    public function test_free_onboarding_composes_the_subdomain_under_the_configured_base(): void
    {
        $response = $this->postJson('/api/onboarding/free', [
            'name' => 'Owner', 'email' => 'owner@example.com', 'environment_name' => 'Academy',
            'domain_type' => 'subdomain', 'domain' => 'Acme',
        ])->assertCreated();

        $this->assertSame('acme.getkursa.space', $response->json('domain'));
        $this->assertDatabaseHas('environments', ['primary_domain' => 'acme.getkursa.space']);
    }

    public function test_free_onboarding_rejects_a_reserved_or_malformed_label(): void
    {
        foreach (['app', 'a b'] as $label) {
            $this->postJson('/api/onboarding/free', [
                'name' => 'Owner', 'email' => $label.'@example.com', 'environment_name' => 'Academy',
                'domain_type' => 'subdomain', 'domain' => $label,
            ])->assertStatus(422);
        }
    }

    public function test_validate_domain_accepts_a_bare_label_and_returns_the_composed_host(): void
    {
        $this->postJson('/api/onboarding/validate-domain', ['domain' => 'brand-new', 'type' => 'subdomain'])
            ->assertOk()
            ->assertJson(['success' => true, 'available' => true, 'domain' => 'brand-new.getkursa.space']);
    }

    public function test_validate_domain_accepts_a_legacy_host_and_checks_the_composed_host(): void
    {
        Environment::factory()->create(['primary_domain' => 'taken.getkursa.space']);

        $response = $this->postJson('/api/onboarding/validate-domain', ['domain' => 'taken.csl-brands.com', 'type' => 'subdomain'])
            ->assertOk()
            ->assertJson(['success' => true, 'available' => false, 'domain' => 'taken.getkursa.space']);

        $this->assertStringEndsWith('.getkursa.space', $response->json('suggestions.0'));
    }

    public function test_validate_domain_rejects_reserved_labels(): void
    {
        $this->postJson('/api/onboarding/validate-domain', ['domain' => 'www', 'type' => 'subdomain'])
            ->assertStatus(422);
    }
}
```

In `tests/Feature/Onboarding/ValidateDomainTest.php`, update the two subdomain-format expectations: in `test_validate_domain_rejects_a_malformed_subdomain` keep the 422 (a host under a foreign base is still rejected), and in `test_validate_domain_reports_a_taken_subdomain_with_suggestions` create the taken environment with `'primary_domain' => 'maclassedechant.getkursa.space'` and post `'domain' => 'maclassedechant'`.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Onboarding/SubdomainCompositionTest.php tests/Feature/Onboarding/ValidateDomainTest.php`
Expected: FAIL — `acme.csl-brands.com` composed; bare label rejected by the regex.

- [ ] **Step 3: Replace the four `formatDomain()` copies**

In `LicenceService`, replace the private `formatDomain()` method body with:

```php
    private function formatDomain(string $domainType, string $domain): string
    {
        return TenantDomain::compose($domainType, $domain);
    }
```

with `use App\Support\Tenancy\TenantDomain;`. Do the same in `StandaloneOnboardingController`, `DemoOnboardingController` and `SupportedOnboardingController` (each keeps its method name and signature; the body becomes the one-line delegation). Since `TenantDomain::compose()` throws `RuntimeException` on a bad label, confirm each legacy controller wraps the call in the same `try/catch (\Exception)` it already uses around environment creation; if it does not, add `catch (\RuntimeException $e) { return response()->json(['status' => 'error', 'message' => $e->getMessage()], 422); }` around the `formatDomain()` call.

- [ ] **Step 4: Rewrite `OnboardingController::validateDomain()` and the suggestions**

Replace the body of `validateDomain()` from the `$domain = strtolower(trim(...))` line through the final `return` with:

```php
        $domain = strtolower(trim($request->input('domain')));
        $type = $request->input('type');

        if (str_contains($domain, 'localhost')) {
            return response()->json([
                'success' => false,
                'message' => 'localhost is not allowed',
                'errors' => ['domain' => ['localhost is not allowed']],
            ], 422);
        }

        if ($type === 'subdomain') {
            try {
                $domain = TenantDomain::compose('subdomain', $domain);
            } catch (\RuntimeException $e) {
                return response()->json([
                    'success' => false,
                    'message' => $e->getMessage(),
                    'errors' => ['domain' => [$e->getMessage()]],
                ], 422);
            }
        }

        $domainExists = Environment::where(function ($query) use ($domain) {
            $query->where('primary_domain', $domain)
                ->orWhereJsonContains('additional_domains', $domain);
        })->exists();

        if ($domainExists) {
            $suggestions = $type === 'subdomain'
                ? $this->generateSubdomainSuggestions((string) TenantDomain::labelOf($domain))
                : [];

            return response()->json([
                'success' => true,
                'available' => false,
                'domain' => $domain,
                'message' => 'Domain is already in use',
                'suggestions' => $suggestions,
            ], 200);
        }

        return response()->json([
            'success' => true,
            'available' => true,
            'domain' => $domain,
            'message' => 'Domain is available',
        ], 200);
```

Keep whatever validation of `domain`/`type` presence precedes it. Replace both `'.csl-brands.com'` literals in `generateSubdomainSuggestions()` with `'.'.TenantDomain::base()`. In `validate()` (the combined email+domain check), compose the same way: after `$type = $request->input('type');` add

```php
        if ($type === 'subdomain') {
            try {
                $domain = TenantDomain::compose('subdomain', $domain);
            } catch (\RuntimeException $e) {
                return response()->json(['success' => false, 'message' => $e->getMessage(), 'errors' => ['domain' => [$e->getMessage()]]], 422);
            }
        }
```

and pass `(string) TenantDomain::labelOf($domain)` to `generateSubdomainSuggestions()`. Add `use App\Support\Tenancy\TenantDomain;`.

- [ ] **Step 5: Rewrite `ValidationController`**

`validateSubdomain()`: replace the `LIKE` query with

```php
        try {
            $host = TenantDomain::compose('subdomain', $subdomain);
        } catch (\RuntimeException $e) {
            return response()->json(['available' => false, 'message' => $e->getMessage()], Response::HTTP_OK);
        }

        $exists = Environment::where('primary_domain', $host)
            ->orWhereJsonContains('additional_domains', $host)
            ->exists();
```

`validateDomain()`: replace `->orWhere('additional_domains', 'LIKE', '%' . $domain . '%')` with `->orWhereJsonContains('additional_domains', $domain)`. Add the `TenantDomain` import.

- [ ] **Step 6: Replace the two `isSubdomain()` helpers**

`EnvironmentCreatedNotification::isSubdomain()` and `EnvironmentSetupMail::isSubdomain()` become:

```php
    private function isSubdomain(): bool
    {
        return TenantDomain::isKursaSubdomain((string) $this->environment->primary_domain);
    }
```

with the import.

- [ ] **Step 7: Run the tests**

Run: `php artisan test --compact tests/Feature/Onboarding tests/Feature/Licensing`
Expected: PASS

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Services/Licensing/LicenceService.php app/Http/Controllers/Api/Onboarding app/Http/Controllers/Api/ValidationController.php app/Notifications/EnvironmentCreatedNotification.php app/Mail/EnvironmentSetupMail.php tests/Feature/Onboarding
git commit -m "feat(onboarding): compose KURSA subdomains under getkursa.space from one place

The subdomain base was appended in four copies of formatDomain() and checked
by a hardcoded csl-brands.com regex, so the brand's own domain could not be
used. TenantDomain::compose() owns the rule: bare labels and hosts under any
known base compose under the configured base; reserved and malformed labels
are refused. validate-domain now answers with the composed host.

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01GdsYLasvsZ1zMvK7aKzVqP"
```

---

### Task 9: Every tenant link through `TenantUrl`, with a pending-domain notice in the onboarding mails

**Files:**
- Modify (link builders): `app/Services/Licensing/LicenceService.php:587,601,655`, `app/Providers/AppServiceProvider.php:105-127`, `app/Http/Controllers/Api/Admin/PasswordLinkController.php:276`, `app/Notifications/EnvironmentPasswordReset.php:157-171`, `app/Notifications/EnvironmentCreatedNotification.php:138-143`, `app/Notifications/EnvironmentAccountCreated.php:76-77`, `app/Notifications/CertificateIsuued.php:71-72`, `app/Notifications/OrderCreated.php:62-63`, `app/Mail/EnvironmentSetupMail.php:88-92`, `app/Mail/EnvironmentResetPasswordMail.php:90-101`, `app/Mail/OrderConfirmation.php:41-43`, `app/Mail/DigitalProductDelivery.php:46-48`, `app/Mail/LearnerWeeklyDigest.php:47`, `app/Mail/InstructorWeeklyDigest.php:47`, `app/Mail/ProductSubscriptionExpiringReminder.php:43-45`, `app/Mail/Licensing/TrialReminderMail.php:59-61`, `app/Mail/Licensing/LicenceRenewalWarningMail.php:46-48`, `app/Support/Retention/RetentionLinks.php:45-66`, `app/Support/Retention/RetentionScenarioRegistry.php:171-183, 289, 317, 346, 363`, `resources/views/payment/callback-success.blade.php`, `callback-failed.blade.php`, `callback-cancelled.blade.php`, `callback-pending.blade.php`, `error.blade.php`, `environment-setup/*.blade.php`
- Modify (notice): `resources/views/emails/environment-reset-password.blade.php:94-103`, `resources/views/emails/environment-setup.blade.php:60-66`
- Test: `tests/Feature/Mail/TenantLinksTest.php`; extend `tests/Feature/Auth/PasswordResetUrlTest.php`

**Interfaces:**
- Consumes: `TenantUrl` (Task 5).
- Produces: mail view variable `pendingDomainNotice` (string|null) on `EnvironmentResetPasswordMail` and `EnvironmentSetupMail`; retention context key `environment_url` replaces `environment_domain`.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Mail/TenantLinksTest.php`:

```php
<?php

namespace Tests\Feature\Mail;

use App\Mail\EnvironmentResetPasswordMail;
use App\Mail\EnvironmentSetupMail;
use App\Mail\LearnerWeeklyDigest;
use App\Models\Environment;
use App\Models\User;
use App\Support\Retention\RetentionLinks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Every tenant link used to be 'https://' . primary_domain . path, a dead
 * address while the tenant's domain is not live. They now go through
 * TenantUrl: the shared host (with environment_id) while pending, the tenant
 * domain once verified.
 */
class TenantLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_password_set_mail_links_to_the_shared_host_while_the_domain_is_pending(): void
    {
        $environment = Environment::factory()->create(['primary_domain' => 'acme.getkursa.space']);
        $mail = new EnvironmentResetPasswordMail('tok', $environment, 'a@b.test', 'a@b.test');

        $with = $mail->content()->with;
        $html = $mail->render();

        $this->assertStringStartsWith('https://app.getkursa.space/auth/reset-password?', $with['resetUrl']);
        $this->assertStringContainsString('environment_id='.$environment->id, $with['resetUrl']);
        $this->assertStringContainsString('app.getkursa.space', $with['pendingDomainNotice']);
        $this->assertStringContainsString('acme.getkursa.space', $with['pendingDomainNotice']);
        $this->assertStringContainsString('app.getkursa.space', $html);
    }

    public function test_the_password_set_mail_links_to_the_tenant_domain_once_live(): void
    {
        $environment = Environment::factory()->create(['primary_domain' => 'acme.getkursa.space', 'domain_verified_at' => now()]);
        $mail = new EnvironmentResetPasswordMail('tok', $environment, 'a@b.test', 'a@b.test');

        $with = $mail->content()->with;

        $this->assertStringStartsWith('https://acme.getkursa.space/auth/reset-password?', $with['resetUrl']);
        $this->assertNull($with['pendingDomainNotice']);
    }

    public function test_the_setup_mail_and_a_digest_follow_the_same_rule(): void
    {
        $owner = User::factory()->create();
        $pending = Environment::factory()->create(['primary_domain' => 'acme.getkursa.space', 'owner_id' => $owner->id]);

        $setup = (new EnvironmentSetupMail($pending, $owner, 'pw'))->content()->with;
        $this->assertSame('https://app.getkursa.space/auth/login?environment_id='.$pending->id, $setup['loginUrl']);
        $this->assertNotNull($setup['pendingDomainNotice']);

        $digest = (new LearnerWeeklyDigest($owner, $pending, []))->content()->with;
        $this->assertSame('https://app.getkursa.space/auth/login?environment_id='.$pending->id, $digest['loginUrl']);
    }

    public function test_retention_links_use_the_environment_url_from_the_context(): void
    {
        $links = new RetentionLinks;

        $this->assertSame(
            'https://app.getkursa.space/dashboard?environment_id=5',
            $links->forScenario('instructor_inactive', ['environment_url' => 'https://app.getkursa.space/?environment_id=5'])
        );
        $this->assertSame('https://acme.getkursa.space/', $links->forScenario('learner_inactive', ['environment_url' => 'https://acme.getkursa.space/']));
    }
}
```

The `EnvironmentSetupMail` and `LearnerWeeklyDigest` constructor signatures above are the ones in the repo today; if either differs, adjust the test to the real signature rather than the mail.

Also add to `tests/Feature/Auth/PasswordResetUrlTest.php` one test asserting that a self-service reset for a member of a pending-domain environment yields a link starting with `https://app.getkursa.space/auth/reset-password?` (mirror the existing test in that file that asserts the tenant-domain link, with `domain_verified_at => null`; set `domain_verified_at => now()` on the environments in the existing tests so their expectations still hold).

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Mail/TenantLinksTest.php tests/Feature/Auth/PasswordResetUrlTest.php`
Expected: FAIL — links start with `https://acme.getkursa.space`; `pendingDomainNotice` undefined.

- [ ] **Step 3: Rewrite each builder**

Add `use App\Support\Tenancy\TenantUrl;` to every file below and make these exact replacements:

`LicenceService.php`
- 587: `"https://{$environment->primary_domain}",` → `TenantUrl::to($environment, '/'),`
- 601: same replacement.
- 655-659: `return TenantUrl::to($environment, '/auth/reset-password', ['token' => $token, 'email' => $user->email, 'environment_id' => $environment->id]);`

`AppServiceProvider.php:109-126` → replace from `if ($environment === null || blank(...` through the final `return $domain . ...;` with:

```php
            if ($environment === null) {
                return url(route('password.reset', [
                    'token' => $token,
                    'email' => $notifiable->getEmailForPasswordReset(),
                ], false));
            }

            return TenantUrl::to($environment, '/auth/reset-password', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
                'environment_id' => $environment->id,
            ]);
```

`PasswordLinkController.php:276-280` → `return TenantUrl::to($environment, '/auth/reset-password', ['token' => $token, 'email' => $owner->email, 'environment_id' => $environment->id]);`

`EnvironmentPasswordReset.php:164-168` → replace the `if ($this->environment->primary_domain) {...}` block with:

```php
        if ($this->environment->primary_domain) {
            $resetUrl = TenantUrl::to($this->environment, '/auth/reset-password', [
                'token' => $this->token,
                'email' => $this->userEmail,
            ]);
        }
```

`EnvironmentCreatedNotification::generateLoginUrl()` → `return TenantUrl::to($this->environment, '/auth/login');`
`EnvironmentAccountCreated.php:76-77`, `CertificateIsuued.php:71-72` → `$loginUrl = TenantUrl::to($this->environment, '/auth/login');` (drop the `$protocol` line).
`OrderCreated.php:63` → `$continuePaymentUrl = TenantUrl::to($environment, '/checkout/continue-payment/'.$this->order->id);` (drop the `$protocol` line if only used here).
`EnvironmentSetupMail::generateLoginUrl()` → `return TenantUrl::to($this->environment, '/auth/login');`
`EnvironmentResetPasswordMail::generateResetUrl()` → body becomes `return TenantUrl::to($this->environment, '/auth/reset-password', ['token' => $this->token, 'email' => $this->userEmail, 'environment_id' => $this->environment->id]);`
`OrderConfirmation.php:42` → `? TenantUrl::to($this->order->environment, '/learners/orders/'.$this->order->id)`
`DigitalProductDelivery.php:47` → `? TenantUrl::to($this->order->environment, '/learners/dashboard')`
`LearnerWeeklyDigest.php:47` → `'loginUrl' => TenantUrl::to($this->environment, '/auth/login'),`
`InstructorWeeklyDigest.php:47` → `'dashboardUrl' => TenantUrl::to($this->environment, '/dashboard'),`
`ProductSubscriptionExpiringReminder.php:44` → `? TenantUrl::to($this->subscription->environment, '/learners/subscriptions')`
`TrialReminderMail.php:60`, `LicenceRenewalWarningMail.php:47` → `? TenantUrl::to($environment, '/billing')`

`RetentionLinks.php`: replace `instructorLink()` and `learnerLink()` with:

```php
    private function instructorLink(array $context, string $default): string
    {
        $url = $context['environment_url'] ?? null;

        return $url ? $this->withPath((string) $url, '/dashboard') : $default;
    }

    private function learnerLink(array $context, string $default): string
    {
        $url = $context['environment_url'] ?? null;

        return $url ? (string) $url : $default;
    }

    /** Insert a path before the query string of a TenantUrl base link. */
    private function withPath(string $url, string $path): string
    {
        $parts = parse_url($url);
        $base = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '').(isset($parts['port']) ? ':'.$parts['port'] : '');

        return $base.$path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }
```

`RetentionScenarioRegistry.php`: in `instructorContext()` select `['id', 'primary_domain', 'domain_verified_at']` and return `'environment_url' => $env ? TenantUrl::to($env, '/') : null` instead of `environment_domain`. In the two learner selects add `'environments.id as env_id'` and `'environments.domain_verified_at as env_domain_verified_at'`, and in the two context arrays replace `'environment_domain' => $row->env_domain` with:

```php
                    'environment_url' => $row->env_domain
                        ? TenantUrl::to((new Environment)->forceFill([
                            'id' => (int) $row->env_id,
                            'primary_domain' => $row->env_domain,
                            'domain_verified_at' => $row->env_domain_verified_at,
                        ]), '/')
                        : null,
```

Update the docblock in `RetentionTarget.php:9` to name `environment_url`.

Blade views: replace every `{{ $protocol }}://{{ $environment->primary_domain }}` (and the `{{$environment->primary_domain }}` variant) with `{{ \App\Support\Tenancy\TenantUrl::base($environment) }}`, so for example `data-primary-domain="{{ \App\Support\Tenancy\TenantUrl::base($environment) }}"` and `href="{{ \App\Support\Tenancy\TenantUrl::to($environment, '/storefront/'.$environment->id) }}"`. Use `TenantUrl::to(...)` for `href`s and `TenantUrl::base(...)` for the data attribute. Leave the `$defaultEnvironment` lookups in `environment-setup/*.blade.php` alone.

- [ ] **Step 4: Add the pending-domain notice to the two onboarding mails**

In `EnvironmentResetPasswordMail::content()` and `EnvironmentSetupMail::content()` add to `with`:

```php
                'pendingDomainNotice' => TenantUrl::isLive($this->environment)
                    ? null
                    : sprintf(
                        'Your academy is available at %s now. Once %s is live it will open there.',
                        TenantUrl::base($this->environment),
                        $this->environment->primary_domain,
                    ),
```

In `resources/views/emails/environment-reset-password.blade.php`, after line 103 (`<p>{{ $resetUrl }}</p>`) add:

```blade
            @if(!empty($pendingDomainNotice))
            <p style="margin-top: 16px; color: #717882;">{{ $pendingDomainNotice }}</p>
            @endif
```

In `resources/views/emails/environment-setup.blade.php`, change the URL cell at line 65 to show `{{ parse_url($loginUrl, PHP_URL_HOST) }}` instead of `{{ $environment->primary_domain }}`, and after the closing `</table>` of that details table add the same `@if(!empty($pendingDomainNotice))` paragraph.

- [ ] **Step 5: Run the tests**

Run: `php artisan test --compact tests/Feature/Mail tests/Feature/Auth/PasswordResetUrlTest.php tests/Feature/Licensing tests/Feature/Characterisation`
Expected: PASS

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app resources/views tests/Feature/Mail/TenantLinksTest.php tests/Feature/Auth/PasswordResetUrlTest.php
git commit -m "feat(tenancy): build every tenant link with TenantUrl

Twenty mail, notification, provider, retention and blade sites concatenated
https:// with primary_domain, so every link a new tenant received was dead
until their DNS existed. All of them now go through TenantUrl: the shared
host with the environment id while the domain is pending, the tenant domain
once verified. The password-set and setup mails say which one applies.

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01GdsYLasvsZ1zMvK7aKzVqP"
```

---

### Task 10: Domain verification: probe, hourly command, admin override

**Files:**
- Create: `app/Support/Tenancy/DomainProbe.php`, `app/Support/Tenancy/DnsHttpDomainProbe.php`, `app/Console/Commands/VerifyEnvironmentDomains.php`, `app/Http/Controllers/Api/Admin/DomainVerificationController.php`
- Modify: `app/Providers/AppServiceProvider.php:22-25`, `routes/console.php` (after the abandoned-orders schedule), `routes/api.php:1215-1295` (admin group), `app/Http/Controllers/Api/CustomerController.php:74-105` (selects)
- Test: `tests/Feature/Tenancy/VerifyEnvironmentDomainsTest.php`, `tests/Feature/Tenancy/DomainVerificationAdminTest.php`

**Interfaces:**
- Produces: `DomainProbe::isLive(string $host): bool`; command `environments:verify-domains`; `PUT /api/admin/environments/{environmentId}/domain-verification { verified: bool }` → `{ status: 'success', data: { id, primary_domain, domain_verified_at } }`; `domain_verified_at` in the admin customer payload.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Tenancy/VerifyEnvironmentDomainsTest.php`:

```php
<?php

namespace Tests\Feature\Tenancy;

use App\Models\Environment;
use App\Support\Tenancy\DomainProbe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerifyEnvironmentDomainsTest extends TestCase
{
    use RefreshDatabase;

    private function fakeProbe(array $liveHosts): void
    {
        $this->app->instance(DomainProbe::class, new class($liveHosts) implements DomainProbe
        {
            public array $asked = [];

            public function __construct(private array $live)
            {
            }

            public function isLive(string $host): bool
            {
                $this->asked[] = $host;

                return in_array($host, $this->live, true);
            }
        });
    }

    public function test_it_stamps_environments_whose_domain_answers_and_leaves_the_others_null(): void
    {
        $this->fakeProbe(['live.getkursa.space']);
        $live = Environment::factory()->create(['primary_domain' => 'live.getkursa.space']);
        $dead = Environment::factory()->create(['primary_domain' => 'dead.getkursa.space']);

        $this->artisan('environments:verify-domains')->assertSuccessful();

        $this->assertNotNull($live->fresh()->domain_verified_at);
        $this->assertNull($dead->fresh()->domain_verified_at);
    }

    public function test_it_never_probes_or_clears_an_already_verified_environment(): void
    {
        $this->fakeProbe([]);
        $verified = Environment::factory()->create(['primary_domain' => 'ok.getkursa.space', 'domain_verified_at' => now()->subDay()]);
        Environment::factory()->create(['primary_domain' => 'inactive.getkursa.space', 'is_active' => false]);

        $this->artisan('environments:verify-domains')->assertSuccessful();

        $this->assertNotNull($verified->fresh()->domain_verified_at);
        $this->assertSame([], $this->app->make(DomainProbe::class)->asked);
    }
}
```

`tests/Feature/Tenancy/DomainVerificationAdminTest.php`:

```php
<?php

namespace Tests\Feature\Tenancy;

use App\Models\Environment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainVerificationAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_mark_a_domain_live_and_clear_it_again(): void
    {
        $admin = User::factory()->create(['role' => 'super_admin']);
        $environment = Environment::factory()->create();

        $this->actingAs($admin)
            ->putJson("/api/admin/environments/{$environment->id}/domain-verification", ['verified' => true])
            ->assertOk()
            ->assertJsonPath('data.id', $environment->id);
        $this->assertNotNull($environment->fresh()->domain_verified_at);

        $this->actingAs($admin)
            ->putJson("/api/admin/environments/{$environment->id}/domain-verification", ['verified' => false])
            ->assertOk()
            ->assertJsonPath('data.domain_verified_at', null);
        $this->assertNull($environment->fresh()->domain_verified_at);
    }

    public function test_a_teacher_cannot_touch_domain_verification(): void
    {
        $teacher = User::factory()->create(['role' => 'company_teacher']);
        $environment = Environment::factory()->create(['owner_id' => $teacher->id]);

        $this->actingAs($teacher)
            ->putJson("/api/admin/environments/{$environment->id}/domain-verification", ['verified' => true])
            ->assertForbidden();
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Tenancy/VerifyEnvironmentDomainsTest.php tests/Feature/Tenancy/DomainVerificationAdminTest.php`
Expected: FAIL — interface missing; command not found; route 404.

- [ ] **Step 3: Create the probe**

`app/Support/Tenancy/DomainProbe.php`:

```php
<?php

namespace App\Support\Tenancy;

interface DomainProbe
{
    /** Whether the host resolves in DNS and answers HTTPS with a non-5xx status. */
    public function isLive(string $host): bool;
}
```

`app/Support/Tenancy/DnsHttpDomainProbe.php`:

```php
<?php

namespace App\Support\Tenancy;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class DnsHttpDomainProbe implements DomainProbe
{
    public function isLive(string $host): bool
    {
        $host = strtolower(trim($host));

        if ($host === '' || in_array(preg_replace('/:\d+$/', '', $host), ['localhost', '127.0.0.1'], true)) {
            return false;
        }

        $bare = preg_replace('/:\d+$/', '', $host);
        $records = @dns_get_record($bare, DNS_A | DNS_AAAA | DNS_CNAME);

        if (! is_array($records) || $records === []) {
            return false;
        }

        try {
            $response = Http::timeout((int) config('tenancy.domain_probe.http_timeout_seconds', 5))
                ->withoutRedirecting()
                ->head('https://'.$host.'/');

            return $response->status() < 500;
        } catch (Throwable $e) {
            Log::info('tenancy.domain_probe_failed', ['host' => $host, 'reason' => $e->getMessage()]);

            return false;
        }
    }
}
```

Bind it in `AppServiceProvider::register()`:

```php
    public function register(): void
    {
        $this->app->bind(\App\Support\Tenancy\DomainProbe::class, \App\Support\Tenancy\DnsHttpDomainProbe::class);
    }
```

- [ ] **Step 4: Create the command and schedule it**

Run `php artisan make:command VerifyEnvironmentDomains --no-interaction` and replace the file with:

```php
<?php

namespace App\Console\Commands;

use App\Models\Environment;
use App\Support\Tenancy\DomainProbe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Stamps domain_verified_at on active environments whose own domain answers.
 * Only ever sets the flag; an operator clears it from the admin app. Links
 * switch from the shared host to the tenant domain once it is set.
 */
class VerifyEnvironmentDomains extends Command
{
    protected $signature = 'environments:verify-domains';

    protected $description = 'Mark environments whose primary domain resolves and answers HTTPS as domain-verified';

    public function handle(DomainProbe $probe): int
    {
        $verified = 0;

        Environment::query()
            ->where('is_active', true)
            ->whereNull('domain_verified_at')
            ->whereNotNull('primary_domain')
            ->orderBy('id')
            ->each(function (Environment $environment) use ($probe, &$verified): void {
                if (! $probe->isLive((string) $environment->primary_domain)) {
                    return;
                }

                $environment->forceFill(['domain_verified_at' => now()])->save();
                $verified++;

                Log::info('tenancy.domain_verified', [
                    'environment_id' => $environment->id,
                    'primary_domain' => $environment->primary_domain,
                ]);
            });

        $this->info("Verified {$verified} environment domain(s).");

        return self::SUCCESS;
    }
}
```

In `routes/console.php`, after the abandoned-orders schedule add:

```php
// Tenant domains: stamp domain_verified_at once a tenant's own domain answers - hourly
Schedule::command(\App\Console\Commands\VerifyEnvironmentDomains::class)
    ->hourly()
    ->withoutOverlapping(3600)
    ->onOneServer()
    ->runInBackground();
```

- [ ] **Step 5: Create the admin endpoint and expose the column**

`app/Http/Controllers/Api/Admin/DomainVerificationController.php`:

```php
<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Environment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Operator override for domain liveness. The hourly probe only sets the flag;
 * this is the one place it can be cleared, for instance when a tenant's DNS
 * is taken down again.
 */
class DomainVerificationController extends Controller
{
    public function update(Request $request, int $environmentId): JsonResponse
    {
        $role = $request->user()?->role instanceof UserRole ? $request->user()->role->value : $request->user()?->role;

        if (! in_array($role, [UserRole::ADMIN->value, UserRole::SUPER_ADMIN->value], true)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate(['verified' => 'required|boolean']);

        $environment = Environment::find($environmentId);
        if (! $environment) {
            return response()->json(['status' => 'error', 'message' => 'Environment not found'], 404);
        }

        $environment->forceFill([
            'domain_verified_at' => $validated['verified'] ? now() : null,
        ])->save();

        Log::info('tenancy.domain_verification_overridden', [
            'environment_id' => $environment->id,
            'verified' => (bool) $validated['verified'],
            'admin_user_id' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $environment->id,
                'primary_domain' => $environment->primary_domain,
                'domain_verified_at' => $environment->domain_verified_at?->toIso8601String(),
            ],
        ]);
    }
}
```

In `routes/api.php`, inside the `admin` group next to the `resend-password-link` route add:

```php
    // Operator override for domain liveness (the hourly probe only ever sets it).
    Route::put('/environments/{environmentId}/domain-verification', [\App\Http\Controllers\Api\Admin\DomainVerificationController::class, 'update']);
```

In `CustomerController.php` add `'domain_verified_at',` after `'is_active',` in the `ownedEnvironments` select and `'environments.domain_verified_at',` after `'environments.is_active',` in the `environments` select.

- [ ] **Step 6: Run the tests**

Run: `php artisan test --compact tests/Feature/Tenancy tests/Feature/Admin 2>/dev/null || php artisan test --compact tests/Feature/Tenancy`
Expected: PASS

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/Tenancy/DomainProbe.php app/Support/Tenancy/DnsHttpDomainProbe.php app/Console/Commands/VerifyEnvironmentDomains.php app/Http/Controllers/Api/Admin/DomainVerificationController.php app/Providers/AppServiceProvider.php routes/console.php routes/api.php app/Http/Controllers/Api/CustomerController.php tests/Feature/Tenancy
git commit -m "feat(tenancy): verify tenant domains hourly, with an admin override

domain_verified_at is stamped by environments:verify-domains once a tenant's
domain resolves and answers HTTPS; the command never clears it, so links do
not flap. PUT /admin/environments/{id}/domain-verification lets an operator
set or clear it by hand. The admin customer payload carries the column.

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01GdsYLasvsZ1zMvK7aKzVqP"
```

---

### Task 11: Public endpoints and the environment trait through the resolver

**Files:**
- Modify: `app/Http/Controllers/Api/BrandingController.php:664-705, 1223-1240`, `app/Http/Middleware/BrandingMiddleware.php:27-52`, `app/Http/Controllers/Api/EnvironmentController.php:636-672`, `app/Http/Controllers/Api/SubscriptionController.php:36-68`, `app/Http/Controllers/Api/ProductLandingPageController.php:282-300`, `app/Http/Controllers/Api/LandingPagePopupController.php:273-286`, `app/Http/Controllers/Api/LegalPageController.php:573-606`, `app/Http/Controllers/Api/ThirdPartyServiceController.php:360-390`, `app/Traits/BelongsToEnvironment.php:80-168`
- Test: `tests/Feature/Tenancy/PublicEndpointsOnSharedHostTest.php`, `tests/Feature/Tenancy/BelongsToEnvironmentResolutionTest.php`

**Interfaces:**
- Consumes: `EnvironmentResolver::resolve()`, `explicitEnvironment()`, request attribute `tenancy.context`.
- Produces: `GET /branding/public?environment_id=N`, `GET /environment/status?environment_id=N`, `GET /subscription/current?environment_id=N` work on the shared host; no `LIKE` lookups remain; `BelongsToEnvironment::detectEnvironmentId()` has no fallback tenant.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/Tenancy/PublicEndpointsOnSharedHostTest.php`:

```php
<?php

namespace Tests\Feature\Tenancy;

use App\Models\Branding;
use App\Models\Environment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * On the shared host the hostname identifies nobody, so public endpoints take
 * an explicit identifier. On a tenant host the host still wins.
 */
class PublicEndpointsOnSharedHostTest extends TestCase
{
    use RefreshDatabase;

    private function branded(array $attributes = []): Environment
    {
        $environment = Environment::factory()->create($attributes);
        Branding::withoutGlobalScopes()->forceCreate([
            'environment_id' => $environment->id,
            'company_name' => 'Acme Academy',
            'primary_color' => '#123456',
            'is_active' => true,
        ]);

        return $environment;
    }

    public function test_public_branding_resolves_by_environment_id_on_the_shared_host(): void
    {
        $environment = $this->branded();

        $this->getJson('/api/branding/public?environment_id='.$environment->id, ['X-Frontend-Domain' => 'app.getkursa.space'])
            ->assertOk()
            ->assertJsonPath('data.company_name', 'Acme Academy')
            ->assertJsonPath('environment.id', $environment->id);
    }

    public function test_public_branding_ignores_the_identifier_on_a_tenant_host(): void
    {
        $host = $this->branded(['primary_domain' => 'acme.test']);
        $other = $this->branded();

        $this->getJson('/api/branding/public?environment_id='.$other->id, ['X-Frontend-Domain' => 'acme.test'])
            ->assertOk()
            ->assertJsonPath('environment.id', $host->id);
    }

    public function test_public_branding_refuses_an_inactive_identifier(): void
    {
        $environment = $this->branded(['is_active' => false]);

        $this->getJson('/api/branding/public?environment_id='.$environment->id, ['X-Frontend-Domain' => 'app.getkursa.space'])
            ->assertStatus(404);
    }

    public function test_environment_status_and_popups_accept_the_identifier_on_the_shared_host(): void
    {
        $environment = $this->branded();

        $this->getJson('/api/environment/status?environment_id='.$environment->id, ['X-Frontend-Domain' => 'app.getkursa.space'])
            ->assertOk()
            ->assertJsonPath('data.id', $environment->id);

        $this->getJson('/api/branding/public/popups?domain='.$environment->id, ['X-Frontend-Domain' => 'app.getkursa.space'])
            ->assertOk();
    }
}
```

If `Branding` requires more columns than the four above to be created, add them from `database/factories/BrandingFactory.php` (`Branding::factory()->create(['environment_id' => …])` is acceptable when the factory exists).

`tests/Feature/Tenancy/BelongsToEnvironmentResolutionTest.php`:

```php
<?php

namespace Tests\Feature\Tenancy;

use App\Models\Environment;
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
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --compact tests/Feature/Tenancy/PublicEndpointsOnSharedHostTest.php tests/Feature/Tenancy/BelongsToEnvironmentResolutionTest.php`
Expected: FAIL — branding by id on the shared host 404s; fallback returns the first environment.

- [ ] **Step 3: `BrandingController::getPublicBranding()` and `getPublicLandingPage()`**

Replace lines 666-705 (the header parsing and the `$environment = Environment::where(...)` block) with:

```php
        $resolver = app(EnvironmentResolver::class);
        $context = $resolver->resolve($request);
        $domain = $context->host;
        $environment = $context->environment ?? ($context->source === EnvironmentContext::SOURCE_NONE
            ? $resolver->explicitEnvironment($request)
            : null);
```

and add `use App\Support\Tenancy\EnvironmentContext; use App\Support\Tenancy\EnvironmentResolver;`. Leave the branding lookup and the legacy `custom_domain` fallback below it untouched. In `getPublicLandingPage()` (around line 1235) replace the `Environment::where('primary_domain', $domain)->orWhere('primary_domain', 'LIKE', ...)` lookup with the same five lines (the `?domain=` query is honoured through `explicitEnvironment()`).

- [ ] **Step 4: `BrandingMiddleware`**

Replace lines 27-52 (from `$environmentId = null;` through the closing of `if (! $environmentId) {...}`) with:

```php
        $context = $request->attributes->get(EnvironmentResolver::REQUEST_ATTRIBUTE);
        $environmentId = $context instanceof EnvironmentContext ? $context->environment?->id : null;
```

with both imports; delete `isValidDomain()` if nothing else uses it.

- [ ] **Step 5: `EnvironmentController::status()` and `SubscriptionController::current()`**

In `status()`, replace lines 640-666 (from `// Try to get environment from domain first` through the `Environment::where('primary_domain', $domain)->orWhere('additional_domains', 'like', ...)->first();`) with:

```php
            $resolver = app(EnvironmentResolver::class);
            $context = $resolver->resolve($request);
            $domain = $context->host;
            $environment = $context->environment
                ?? ($context->source === EnvironmentContext::SOURCE_NONE ? $resolver->explicitEnvironment($request) : null);
```

Keep the "user's first environment" fallback and the `$exemptedDomains` check that follow. In `SubscriptionController::current()`, replace the header parsing and `Environment::where(...)` block (lines 39-63) with the same five lines and then `if ($environment) { $user = $environment->user; }` as before (the previous code did not filter on `is_active`; the resolver does, which is the intended tightening).

- [ ] **Step 6: The three landing-page style resolvers**

`ProductLandingPageController::resolveEnvironment()`: keep the `?domain=` branch, then replace the `$host ...` lookup with:

```php
        return app(EnvironmentResolver::class)->resolve($request)->environment;
```

`LandingPagePopupController::publicPopups()`: replace lines 273-286 with:

```php
        $resolver = app(EnvironmentResolver::class);
        $context = $resolver->resolve($request);
        $environment = $context->environment
            ?? ($context->source === EnvironmentContext::SOURCE_NONE ? $resolver->explicitEnvironment($request) : null);
```

`LegalPageController::detectEnvironmentFromRequest()`: body becomes the same four lines followed by `return $environment?->id;`.
`ThirdPartyServiceController::getWhatsAppConfig()`: replace lines 360-390 with the same four lines.

Add the two imports to each file.

- [ ] **Step 7: `BelongsToEnvironment::detectEnvironmentId()`**

Replace `detectEnvironmentId()` and delete `detectEnvironmentFromDomain()`:

```php
    /**
     * The environment new rows are stamped with: the resolved request context
     * first, then an explicit environment_id input (console and queue callers
     * pass one), else null. There is deliberately no fallback tenant.
     */
    public static function detectEnvironmentId()
    {
        $request = request();
        $context = $request?->attributes->get(EnvironmentResolver::REQUEST_ATTRIBUTE);

        if ($context instanceof EnvironmentContext && $context->resolved()) {
            return $context->environment->id;
        }

        if ($request && $request->has('environment_id')) {
            return $request->input('environment_id');
        }

        if (session()->has('current_environment_id')) {
            return session('current_environment_id');
        }

        return null;
    }
```

with imports `use App\Support\Tenancy\EnvironmentContext; use App\Support\Tenancy\EnvironmentResolver;`. Remove the now-unused `Log` import if nothing else in the trait uses it.

- [ ] **Step 8: Run the tests**

Run: `php artisan test --compact tests/Feature/Tenancy tests/Feature/PublicBrandingTest.php tests/Feature/PublicEnvironmentApiTest.php tests/Feature/Environments tests/Feature/Api/ProductLandingPagePublicTest.php`
Expected: PASS

- [ ] **Step 9: Format and commit**

```bash
vendor/bin/pint --dirty --format agent
git add app/Http/Controllers/Api/BrandingController.php app/Http/Middleware/BrandingMiddleware.php app/Http/Controllers/Api/EnvironmentController.php app/Http/Controllers/Api/SubscriptionController.php app/Http/Controllers/Api/ProductLandingPageController.php app/Http/Controllers/Api/LandingPagePopupController.php app/Http/Controllers/Api/LegalPageController.php app/Http/Controllers/Api/ThirdPartyServiceController.php app/Traits/BelongsToEnvironment.php tests/Feature/Tenancy
git commit -m "refactor(tenancy): route every public endpoint and the environment trait through the resolver

Five copies of header parsing, three LIKE '%host%' lookups and a fallback to
the first active environment are gone. Public endpoints resolve by host, then
by an explicit environment_id or domain identifier so they work on the shared
host; authenticated requests never consult the identifier.

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01GdsYLasvsZ1zMvK7aKzVqP"
```

---

### Task 12: Shared hosts known to the registry; marketplace payloads carry the effective URL

**Files:**
- Modify: `app/Support/TenantDomainRegistry.php:44-68`, `app/Http/Controllers/Api/SessionAuthController.php:389-394`, `routes/api.php:279-286`
- Test: `tests/Feature/Tenancy/MarketplaceEnvironmentUrlTest.php`

**Interfaces:**
- Produces: `environment.url` on `POST /session/marketplace-token` and on the marketplace branch of `GET /user`; shared hosts in `TenantDomainRegistry::getAllowedHosts()`.

- [ ] **Step 1: Write the failing test**

```php
<?php

namespace Tests\Feature\Tenancy;

use App\Models\Environment;
use App\Models\User;
use App\Support\TenantDomainRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketplaceEnvironmentUrlTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_marketplace_user_payload_carries_the_effective_environment_url(): void
    {
        $owner = User::factory()->create();
        $environment = Environment::factory()->create(['owner_id' => $owner->id, 'primary_domain' => 'acme.getkursa.space']);
        $token = $owner->createToken('marketplace-auth', ['marketplace'])->plainTextToken;

        $this->getJson('/api/user', ['Authorization' => 'Bearer '.$token])
            ->assertOk()
            ->assertJsonPath('environment.primary_domain', 'acme.getkursa.space')
            ->assertJsonPath('environment.url', 'https://app.getkursa.space');
    }

    public function test_the_registry_knows_the_shared_hosts(): void
    {
        $hosts = TenantDomainRegistry::getAllowedHosts();

        $this->assertContains('app.getkursa.space', $hosts);
        $this->assertContains('www.app.getkursa.space', $hosts);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --compact tests/Feature/Tenancy/MarketplaceEnvironmentUrlTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement**

In `TenantDomainRegistry::getDevHosts()` prepend `...array_map('strtolower', (array) config('tenancy.shared_hosts', [])),` as the first entries of the returned array.

In `SessionAuthController::marketplaceToken()` and in the marketplace branch of the `/user` closure in `routes/api.php`, add `'url' => \App\Support\Tenancy\TenantUrl::base($environment),` (respectively `$ownedEnv`) next to `'primary_domain'`.

- [ ] **Step 4: Run, format, commit**

Run: `php artisan test --compact tests/Feature/Tenancy/MarketplaceEnvironmentUrlTest.php tests/Feature/ConfigureTenantCorsAndSanctumTest.php`
Expected: PASS

```bash
vendor/bin/pint --dirty --format agent
git add app/Support/TenantDomainRegistry.php app/Http/Controllers/Api/SessionAuthController.php routes/api.php tests/Feature/Tenancy/MarketplaceEnvironmentUrlTest.php
git commit -m "feat(tenancy): expose the effective environment URL to the marketplace and register the shared hosts

The marketplace builds its dashboard link from primary_domain, which is dead
for a pending tenant. environment.url carries the TenantUrl base so the
marketplace can switch to it. The shared hosts join the tenant domain
registry so the admin-domain gate and CORS narrowing treat them as known.

Co-Authored-By: Claude Fable 5.1 <noreply@anthropic.com>
Claude-Session: https://claude.ai/code/session_01GdsYLasvsZ1zMvK7aKzVqP"
```

---

### Task 13: Whole-suite verification

**Files:** none new.

- [ ] **Step 1: Run the entire suite**

Run: `php artisan test --compact`
Expected: every test green, or the same pre-existing failures recorded as the baseline in Task 3 Step 7 and none introduced by this plan. List any remaining failure with its reason in the task report.

- [ ] **Step 2: Confirm the guard is in log mode and the route walk holds**

Run: `php artisan config:show tenancy.environment_guard` → `log`.
Run: `php artisan test --compact --filter=test_every_authenticated_route_is_guarded_or_exempt` → PASS.

- [ ] **Step 3: Format everything once more**

Run: `vendor/bin/pint --format agent` and commit any formatting-only change as `style: pint`.
