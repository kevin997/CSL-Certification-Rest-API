# Shared App Host Tenancy — Design

**Status:** design approved in discussion, awaiting spec review
**Date:** 2026-09-01
**Repos:** `CSL-Certification-Rest-API` (system of record), `CSL-Certification`, `CSL-Sales-Website`, `kursa-systtem-admin`, `CSL-DevOps` (DNS doc only)
**Plan:** `docs/superpowers/plans/2026-09-01-shared-app-host-tenancy.md` (to be written)

## 1. Problem

A new customer's academy is provisioned with a primary domain (`acme.csl-brands.com` today) that
does not resolve until someone adds a DNS record and attaches the domain to the Vercel project by
hand. Until then the emailed password-set link is dead, and the "Continue to sign in" button on the
sales site sends the user to the identity provider, which refuses to render without a marketplace
redirect. The owner, their learners, and their collaborators have nowhere to go.

The product decision: after onboarding, users are taken to a **shared host**, `app.getkursa.space`
(`www.app.getkursa.space` redirects to it), and every API operation must work for an owner,
learner, or collaborator who is on that host rather than on the tenant's own domain.

The difficulty: the platform is fully multi-tenant and the tenant is identified **only** by the
browser hostname. On the shared host the hostname identifies nobody.

## 2. Findings that constrain the design

Verified against the code on 2026-09-01. Line numbers are approximate.

**Tenant equals hostname, everywhere.**
- The frontend sends `X-Frontend-Domain: window.location.host` on every request
  (`CSL-Certification/lib/api.ts:261`, `lib/api-server.ts:74`).
- `DetectEnvironment` (global middleware, `bootstrap/app.php:62`) maps that header, then `Origin`,
  then `Referer`, then the API host to an environment via `Environment::findByDomain()`, then
  writes `session(['current_environment_id' => …])` and `$request->merge(['environment' => …])`
  (`app/Http/Middleware/DetectEnvironment.php:19-150`).
- `EnvironmentScope` filters every table with an `environment_id` column on that session value
  (`app/Scopes/EnvironmentScope.php:22`). 42 files read the session value directly.
- Bearer tokens carry an `environment_id:{id}` ability minted at login after a membership check
  (`app/Http/Controllers/Api/TokenController.php:182-193`); `/user` and `/session/user` read it
  (`routes/api.php:236-330`).

**An unresolved host fails open.** When no environment matches, the scope is not applied and
queries run unscoped. `BelongsToEnvironment::detectEnvironmentId()` goes further and falls back to
`Environment::where('is_active', true)->first()` (`app/Traits/BelongsToEnvironment.php:103-111`).
Licence gates read `$request->get('environment')` and pass when it is absent
(`app/Http/Middleware/CheckPlanFeature.php:40-44`). Vercel preview hosts already exercise this path.

**Five independent resolvers disagree.** `DetectEnvironment`, `BelongsToEnvironment`,
`BrandingMiddleware`, `BrandingController::getPublicBranding` (`:664-705`), and
`ProductLandingPageController::resolveEnvironment` (`:282-300`, with a `LIKE '%host%'` fallback)
each re-implement header precedence with different `is_active` handling and caching.
`findByDomain()` has an OR/AND precedence bug that lets an inactive environment match on
`primary_domain` (`app/Models/Environment.php:120-128`).

**There is no subdomain in the data model.** `environments.primary_domain` is unique;
`additional_domains` is a JSON array. A "subdomain" is a label with `.csl-brands.com` appended,
duplicated in `LicenceService::formatDomain()` (`:743-754`) and two legacy onboarding controllers,
and validated by a hardcoded regex in `OnboardingController::validateDomain()` (`:291`). The sales
wizard hardcodes the same suffix (`CSL-Sales-Website/components/onboarding/steps/DomainPreferences.tsx:127,298,460`).

**`primary_domain` is treated as the tenant's identity and URL.** About twenty mail, notification,
and controller sites build `'https://' . $environment->primary_domain . $path` (listed in §5.2.7).
The system admin app displays it as the environment's identity on four screens and edits it as free
text. The marketplace microservice snapshots it into `sellers.environment_url` and the marketplace
SPA turns it into the "Dashboard" link. Pointing pending tenants at the shared host through this
column is therefore both impossible (unique) and wrong.

**Subdomains are manual.** Neither `getkursa.space` (nameservers at Hostinger) nor
`csl-brands.com` (Hosteur) has a wildcard record. Each live tenant is a hand-added CNAME to the
same Vercel project. Vercel wildcard domains require Vercel nameservers. `app.getkursa.space` has
no DNS record yet. Nothing in `CSL-DevOps` or `CSL-Serverless-Functions` provisions DNS, TLS, or
proxy routes for tenants.

**The frontend already has most of the pieces.** Host-scoped storage (`lib/tenant-storage.ts`),
bearer-token mode for every host outside `csl-brands.com` (`lib/api.ts:27-35`, default of
`NEXT_PUBLIC_COOKIE_AUTH_ROOT_DOMAINS`), an academy picker (`app/academies/page.tsx`), one-time
cross-domain switch tokens (`app/auth/switch/page.tsx`, `AcademySwitchController`), storefront by
numeric id (`/storefront/{id}`), and password-set links that already carry `environment_id`.
It also has a hardcoded fallback to environment id 11 on unknown hosts (`lib/branding.ts:58-62`)
that redirects visitors into that environment's storefront (`components/public/home-client.tsx:76-80`).

**The onboarding handoff today.** `POST /onboarding/free|trial` provisions user, environment,
owner pivot, and emails a password-set link to `https://{primary_domain}/auth/reset-password?…`
(`LicenceService.php:467-509, 637-660`). Paid flows provision on settlement; the sales site polls
`GET /licence-checkouts/{uuid}/status`, which returns `environment_id` once paid. The sales site
then links to `${NEXT_PUBLIC_IDP_URL}/login` with nothing attached.

## 3. Decisions

| # | Decision | Rationale |
|---|---|---|
| D1 | Environment is bound at **login** on the shared host; the hostname stays authoritative on tenant hosts. | Reuses token abilities, the picker, and switch tokens. Path-prefixed tenants would need a tenant segment in every LMS route since Next.js has no dynamic base path. |
| D2 | After onboarding the user lands on the shared host **already signed in** via a one-time token. | Chosen by the product owner. Provisioning already stamps `email_verified_at`, so nothing enforced today is weakened (see §8). |
| D3 | New subdomains are composed under **`getkursa.space`**; base is configuration in both repos. Existing tenants keep their domains. | Matches the brand, the identity provider, the admin app, and the wildcard CORS and Sanctum rules already configured for `*.getkursa.space`. |
| D4 | Domain liveness is a **`domain_verified_at`** timestamp set by an **hourly probe** and overridable by the **system admin**. | Chosen by the product owner. The probe only sets; only an admin clears, so links never flap. |
| D5 | The shared host runs in **bearer-token mode** in the frontend, never cookie mode. | Bearer tokens carry the binding; the API host cannot share cookies with `getkursa.space` anyway (`ConfigureTenantCorsAndSanctum::canShareCookiesWithApiHost`). |
| D6 | Client-supplied environment identifiers are honoured **only on unauthenticated public endpoints** that already accept one. Authenticated requests use the binding, full stop. | Prevents a client picking another tenant by header or body field. |
| D7 | Tenant routes **fail closed** without a resolved environment, rolled out in log-only mode first. | The open failure mode is a pre-existing leak that the shared host would make the main path. |
| D8 | `www.app.getkursa.space` is a Vercel-level 301 to `app.getkursa.space`. Both are declared as shared hosts in the API. | One host keeps storage and cookies in one place. |

## 4. Vocabulary

- **Tenant host**: a hostname that `findByDomain()` maps to exactly one environment.
- **Shared host**: a hostname listed in `config('tenancy.shared_hosts')`.
- **Binding**: the environment id an authenticated principal is bound to: the `environment_id:{id}`
  token ability (bearer), or `session('current_environment_id')` as written at login (cookie).
- **Environment context**: the result of resolution for one request: the environment (or null),
  the source (`host`, `binding`, or `none`), and the frontend host that was examined.
- **Domain live**: `environments.domain_verified_at` is not null.
- **Effective base URL**: `https://{primary_domain}` when live, otherwise `https://{shared_host}`.

## 5. Design

### 5.1 Overview

```
tenant host (acme.getkursa.space)            shared host (app.getkursa.space)
────────────────────────────────            ─────────────────────────────────
host → environment (unchanged)              host → nothing
                                            token ability / login session → environment
public pages: by host                       public pages: by explicit id in the URL
links out: https://acme.getkursa.space/…    links out: https://app.getkursa.space/…?environment_id=N
```

One resolver produces the environment context for every request. One guard refuses tenant routes
without one. One URL builder decides which host a link uses. One domain helper composes and
validates subdomains. Login and switch bind the environment on the shared host using the flows
that already exist.

### 5.2 API (`CSL-Certification-Rest-API`)

#### 5.2.1 Configuration: `config/tenancy.php` (new)

```php
return [
    // Hosts that serve every tenant. Lowercase, no scheme, optional port.
    'shared_hosts' => explode(',', env('TENANCY_SHARED_HOSTS', 'app.getkursa.space,www.app.getkursa.space')),
    // The canonical shared host used when building links.
    'shared_host' => env('TENANCY_SHARED_HOST', 'app.getkursa.space'),
    // Base under which new KURSA subdomains are composed (D3).
    'subdomain_base' => env('TENANCY_SUBDOMAIN_BASE', 'getkursa.space'),
    // Bases still accepted as "one of ours" for existing tenants.
    'legacy_subdomain_bases' => ['csl-brands.com', 'cfpcsl.com'],
    // Platform frontends that resolve to a fixed environment (moved from DetectEnvironment).
    'host_aliases' => [
        'csl-certification.vercel.app' => 'learning.csl-brands.com',
        'learning.cfpcsl.com' => 'learning.csl-brands.com',
        'csl-certification-git-develop-kevin997s-projects.vercel.app' => 'learning.csl-brands.com',
    ],
    // 'log' records would-be refusals; 'enforce' returns 403 (D7).
    'environment_guard' => env('TENANCY_ENVIRONMENT_GUARD', 'log'),
    // Domain probe (D4).
    'domain_probe' => [
        'http_timeout_seconds' => 5,
    ],
    // Lifetime of the one-time sign-in token minted at onboarding (D2).
    'onboarding_switch_token_ttl_seconds' => 300,
];
```

`host_aliases` replaces the bidirectional `strpos` loop in `DetectEnvironment.php:66-87` with exact
matching. The substring behaviour is a latent bug (a short header value that is a substring of a
known domain matches), not a feature anyone relies on.

#### 5.2.2 Resolver: `App\Support\Tenancy\EnvironmentResolver`

Lives beside `app/Support/Retention/` as `app/Support/Tenancy/`. Pure with respect to the
session: it reads the request and the Sanctum guard, and returns an `EnvironmentContext`
value object `{ ?Environment $environment, string $source, string $host }`.

```
resolve(Request $request): EnvironmentContext
  host = frontendHost(request)             // X-Frontend-Domain, else Origin host, else Referer host, else API host; lowercased
  if isSharedHost(host):
      env = bindingEnvironment(request)    // see below
      return context(env, env ? 'binding' : 'none', host)
  env = Environment::findActiveByDomain(host)
      ?? Environment::findActiveByDomain(config('tenancy.host_aliases')[host] ?? '')
  return context(env, env ? 'host' : 'none', host)

bindingEnvironment(request): ?Environment
  user = Auth::guard('sanctum')->user()    // explicit guard: the default guard is 'web' (config/auth.php:17) and cannot see a bearer token from global middleware
  if user is null: return null
  token = user->currentAccessToken()
  if token is a PersonalAccessToken:
      id = ability 'environment_id:{id}' or null
  else if session()->isStarted() and session()->has('current_environment_id'):
      id = session('current_environment_id')
  else: return null
  return Environment::findActive(id)       // membership was verified when the binding was minted
```

`Environment::findActive(int $id)` is `where('id', $id)->where('is_active', true)->first()`.

`Environment::findActiveByDomain()` is `findByDomain()` with the OR/AND precedence fixed:
`where(fn ($q) => $q->where('primary_domain', $d)->orWhereJsonContains('additional_domains', $d))->where('is_active', true)`.
The existing 300-second cache and the model's `saved`/`deleted` invalidation hooks are kept.
`findByDomain()` is retained as an alias so `EnvironmentPaymentConfigService.php:179-181` can drop
its workaround in a later cleanup.

**What consumes the resolver.**
- `DetectEnvironment` calls it, then does exactly what it does today with the result: shares it
  with views, merges it into the request, writes `session(['current_environment_id' => …])`,
  runs the heartbeat, and stamps the `environment` key on JSON responses. On the shared host with
  no binding it does not write the session key and does not clear it either (a cookie-mode login
  has already written the right value). The auto-attach block (`:116-131`) is unchanged; on the
  shared host the bound user is already a member, so it is a no-op there.
- `BelongsToEnvironment::detectEnvironmentId()` becomes: resolver result, else request input
  `environment_id` (kept for console and queue callers that pass it explicitly), else null. The
  "first active environment" fallback and the private domain re-implementation are deleted.
- `BrandingMiddleware`, `BrandingController::getPublicBranding`,
  `ProductLandingPageController::resolveEnvironment`, `LandingPagePopupController`,
  `EnvironmentController::status`, and `ValidationController` use the resolver for the
  host-derived environment and `Environment::resolveByIdentifier()` for an explicit identifier.
  The `LIKE '%host%'` fallbacks are deleted.

`DetectEnvironment` stays in the global stack. Bearer resolution needs no session, and the shared
host is bearer-only (D5). It cannot move into the `api` group because `routes/api-public.php` is
registered outside that group (`bootstrap/app.php:29-32`) and `web` payment views rely on the
shared `$environment`.

#### 5.2.3 Guard: `App\Http\Middleware\EnsureEnvironmentResolved`, alias `environment.required`

Runs after `auth:sanctum`. Passes when `$request->get('environment')` is set, or when the
authenticated user's system role is `admin`, `super_admin`, or `sales_agent` (platform staff work
without a binding from `manager.getkursa.space`). Otherwise:

- mode `log`: `Log::warning('tenancy.environment_required', [route, host, user_id])` and pass;
- mode `enforce`: `403 { "code": "environment_required", "message": "No academy selected. Sign in to an academy or open it from its own address." }`.

Clients branch on `code`, never on the message.

**Where it applies.** Every `auth:sanctum` group in `routes/api.php`, `routes/learner.php`, and
`routes/environment-auth.php`, except the identity and membership routes that legitimately run
without a binding: `/user`, `/session/user`, `/user/environments`, `/environments/user`,
`/environments/{id}/join`, `/environments/{id}/leave`, `/auth/academy-switch-token`,
`/session/logout`, `/session/marketplace-token`, `DELETE /tokens`, `/logout`,
`/broadcasting/auth`, `/environment-users/setup-account`, and the `admin/*` and
`admin/sales/*` groups. The implementation plan carries the exact per-group list; a feature test
walks every registered route and asserts each `auth:sanctum` route either has the guard or is on
the exemption list, in the style of `ValidateDomainTest::test_every_route_middleware_alias_is_registered`.

#### 5.2.4 Login binding (`POST /tokens`, `POST /session/login`)

Both controllers delegate the "which environment" decision to one method,
`EnvironmentResolver::bindingForLogin(User $user, ?int $requested, string $host): LoginBinding`,
after credentials have been verified and the admin-domain gate has run. `LoginBinding` is
`{ ?int $environmentId, bool $requiresSelection, array $environments }`.

```
memberships = owned ∪ pivot (same query as EnvironmentMembershipController::myEnvironments)

if requested is set:
    require requested ∈ memberships           // existing behaviour, existing 'Invalid credentials' response
    return bind(requested)

if host is a tenant host and hostEnv ∈ memberships:
    return bind(hostEnv)                       // new default; previously the token had no environment ability

if host is a shared host:
    if |memberships| == 1: return bind(the one)
    if |memberships| >  1: return select(memberships)   // environmentId null, requiresSelection true
    if user is platform staff: return bind(null)
    return refuse 403 { code: 'no_environment' }

return legacy behaviour                        // teacher auto-resolve on session login; no ability on token login
```

Response additions on both endpoints: `requires_environment_selection` (bool) and, when true,
`environments` in the exact shape of `GET /user/environments`. Token abilities and
`session('current_environment_id')` are written from `LoginBinding` exactly as today.

#### 5.2.5 Switching, including in place on the shared host

`AcademySwitchController::generateSwitchToken` keeps its membership check and cache token, and
builds `redirect_url` with `TenantUrl::to($target, '/auth/switch', ['token' => $token])` instead
of `primary_domain` directly (`:113-116`). When the target's domain is not live, the URL lands on
the shared host: the existing `/auth/switch` page exchanges the token there and the user is now
bound to the target on the same host. No new endpoint is needed for "switch in place".

Token minting moves into `App\Support\Tenancy\SwitchTokenIssuer::issue(User, Environment, int $ttlSeconds): string`
so onboarding (§5.2.6) can mint the same kind of token. `validateSwitchToken` adds
`is_account_setup` for the target environment to its response, and `GET /user` adds it for the
bound environment; today only `POST /tokens` returns it, so a user who arrived through a token
exchange or reloaded the page is never prompted to set a password.

#### 5.2.6 Onboarding: auto sign-in (D2)

- `LicenceService::provisionEnvironmentFromPayload()` mints a switch token for the owner with
  `config('tenancy.onboarding_switch_token_ttl_seconds')` and returns it alongside the
  environment. `LicenceController::onboard()` adds `redirect_url` to the 201 body:
  `TenantUrl::to($environment, '/auth/switch', ['token' => …])`. The password-set email is still sent.
- Paid flows: new `POST /licence-checkouts/{uuid}/sign-in-link` (same throttle as `/pay`). When
  the checkout is `paid` and its environment exists, it mints a fresh one-time token for the owner
  and returns `{ redirect_url }`; otherwise `409 { code: 'checkout_not_ready' }`. It is a `POST`
  and separate from the polled `status` so no one-time secret is minted on every poll.
- The owner pivot's `is_account_setup` stays `false` until a password is set through the emailed
  link or the existing `PUT /environment-users/setup-account`, which writes the global password.

#### 5.2.7 URL builder: `App\Support\Tenancy\TenantUrl`

```php
TenantUrl::base(Environment $e): string      // https://{primary_domain} when live, else https://{shared_host}; http for localhost/127.0.0.1 hosts and APP_ENV=local
TenantUrl::to(Environment $e, string $path, array $query = []): string
                                             // base + path + query; on the shared host adds environment_id={id} unless already present
TenantUrl::isLive(Environment $e): bool      // domain_verified_at !== null
```

Every site that concatenates `primary_domain` into a URL is rewritten to call `TenantUrl::to()`:
`LicenceService.php:587,601,655`, `AppServiceProvider.php:105-127`,
`Admin/PasswordLinkController.php:276`, `Notifications/EnvironmentPasswordReset.php:165`,
`Notifications/EnvironmentCreatedNotification.php:142`, `Notifications/EnvironmentAccountCreated.php:77`,
`Notifications/CertificateIsuued.php:72`, `Notifications/OrderCreated.php:63`,
`Mail/EnvironmentSetupMail.php:81-91`, `Mail/OrderConfirmation.php:42`, `Mail/DigitalProductDelivery.php:47`,
`Mail/LearnerWeeklyDigest.php:47`, `Mail/InstructorWeeklyDigest.php:47`,
`Mail/ProductSubscriptionExpiringReminder.php:44`, `Mail/Licensing/TrialReminderMail.php:60`,
`Mail/Licensing/LicenceRenewalWarningMail.php:47`, `Auth/AcademySwitchController.php:113-116`,
`Support/Retention/RetentionLinks.php:25-65`, and the `data-primary-domain` attribute in
`resources/views/payment/*.blade.php`. Gateway callback URLs (`route()` on `APP_URL`) are not
tenant links and are untouched. The certificate QR base is global and untouched.

The `isSubdomain()` helpers in `EnvironmentCreatedNotification` and `EnvironmentSetupMail`, which
test for `.csl-brands.com`, are replaced by `TenantDomain::isKursaSubdomain()` (§5.2.9).

Emails that name the academy address say: "Your academy is available at
`https://app.getkursa.space` now. Once `acme.getkursa.space` is live it will open there." The
sentence is dropped when the domain is live.

#### 5.2.8 Domain verification (D4)

- Migration `add_domain_verified_at_to_environments_table`: nullable `timestamp domain_verified_at`,
  indexed. Backfill: every existing environment gets `domain_verified_at = created_at`, because
  they are reachable today by definition and a null would silently move their emails to the shared
  host on deploy. Wrapped in the repo's `MigrationHelper::columnExists` guard like its siblings.
- `App\Support\Tenancy\DomainProbe` interface with `isLive(string $host): bool`; the production
  implementation resolves an `A` or `CNAME` record with `dns_get_record` and issues an HTTPS `HEAD`
  to `https://{host}/` with the configured timeout, treating any response below 500 as live.
  Tests bind a fake.
- Command `environments:verify-domains`: for every active environment with a null
  `domain_verified_at`, ask the probe; on success set the timestamp and log. Never clears.
  Scheduled `hourly()` in `routes/console.php` next to `ProcessAbandonedOrdersCommand`.
- Admin override: `PUT /admin/environments/{id}/domain-verification { "verified": true|false }`
  in the existing `admin` group, sets or clears the timestamp, writes an audit-log entry using the
  existing audit mechanism the admin app reads. `GET /environments/{id}` and the admin customers
  payload include `domain_verified_at`.
- Cache: setting or clearing the timestamp calls the model's existing `saved` hook, which already
  forgets `env_by_domain:*`. `TenantUrl` reads the model, not a cache, so no new invalidation.

#### 5.2.9 Subdomain composition: `App\Support\Tenancy\TenantDomain`

```php
TenantDomain::compose(string $type, string $value): string   // 'subdomain' → label . '.' . base; 'custom' → host as given, scheme stripped, lowercased
TenantDomain::isKursaSubdomain(string $host): bool           // suffix ∈ {base} ∪ legacy_subdomain_bases
TenantDomain::labelRules(): array                            // /^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])?$/, not 'www', not 'app', not in a reserved list
```

Replaces `LicenceService::formatDomain()`, `StandaloneOnboardingController::formatDomain()`,
`DemoOnboardingController::formatDomain()`, and the regex in `OnboardingController::validateDomain()`.
`POST /onboarding/validate-domain` with `type=subdomain` accepts either a bare label or a fully
qualified host whose suffix is the configured base or a legacy base; it validates the label,
composes with the configured base, checks uniqueness against `primary_domain` and
`additional_domains`, and returns `{ available, domain, suggestions }` where `domain` is the
composed host. The reserved list holds `app`, `www`, `api`, `idp`, `manager`, `admin`, `mail`,
`marketplace`, `ads`, `marketing`, `sales`.

#### 5.2.10 Public endpoints on the shared host

Public endpoints keep working on tenant hosts by host. On the shared host each needs an explicit
identifier, which they already accept or gain:

| Endpoint | Identifier on the shared host |
|---|---|
| `GET /branding/public` | `?environment_id=N` (new) or `?domain=` (existing); active only |
| `GET /branding/public/landing-page`, `GET /branding/public/popups`, `GET /products/public/landing-page`, `GET /legal-pages/public/{type}` | `?domain=` already goes through `resolveByIdentifier()`, which accepts a numeric id; documented, not changed |
| `/storefront/{environmentId}/...` | numeric id in the path, unchanged |
| `/environment/status`, `/subscription/current` | `?environment_id=N` (new) via the resolver-plus-identifier pattern of §5.2.2 |

#### 5.2.11 Marketplace and system-admin payloads

`SessionAuthController::marketplaceToken` and the marketplace branch of `GET /user` add
`environment.url = TenantUrl::base($env)` next to `primary_domain`. The marketplace microservice
and SPA keep reading `primary_domain` until they are updated (§11); nothing breaks, the link is
just a bare host until then. `TenantDomainRegistry::getAllowedHosts()` adds the shared hosts to
its static list so the admin-domain gate and CORS narrowing treat them as known.

#### 5.2.12 Responses stamped by `DetectEnvironment`

The `environment` key added to JSON responses gains `source` (`host`, `binding`, `none`) and
`domain_verified_at`. `detected_domain` and `header_domain` stay for compatibility.

### 5.3 Frontend (`CSL-Certification`)

#### 5.3.1 `lib/tenancy.ts` (new)

```ts
export const SHARED_HOSTS: string[]                      // from NEXT_PUBLIC_SHARED_APP_HOSTS, default 'app.getkursa.space,www.app.getkursa.space'
export const isSharedHost = (host = window.location.hostname): boolean
export const readEnvironmentIdFromUrl = (): string | null // ?environment_id, digits only
```

`isCookieAuthDomain()` returns `false` when `isSharedHost()` is true, before the root-domain check
(D5). `NEXT_PUBLIC_COOKIE_AUTH_ROOT_DOMAINS` keeps its default. One more variable,
`NEXT_PUBLIC_SALES_SITE_URL` (default `https://www.getkursa.space`), is read by the "Find your
academy" panel (§5.3.4) and the academy picker's "Create campus" link (§5.3.3).

#### 5.3.2 Environment identity on the shared host

- `BrandingProvider` on a shared host: environment id = URL param, else `tenantStorage 'environment-id'`.
  When present it seeds storage and fetches `GET /branding/public?environment_id=N`. When absent it
  renders neutral KURSA defaults with `environment = null`. Cookie paint is skipped on the shared
  host because the cookie name is host-scoped and would paint the previous academy after a switch.
  On tenant hosts the provider is unchanged.
- `EnvironmentSettings.id` becomes nullable. `defaultEnvironmentSettings` (`lib/branding.ts:58-62`)
  loses its `id: 11` and `name: "Environment Two"`; every consumer of `environment.id` handles null.
- The request interceptor's cookie fallback (`lib/api.ts:187,214`) uses the same sanitised cookie
  name the provider writes; today the names differ and the branch is unreachable.
- Storage stays host-scoped. On the shared host that means one bound academy per browser, which
  is the intended workspace model.

#### 5.3.3 Login, selection, switch

- Login page: `environmentId` comes from `useBranding().environment?.id`, which on the shared host
  is the seeded id or undefined. After login, if the response has
  `requires_environment_selection`, store the token and navigate to `/academies`.
- `/academies`: unchanged. `handleSwitch` follows `redirect_url`; when the target's domain is not
  live that URL is on the same host and `/auth/switch` rebinds in place. The dead "Create campus"
  button links to the sales site's onboarding anchor.
- `/auth/switch`: after storing the new token it clears branding cookies (already) and, on a
  shared host, sets `tenantStorage 'environment-id'` before redirecting (already), so the next
  `BrandingProvider` mount fetches the right academy.
- Password reset and forgot-password pages already read `environment_id`; on the shared host they
  seed storage from it.
- The password-setup modal (`components/learners/password-setup-modal.tsx`), mounted only in
  `LearnerLayout` today, is also mounted in the dashboard layout, and `checkAuth` stores
  `is_account_setup` from `GET /user`, so an owner who arrived through auto sign-in is prompted.
- A 403 with `code === 'environment_required'` is handled next to the 401 handler in `lib/api.ts`:
  clear `environment-id`, then navigate to `/academies` when authenticated, else `/auth/login`.

#### 5.3.4 Public pages on the shared host

- `HomeClient` with no environment and unauthenticated: render a "Find your academy" panel (sign
  in, and a link to `NEXT_PUBLIC_SALES_SITE_URL`, default `https://www.getkursa.space`) instead of
  redirecting into environment 11's storefront. With an environment it behaves as today.
- Every public fetch that passes the hostname as the tenant identifier passes the environment id
  instead when `isSharedHost()`: `getPublicBrandingSettings`, `getPublicLandingPage` (client and
  `lib/api-server.ts` callers via a `domain` param), popups, legal pages, and `app/sitemap.ts`
  and `app/robots.ts`, which return a minimal document on the shared host.
- `/storefront/[domain]` and `/lp/[slug]` already carry the identifier in the path.

#### 5.3.5 `proxy.ts`

No tenancy logic is added; the `www.app` redirect is done at Vercel (D8). The dead `host`
computation (`:112-113`) is removed since it misleads readers into thinking routing happens there.

### 5.4 Sales website (`CSL-Sales-Website`)

- New env vars: `NEXT_PUBLIC_APP_URL` (default `https://app.getkursa.space`),
  `NEXT_PUBLIC_SUBDOMAIN_BASE` (default `getkursa.space`). `NEXT_PUBLIC_IDP_URL` is no longer read
  by onboarding pages.
- Free and trial flows (`OnboardingWizard.handleFreeOrTrialSubmit`): after tracking events fire,
  if `result.redirect_url` is present, `window.location.assign(result.redirect_url)`. The
  completion panel remains the fallback when it is absent.
- Paid flow (`app/onboarding/status/[checkoutId]/page.tsx`): on `paid`, call
  `POST /licence-checkouts/{id}/sign-in-link` once and redirect. The button becomes "Open your
  academy" and falls back to `${NEXT_PUBLIC_APP_URL}/auth/login?environment_id=${environment_id}`
  from the status response if the link call fails. `app/onboarding/success/page.tsx` follows suit.
- `DomainPreferences`: the suffix shown and validated comes from `NEXT_PUBLIC_SUBDOMAIN_BASE`;
  validation sends the bare label with `type: 'subdomain'` so validation and creation agree on
  shape; `result.domain` from the API is the composed host. Clicking a suggestion re-validates.
- Copy (`lib/i18n/translations/{en,fr}.ts`): subdomain examples use the configured base; the
  completion copy says the academy opens at the shared host now and at its own address once live.

### 5.5 System admin (`kursa-systtem-admin`)

- `Environment` type gains `domain_verified_at: string | null`.
- Customers page: a "Domain live" badge next to `primary_domain`, and a toggle that calls
  `PUT /admin/environments/{id}/domain-verification` through the existing BFF proxy.
- Screens that identify environments by `primary_domain` are unchanged; the column keeps its
  meaning under this design.

### 5.6 Infrastructure (`CSL-DevOps`)

Manual, one time, before anything else ships: at Hostinger add `app` and `www.app` CNAMEs for
`getkursa.space` to `cname.vercel-dns.com`; in the Vercel project for `CSL-Certification` add
both domains and set `www.app.getkursa.space` to redirect to `app.getkursa.space`. Record both in
`CSL-DevOps/DNS_CONFIGURATION.md` under a new "KURSA tenant frontend" table, together with the
per-tenant CNAME instruction that operators follow today.

## 6. Walkthroughs

**A. Free signup.** Wizard posts to `/onboarding/free` with label `acme`. API composes
`acme.getkursa.space`, provisions, emails the password-set link built by `TenantUrl` (shared host,
because `domain_verified_at` is null), mints a 5-minute switch token, and returns `redirect_url`.
The wizard redirects. `/auth/switch` on the shared host exchanges the token for a bearer token
with `environment_id:N`, stores it, seeds `environment-id`, and lands the owner on `/dashboard`.
The password-setup modal opens on the dashboard because `is_account_setup` is false (§5.2.5, §5.3.3).

**B. A learner is invited before the domain is live.** The invite mail links to
`https://app.getkursa.space/auth/reset-password?token=…&email=…&environment_id=N`. The reset page
seeds storage with N, branding loads for N, the learner sets a password and signs in; the API
binds N because it was requested and the learner is a member.

**C. The domain goes live.** An operator adds the CNAME and the Vercel domain. Within the hour the
probe stamps `domain_verified_at`. New emails link to `https://acme.getkursa.space/…`. Users
already working on the shared host keep their tokens; `/academies` now redirects them to the
live domain when they switch to it.

**D. Member of three academies signs in on the shared host.** Login returns
`requires_environment_selection: true` with the three environments. The frontend shows
`/academies`. Choosing one generates a switch token whose `redirect_url` is on the shared host
(domain pending) or the academy's own domain (live). Either way the user ends up bound to it.

**E. Unknown host, for instance a Vercel preview.** The resolver returns `none`. In `log` mode
tenant routes pass and log; in `enforce` mode they return `environment_required`. The frontend
shows neutral branding and the "Find your academy" panel instead of environment 11.

## 7. Error handling

| Situation | Behaviour |
|---|---|
| Tenant route without environment, `enforce` | `403 { code: 'environment_required' }`; frontend goes to `/academies` or login |
| Login on shared host, zero memberships, not staff | `403 { code: 'no_environment' }` with a message pointing to the sales site |
| Requested environment not a membership | unchanged `422 credentials: Invalid credentials provided.` |
| Switch token expired or reused | unchanged `401 Invalid or expired switch token`; `/auth/switch` shows its error state with a login link |
| `sign-in-link` before payment settles or before provisioning | `409 { code: 'checkout_not_ready' }`; the status page keeps its fallback button |
| Domain probe throws (DNS timeout, TLS error) | treated as not live, logged at info, next hour retries |
| `environment_id` in a URL that does not exist or is inactive | branding request 404s; provider renders neutral defaults; login proceeds without a requested id |

## 8. Security considerations

- **Client-chosen environment.** `X-Frontend-Domain`, `environment_id` inputs, and the URL param
  are client-controlled. Under D6 they only ever select public data; the binding for authenticated
  requests comes from a token minted after a membership check, or from the login session.
- **Fail closed.** D7 turns the pre-existing unscoped path into a refusal. The `log` phase exists
  to find legitimate binding-less routes before enforcing.
- **Auto sign-in with an unverified email (D2).** Someone can already provision an environment
  under an email they do not own; today they cannot use it, under D2 they can, for an empty
  environment tied to that address. Accepted because `email_verified_at` is already stamped at
  provisioning, onboarding is throttled (`throttle:20,1`), the token is single-use and lives five
  minutes, `is_account_setup` stays false until the emailed link is used, and an admin can delete
  the environment. If this becomes a problem, gate publishing on `is_account_setup`.
- **One-time tokens.** 64 random characters, cache-backed, deleted on first use, 60 seconds for
  switches and 300 for onboarding. The `sign-in-link` endpoint is throttled like `/pay` and
  requires the checkout UUID, which is the same secret the status page already relies on.
- **Auto-attach in `DetectEnvironment`** (`:116-131`) remains as is; it is out of scope but noted
  as a follow-up because it lets an authenticated caller gain a pivot row on any tenant host they
  name.

## 9. Testing

**API** (PHPUnit, feature tests preferred per project convention; every change carries a test):
- `EnvironmentResolverTest`: tenant host resolves; inactive environment does not match on
  `primary_domain`; alias host resolves exactly, not by substring; shared host with a bearer
  binding resolves that environment; shared host with a session binding resolves; shared host with
  no principal returns `none`; unknown host returns `none`.
- `EnsureEnvironmentResolvedTest`: `log` passes and logs; `enforce` returns the stable code;
  platform roles pass; plus the route-walk test that every `auth:sanctum` route is guarded or
  exempted.
- `LoginBindingTest` for both endpoints: requested id (member, non-member); tenant host default;
  shared host single, multiple, none, staff.
- `AcademySwitchRedirectTest`: pending domain yields the shared-host URL with `environment_id`;
  live domain yields the tenant URL.
- `OnboardingSignInTest`: free and trial responses carry a usable one-time `redirect_url`;
  `sign-in-link` refuses before `paid`, succeeds once paid, and each token works once.
- `TenantUrlTest`, `TenantDomainTest` (compose with configured and legacy bases, label rules,
  reserved labels, validate-domain accepts label or FQDN).
- `VerifyDomainsCommandTest` with a fake probe: sets on success, leaves null on failure, never clears.
- `PublicBrandingByEnvironmentIdTest`; `TenantMailBrandingTest` extended to assert the shared-host
  link and sentence while pending and the tenant link when live.
- `DetectEnvironmentTest` (none exists today): response stamping and session behaviour on tenant,
  shared, and unknown hosts.

**Frontend and sales site**: neither repo has a test framework, and adding one needs approval.
Verification is `npm run build` and `npx tsc --noEmit` per repo plus a manual checklist executed
on a preview deployment against staging: walkthroughs A through E, and a regression pass on an
existing tenant host (login, branding, storefront, switch).

## 10. Rollout

1. DNS and Vercel for `app.getkursa.space` and `www.app` (§5.6). Independent of code.
2. API on `main` via the git-flow release, deployed with `TENANCY_ENVIRONMENT_GUARD=log`. The
   migration backfills existing tenants as live, so no email changes host on deploy.
3. Frontend to Vercel production. Must precede step 4.
4. Sales website. From here new signups land on the shared host signed in.
5. System admin badge and toggle.
6. After at least a week of clean `tenancy.environment_required` logs, set
   `TENANCY_ENVIRONMENT_GUARD=enforce`.

Steps 2 and 3 are backward compatible with each other: an old frontend on a tenant host keeps
working against the new API, and the new frontend on a tenant host keeps working against the old
API except for the shared host itself, which does not exist until step 1.

## 11. Out of scope, recorded as follow-ups

- Moving `getkursa.space` nameservers to Vercel and attaching `*.getkursa.space`, which makes
  subdomains instant and leaves only custom domains on the shared host. Complements this design.
- Marketplace microservice and SPA reading `environment.url` instead of `primary_domain`.
- Removing the auto-attach block in `DetectEnvironment` and reconsidering `X-Frontend-Domain`
  outranking `Origin`.
- `EnvironmentPaymentConfigService` dropping its `findByDomain` workaround.
- The `TenantDomainRegistry` cache, which is not invalidated by model hooks and hides a new tenant
  from CORS narrowing for up to five minutes.
- A test framework for the two Next.js repos.
