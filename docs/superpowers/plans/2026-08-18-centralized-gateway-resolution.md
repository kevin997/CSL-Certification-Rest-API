# Centralized Gateway Resolution Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make every payment touch point resolve a payment gateway against the *effective* environment, so a centralized tenant can actually pay — and stop a fatal `Response::` reference turning a graceful 422 into a 500.

**Architecture:** One resolver, `PaymentGatewayResolver`, owns the two decisions every broken call site currently makes for itself: which environment to filter on (the effective one, via `EnvironmentPaymentConfigService::getEffectiveEnvironmentId`) and whether to bypass `EnvironmentScope` (always). Because it depends on no session state, the same code is correct in a request, a queue job, a console command and a webhook. Every broken site is then rewritten to call it.

**Tech Stack:** PHP 8.3 / Laravel 12 / Eloquent / PHPUnit.

**Spec:** `docs/superpowers/specs/2026-08-18-centralized-gateway-resolution-defect.md` — read it first; it carries the proof, the measurements and the design decisions this plan argues from.

## Global Constraints

- No new dependencies. No migration files — this fix is code-only; no schema change is required or permitted.
- **Never** resolve a gateway with a bare `PaymentGatewaySetting::find()` / `::where('environment_id', …)` again. Every gateway lookup on a payment path goes through `PaymentGatewayResolver`.
- `EnvironmentScope` must not be removed, weakened, or removed from the model. It is correct for tenant-owned data; the fix is to bypass it deliberately at gateway lookups, not to delete it.
- `Order.environment_id` keeps storing the **tenant** environment. Do not change what is stored — change what is done with it before a gateway lookup.
- Webhook URLs keep carrying the tenant environment in `{environment_id}`. URLs already issued to providers cannot be changed retroactively; the handler resolves the effective environment from it.
- Platform-scoped lookups (`whereNull('environment_id')`, via `PlatformPaymentGatewayResolver`) are already correct and must not be routed through the new resolver.
- Tests run with `php artisan test` or `vendor/bin/phpunit`. Feature tests seed gateways with `PaymentGatewaySetting::withoutGlobalScopes()->forceCreate([...])` — the existing convention in `tests/Feature/Storefront/PublicGatewayPayloadTest.php`.
- **Every test that exercises a centralized path MUST set `session(['current_environment_id' => $tenant->id])`.** The absence of that one line is why 16 existing tests all passed against broken code.

---

## File Structure

| File | Responsibility |
|---|---|
| `app/Services/Payments/PaymentGatewayResolver.php` (create) | The single place that resolves a gateway for an environment. |
| `tests/Unit/Services/PaymentGatewayResolverTest.php` (create) | Resolver behaviour, including with a conflicting session env. |
| `app/Http/Controllers/Api/StorefrontController.php` (modify) | `Response` import; checkout and continue-payment lookups. |
| `app/Http/Controllers/Api/SubscriptionProductController.php` (modify) | subscribe / renew / continue-payment lookups. |
| `app/Services/PaymentService.php` (modify) | `getGatewaySettings`, `processGatewayPayment`, `verifyPayment`; transaction env consistency. |
| `app/Services/Payments/RefundService.php` (modify) | refund lookup. |
| `app/Http/Controllers/Api/TransactionController.php` (modify) | webhook gateway resolution. |
| `tests/Feature/Storefront/CentralizedCheckoutTest.php` (create) | Checkout + continue-payment end to end for a centralized tenant. |
| `tests/Feature/Payments/CentralizedWebhookTest.php` (create) | Webhook resolves the centralized gateway, not the platform fallback. |

Tasks 5 and 6 (non-payment call sites, and the `active`-column bug) are separable cleanups; a reviewer could reject either without blocking the payment fix.

---

## Task 1: The resolver

**Files:**
- Create: `app/Services/Payments/PaymentGatewayResolver.php`
- Test: `tests/Unit/Services/PaymentGatewayResolverTest.php`

**Interfaces:**
- Consumes: `EnvironmentPaymentConfigService::getEffectiveEnvironmentId(int $environmentId): int`; the `PaymentGatewaySetting` model.
- Produces — Tasks 2-6 call these:
  - `PaymentGatewayResolver::forId(int|string $id, int $environmentId): ?PaymentGatewaySetting`
  - `PaymentGatewayResolver::forCode(string $code, int $environmentId, bool $activeOnly = true): ?PaymentGatewaySetting`
  - `PaymentGatewayResolver::listFor(int $environmentId, bool $activeOnly = true): \Illuminate\Support\Collection`
  - `PaymentGatewayResolver::effectiveEnvironmentId(int $environmentId): int`

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Services/PaymentGatewayResolverTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use App\Models\Environment;
use App\Models\EnvironmentPaymentConfig;
use App\Models\PaymentGatewaySetting;
use App\Services\Payments\PaymentGatewayResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentGatewayResolverTest extends TestCase
{
    use RefreshDatabase;

    private PaymentGatewayResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = app(PaymentGatewayResolver::class);
    }

    private function gatewayFor(Environment $environment, string $code = 'taramoney', bool $status = true): PaymentGatewaySetting
    {
        return PaymentGatewaySetting::withoutGlobalScopes()->forceCreate([
            'environment_id' => $environment->id,
            'gateway_name' => 'TaraMoney',
            'code' => $code,
            'display_name' => 'TaraMoney',
            'status' => $status,
            'is_default' => false,
            'mode' => 'live',
            'sort_order' => 0,
            'settings' => ['api_key' => 'k'],
        ]);
    }

    /** A tenant that borrows the provider's gateways. */
    private function centralizedPair(): array
    {
        $provider = Environment::factory()->create(['is_active' => true, 'is_centralized_payment_provider' => true]);
        $tenant = Environment::factory()->create(['is_active' => true]);
        EnvironmentPaymentConfig::factory()->create([
            'environment_id' => $tenant->id,
            'use_centralized_gateways' => true,
            'is_active' => true,
        ]);

        return [$provider, $tenant];
    }

    public function test_it_finds_the_providers_gateway_for_a_centralized_tenant(): void
    {
        [$provider, $tenant] = $this->centralizedPair();
        $gateway = $this->gatewayFor($provider);

        $found = $this->resolver->forId($gateway->id, $tenant->id);

        $this->assertNotNull($found);
        $this->assertSame($gateway->id, $found->id);
    }

    /**
     * The regression that matters. EnvironmentScope filters on the session
     * environment, so without an explicit bypass this returns null — which is
     * exactly what broke checkout in production.
     */
    public function test_it_still_resolves_when_the_session_names_the_tenant(): void
    {
        [$provider, $tenant] = $this->centralizedPair();
        $gateway = $this->gatewayFor($provider);

        session(['current_environment_id' => $tenant->id]);

        $this->assertNotNull($this->resolver->forId($gateway->id, $tenant->id));
        $this->assertNotNull($this->resolver->forCode('taramoney', $tenant->id));
        $this->assertCount(1, $this->resolver->listFor($tenant->id));
    }

    public function test_it_resolves_with_no_session_at_all(): void
    {
        [$provider, $tenant] = $this->centralizedPair();
        $gateway = $this->gatewayFor($provider);

        session()->forget('current_environment_id');

        $this->assertNotNull($this->resolver->forId($gateway->id, $tenant->id));
    }

    public function test_a_non_centralized_environment_resolves_its_own_gateway(): void
    {
        $environment = Environment::factory()->create(['is_active' => true]);
        $gateway = $this->gatewayFor($environment);

        session(['current_environment_id' => $environment->id]);

        $this->assertSame($gateway->id, $this->resolver->forId($gateway->id, $environment->id)->id);
    }

    public function test_it_refuses_a_gateway_belonging_to_an_unrelated_environment(): void
    {
        $other = Environment::factory()->create(['is_active' => true]);
        $mine = Environment::factory()->create(['is_active' => true]);
        $theirs = $this->gatewayFor($other);

        $this->assertNull($this->resolver->forId($theirs->id, $mine->id));
    }

    public function test_disabled_gateways_are_excluded_unless_asked_for(): void
    {
        $environment = Environment::factory()->create(['is_active' => true]);
        $this->gatewayFor($environment, 'taramoney', false);

        $this->assertNull($this->resolver->forCode('taramoney', $environment->id));
        $this->assertNotNull($this->resolver->forCode('taramoney', $environment->id, false));
    }

    public function test_effective_environment_id_is_exposed_for_callers_that_need_it(): void
    {
        [$provider, $tenant] = $this->centralizedPair();

        $this->assertSame($provider->id, $this->resolver->effectiveEnvironmentId($tenant->id));
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=PaymentGatewayResolverTest`
Expected: FAIL — `Class "App\Services\Payments\PaymentGatewayResolver" not found`.

- [ ] **Step 3: Write the resolver**

Create `app/Services/Payments/PaymentGatewayResolver.php`:

```php
<?php

namespace App\Services\Payments;

use App\Models\PaymentGatewaySetting;
use App\Services\EnvironmentPaymentConfigService;
use Illuminate\Support\Collection;

/**
 * The single place a payment gateway is resolved for an environment.
 *
 * Two decisions used to be made independently at ~15 call sites, and most got
 * at least one wrong:
 *
 *  1. WHICH environment owns the gateway. A centralized tenant transacts
 *     through another environment's gateways, so the effective id must be
 *     resolved first.
 *  2. WHETHER to bypass EnvironmentScope. That scope filters on
 *     session('current_environment_id'), which during a storefront request is
 *     the TENANT -- so it hides the very gateway the tenant is meant to use.
 *     A call site that filters correctly on the effective environment but does
 *     not bypass the scope ends up with two mutually exclusive predicates and
 *     resolves nothing.
 *
 * Depending on no session state also makes this correct in a queue job, a
 * console command and a webhook, where the scope is inert and the old code
 * happened to work by accident.
 */
class PaymentGatewayResolver
{
    public function __construct(private EnvironmentPaymentConfigService $paymentConfig) {}

    /** The environment whose gateways this environment actually transacts through. */
    public function effectiveEnvironmentId(int $environmentId): int
    {
        return $this->paymentConfig->getEffectiveEnvironmentId($environmentId);
    }

    /**
     * Resolve a gateway by its primary key, scoped to what `$environmentId` is
     * allowed to use. Returns null when the id belongs to an unrelated
     * environment — callers must treat that as "not available", never as an error.
     */
    public function forId(int|string $id, int $environmentId): ?PaymentGatewaySetting
    {
        return $this->query($environmentId)->whereKey($id)->first();
    }

    public function forCode(string $code, int $environmentId, bool $activeOnly = true): ?PaymentGatewaySetting
    {
        $query = $this->query($environmentId)->where('code', $code);

        if ($activeOnly) {
            $query->where('status', true);
        }

        return $query->orderByDesc('is_default')->first();
    }

    /** @return Collection<int, PaymentGatewaySetting> */
    public function listFor(int $environmentId, bool $activeOnly = true): Collection
    {
        $query = $this->query($environmentId);

        if ($activeOnly) {
            $query->where('status', true);
        }

        return $query->orderBy('sort_order')->get();
    }

    private function query(int $environmentId)
    {
        // withoutGlobalScopes() is the point of this class -- see the note above.
        return PaymentGatewaySetting::withoutGlobalScopes()
            ->where('environment_id', $this->effectiveEnvironmentId($environmentId));
    }
}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `php artisan test --filter=PaymentGatewayResolverTest`
Expected: PASS — 7 tests.

- [ ] **Step 5: Commit**

```bash
git add app/Services/Payments/PaymentGatewayResolver.php tests/Unit/Services/PaymentGatewayResolverTest.php
git commit -m "feat(payments): single resolver for gateway lookup by effective environment"
```

---

## Task 2: Checkout — the reported 500

**Files:**
- Modify: `app/Http/Controllers/Api/StorefrontController.php` (import block ~line 30; lines 1727, 2350)
- Test: `tests/Feature/Storefront/CentralizedCheckoutTest.php` (create)

**Interfaces:**
- Consumes: `PaymentGatewayResolver::forId(int|string $id, int $environmentId): ?PaymentGatewaySetting` from Task 1.
- Produces: nothing later tasks depend on.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Storefront/CentralizedCheckoutTest.php`:

```php
<?php

namespace Tests\Feature\Storefront;

use App\Models\Environment;
use App\Models\EnvironmentPaymentConfig;
use App\Models\PaymentGatewaySetting;
use App\Services\Payments\PaymentGatewayResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A centralized tenant borrows the provider's gateways. Production returned a
 * 500 here: the storefront listed the provider's gateway id, then checkout
 * looked that id up under the tenant's session scope, found nothing, and the
 * "not available" branch threw on an unimported Response class.
 */
class CentralizedCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function centralizedSetup(): array
    {
        $provider = Environment::factory()->create(['is_active' => true, 'is_centralized_payment_provider' => true]);
        $tenant = Environment::factory()->create(['is_active' => true]);

        EnvironmentPaymentConfig::factory()->create([
            'environment_id' => $tenant->id,
            'use_centralized_gateways' => true,
            'is_active' => true,
        ]);

        $gateway = PaymentGatewaySetting::withoutGlobalScopes()->forceCreate([
            'environment_id' => $provider->id,
            'gateway_name' => 'TaraMoney',
            'code' => 'taramoney',
            'display_name' => 'TaraMoney',
            'status' => true,
            'is_default' => true,
            'mode' => 'live',
            'sort_order' => 0,
            'settings' => ['api_key' => 'k', 'business_id' => 'b'],
        ]);

        return [$provider, $tenant, $gateway];
    }

    public function test_the_gateway_the_storefront_advertises_is_resolvable_at_checkout(): void
    {
        [, $tenant, $gateway] = $this->centralizedSetup();

        // Exactly what the storefront request establishes.
        session(['current_environment_id' => $tenant->id]);

        $listed = app(PaymentGatewayResolver::class)->listFor($tenant->id);
        $this->assertCount(1, $listed, 'the storefront advertises the provider gateway');

        $resolved = app(PaymentGatewayResolver::class)->forId($listed->first()->id, $tenant->id);
        $this->assertNotNull($resolved, 'checkout must resolve the id it advertised');
        $this->assertSame($gateway->id, $resolved->id);
    }

    public function test_an_unavailable_gateway_returns_422_not_500(): void
    {
        [, $tenant] = $this->centralizedSetup();
        $unrelated = Environment::factory()->create(['is_active' => true]);
        $theirs = PaymentGatewaySetting::withoutGlobalScopes()->forceCreate([
            'environment_id' => $unrelated->id,
            'gateway_name' => 'Stripe',
            'code' => 'stripe',
            'display_name' => 'Card',
            'status' => true,
            'is_default' => false,
            'mode' => 'live',
            'sort_order' => 0,
            'settings' => [],
        ]);

        session(['current_environment_id' => $tenant->id]);

        // The branch that threw: resolving a gateway this tenant may not use
        // must produce a clean "not available", never a fatal Error.
        $this->assertNull(app(PaymentGatewayResolver::class)->forId($theirs->id, $tenant->id));
    }

    /**
     * Guards Defect B directly: an unimported Response class made every
     * HTTP_* constant in this controller a fatal error.
     */
    public function test_the_storefront_controller_can_reference_its_response_constants(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Api/StorefrontController.php'));

        $this->assertMatchesRegularExpression(
            '/^use\s+(Illuminate\\\\Http|Symfony\\\\Component\\\\HttpFoundation)\\\\Response;/m',
            $source,
            'StorefrontController uses Response::HTTP_* constants and must import a Response class'
        );
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=CentralizedCheckoutTest`
Expected: FAIL — the resolver test fails on the tenant session (returns null), and the import assertion fails because no `Response` import exists.

- [ ] **Step 3: Add the missing import**

In `app/Http/Controllers/Api/StorefrontController.php`, add to the import block alongside `use Illuminate\Http\JsonResponse;` (line 30):

```php
use Illuminate\Http\Response;
```

This fixes all four usages at once — lines 1724, 1734, 1863 and 1921. Do not change the constants themselves; `Illuminate\Http\Response` extends the Symfony response and carries every `HTTP_*` constant used here.

- [ ] **Step 4: Route the checkout lookup through the resolver**

At `StorefrontController.php:1727`, replace:

```php
                $gatewaySettings = PaymentGatewaySetting::find($paymentMethod);
```

with:

```php
                $gatewaySettings = app(PaymentGatewayResolver::class)
                    ->forId($paymentMethod, $environment->id);
```

and add `use App\Services\Payments\PaymentGatewayResolver;` to the import block.

- [ ] **Step 5: Route the continue-payment lookup through the resolver**

At `StorefrontController.php:2350`, the query filters on `$order->environment_id` — the tenant, which owns no gateway. Replace the whole lookup with:

```php
            $resolver = app(PaymentGatewayResolver::class);
            $paymentGatewaySetting = is_numeric($paymentMethod)
                ? $resolver->forId($paymentMethod, $order->environment_id)
                : $resolver->forCode((string) $paymentMethod, $order->environment_id);
```

`$order->environment_id` stays the tenant — the resolver converts it. Keep the existing null handling that follows; it now returns a clean 400 instead of hiding a scope miss.

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --filter=CentralizedCheckoutTest`
Expected: PASS — 3 tests.

- [ ] **Step 7: Run the full suite for regressions**

Run: `php artisan test`
Expected: no new failures. Note the suite has pre-existing failures in the auth area (13 errors / 6 failures as of 2026-08-13) — compare against a baseline run rather than expecting green.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Api/StorefrontController.php tests/Feature/Storefront/CentralizedCheckoutTest.php
git commit -m "fix(storefront): resolve gateways by effective environment; import Response"
```

---

## Task 3: PaymentService — the main payment path

**Files:**
- Modify: `app/Services/PaymentService.php` (lines 365, 452, 760; and the transaction environment writes at 137, 511, 573)
- Test: `tests/Unit/Services/PaymentServiceGatewayResolutionTest.php` (create)

**Interfaces:**
- Consumes: `PaymentGatewayResolver::forCode(string $code, int $environmentId, bool $activeOnly = true)`, `::forId(int|string $id, int $environmentId)` from Task 1.
- Produces: nothing later tasks depend on.

These three sites filter on the effective environment already but do **not** bypass `EnvironmentScope`, so the scope ANDs the session environment on top and the two predicates become mutually exclusive.

- [ ] **Step 1: Write the failing test**

Create `tests/Unit/Services/PaymentServiceGatewayResolutionTest.php`:

```php
<?php

namespace Tests\Unit\Services;

use App\Models\Environment;
use App\Models\EnvironmentPaymentConfig;
use App\Models\PaymentGatewaySetting;
use App\Services\Payments\PaymentGatewayResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentServiceGatewayResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_centralized_tenant_resolves_the_providers_gateway_by_code_under_its_own_session(): void
    {
        $provider = Environment::factory()->create(['is_active' => true, 'is_centralized_payment_provider' => true]);
        $tenant = Environment::factory()->create(['is_active' => true]);
        EnvironmentPaymentConfig::factory()->create([
            'environment_id' => $tenant->id,
            'use_centralized_gateways' => true,
            'is_active' => true,
        ]);
        PaymentGatewaySetting::withoutGlobalScopes()->forceCreate([
            'environment_id' => $provider->id,
            'gateway_name' => 'TaraMoney',
            'code' => 'taramoney',
            'display_name' => 'TaraMoney',
            'status' => true,
            'is_default' => true,
            'mode' => 'live',
            'sort_order' => 0,
            'settings' => ['api_key' => 'k'],
        ]);

        // The condition under which the old code silently resolved nothing:
        // explicit filter on the effective env, scope filtering on the tenant.
        session(['current_environment_id' => $tenant->id]);

        $resolved = app(PaymentGatewayResolver::class)->forCode('taramoney', $tenant->id);

        $this->assertNotNull($resolved, 'processGatewayPayment must find the gateway it is told to charge');
        $this->assertSame($provider->id, $resolved->environment_id);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=PaymentServiceGatewayResolutionTest`
Expected: PASS if Task 1 is merged (the resolver already behaves). This test exists to pin the behaviour PaymentService now depends on — run it, confirm green, then make the edits below and confirm it stays green.

- [ ] **Step 3: Replace the three lookups**

In `app/Services/PaymentService.php`, add `use App\Services\Payments\PaymentGatewayResolver;` to the imports, then:

At **line 365** (`getGatewaySettings`), replace the query with:

```php
        $settings = app(PaymentGatewayResolver::class)
            ->forCode($gatewayCode, $environmentId, false);
```

Keep the existing platform fallback that follows if the result is null — platform gateways are `environment_id IS NULL` and are resolved by `PlatformPaymentGatewayResolver`, which is already correct.

At **line 452** (`processGatewayPayment`), replace the query with:

```php
        $gatewaySettings = app(PaymentGatewayResolver::class)
            ->forCode($gatewayCode, $environmentId);
```

At **line 760** (`verifyPayment`), replace the query with:

```php
        $gatewaySettings = app(PaymentGatewayResolver::class)
            ->forId($transaction->payment_gateway_setting_id, $transaction->environment_id);
```

- [ ] **Step 4: Make the transaction environment consistent**

Three sites write `Transaction.environment_id` three different ways, which is what feeds an unpredictable `{environment_id}` into gateway callback URLs. Standardise on the **tenant** environment, matching the order:

At **line 137**, change `'environment_id' => $effectiveEnvironmentId,` to:

```php
            // The tenant owns the transaction; the effective environment only
            // owns the gateway. Callback URLs are built from this value, so it
            // must match the order rather than the gateway.
            'environment_id' => $environmentId,
```

At **line 573**, `$transaction->environment_id = $environmentId;` is already the requesting environment — add the same one-line comment above it so the intent is not re-broken.

Line 511 already prefers `$paymentData['environment_id']` (the order's) and only falls back to the gateway's; leave it, but add:

```php
        // Falls back to the gateway's environment only when no caller supplied
        // one -- prefer the order's environment, which is the tenant.
```

- [ ] **Step 5: Run the tests**

Run: `php artisan test --filter=PaymentServiceGatewayResolutionTest`
Then: `php artisan test --filter=Payments`
Expected: PASS; the existing `WebhookAuthorityTest`, `CallbackDisplayOnlyTest` and `TransactionPolicyTest` must stay green.

- [ ] **Step 6: Commit**

```bash
git add app/Services/PaymentService.php tests/Unit/Services/PaymentServiceGatewayResolutionTest.php
git commit -m "fix(payments): resolve gateways via the resolver; make transaction environment consistent"
```

---

## Task 4: Subscriptions and refunds

**Files:**
- Modify: `app/Http/Controllers/Api/SubscriptionProductController.php` (lines 315, 440, 636)
- Modify: `app/Services/Payments/RefundService.php` (line 50)
- Test: `tests/Feature/Payments/CentralizedSubscriptionTest.php` (create)

**Interfaces:**
- Consumes: `PaymentGatewayResolver::forId(int|string $id, int $environmentId)`, `::forCode(string $code, int $environmentId, bool $activeOnly = true)` from Task 1.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Payments/CentralizedSubscriptionTest.php`:

```php
<?php

namespace Tests\Feature\Payments;

use App\Models\Environment;
use App\Models\EnvironmentPaymentConfig;
use App\Models\PaymentGatewaySetting;
use App\Services\Payments\PaymentGatewayResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralizedSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    private function setUpCentralized(): array
    {
        $provider = Environment::factory()->create(['is_active' => true, 'is_centralized_payment_provider' => true]);
        $tenant = Environment::factory()->create(['is_active' => true]);
        EnvironmentPaymentConfig::factory()->create([
            'environment_id' => $tenant->id,
            'use_centralized_gateways' => true,
            'is_active' => true,
        ]);
        $gateway = PaymentGatewaySetting::withoutGlobalScopes()->forceCreate([
            'environment_id' => $provider->id,
            'gateway_name' => 'TaraMoney',
            'code' => 'taramoney',
            'display_name' => 'TaraMoney',
            'status' => true,
            'is_default' => true,
            'mode' => 'live',
            'sort_order' => 0,
            'settings' => ['api_key' => 'k'],
        ]);

        return [$tenant, $gateway];
    }

    public function test_subscribe_renew_and_continue_all_resolve_the_same_gateway(): void
    {
        [$tenant, $gateway] = $this->setUpCentralized();
        session(['current_environment_id' => $tenant->id]);

        $resolver = app(PaymentGatewayResolver::class);

        // subscribe / renew resolve by id from the request
        $this->assertSame($gateway->id, $resolver->forId($gateway->id, $tenant->id)?->id);
        // continue-payment resolves from the stored order's tenant environment
        $this->assertSame($gateway->id, $resolver->forCode('taramoney', $tenant->id)?->id);
    }

    public function test_a_refund_can_resolve_the_gateway_of_a_centralized_transaction(): void
    {
        [$tenant, $gateway] = $this->setUpCentralized();
        // An admin refund runs under the admin's own session, not the tenant's.
        session(['current_environment_id' => $tenant->id]);

        $this->assertNotNull(
            app(PaymentGatewayResolver::class)->forId($gateway->id, $tenant->id),
            'RefundService must resolve the gateway that took the payment'
        );
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=CentralizedSubscriptionTest`
Expected: PASS once Task 1 is merged — these pin the behaviour the edits below rely on. Confirm green before editing, and green again after.

- [ ] **Step 3: Replace the subscription lookups**

In `app/Http/Controllers/Api/SubscriptionProductController.php`, add `use App\Services\Payments\PaymentGatewayResolver;`, then:

At **line 315** (`subscribe`) and **line 440** (`renew`), replace each:

```php
        $gatewaySettings = PaymentGatewaySetting::find($request->input('payment_method'));
```

with:

```php
        $gatewaySettings = app(PaymentGatewayResolver::class)
            ->forId($request->input('payment_method'), $environment->id);
```

At **line 636** (`continuePayment`), replace the `where('environment_id', $order->environment_id)` query with:

```php
            $resolver = app(PaymentGatewayResolver::class);
            $paymentGatewaySetting = is_numeric($paymentMethod)
                ? $resolver->forId($paymentMethod, $order->environment_id)
                : $resolver->forCode((string) $paymentMethod, $order->environment_id);
```

- [ ] **Step 4: Replace the refund lookup**

In `app/Services/Payments/RefundService.php`, add `use App\Services\Payments\PaymentGatewayResolver;` and at **line 50** replace:

```php
        $gatewaySettings = PaymentGatewaySetting::find($parent->payment_gateway_setting_id);
```

with:

```php
        $gatewaySettings = app(PaymentGatewayResolver::class)
            ->forId($parent->payment_gateway_setting_id, $parent->environment_id);
```

- [ ] **Step 5: Run the tests**

Run: `php artisan test --filter=CentralizedSubscriptionTest`
Then: `php artisan test --filter=Refund`
Expected: PASS; `RefundFlowTest`'s 13 tests must stay green.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/Api/SubscriptionProductController.php app/Services/Payments/RefundService.php tests/Feature/Payments/CentralizedSubscriptionTest.php
git commit -m "fix(payments): resolve gateways by effective environment in subscriptions and refunds"
```

---

## Task 5: Webhooks

**Files:**
- Modify: `app/Http/Controllers/Api/TransactionController.php:389`
- Test: `tests/Feature/Payments/CentralizedWebhookTest.php` (create)

**Interfaces:**
- Consumes: `PaymentGatewayResolver::forCode(string $code, int $environmentId, bool $activeOnly = true)` from Task 1.

This one fails differently from the rest. A provider posts with no browser headers, so `EnvironmentScope` may be inert — but the handler filters on the `{environment_id}` **from the URL**, which is the tenant, which owns no gateway. It then falls through to the platform gateway at `:393`, whose secrets are different, so signature verification either fails or verifies against the wrong account. That is worse than a clean miss: it is a silent wrong-key verification.

- [ ] **Step 1: Write the failing test**

Create `tests/Feature/Payments/CentralizedWebhookTest.php`:

```php
<?php

namespace Tests\Feature\Payments;

use App\Models\Environment;
use App\Models\EnvironmentPaymentConfig;
use App\Models\PaymentGatewaySetting;
use App\Services\Payments\PaymentGatewayResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CentralizedWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_webhook_for_a_tenant_resolves_the_providers_gateway_not_the_platform_one(): void
    {
        $provider = Environment::factory()->create(['is_active' => true, 'is_centralized_payment_provider' => true]);
        $tenant = Environment::factory()->create(['is_active' => true]);
        EnvironmentPaymentConfig::factory()->create([
            'environment_id' => $tenant->id,
            'use_centralized_gateways' => true,
            'is_active' => true,
        ]);

        $tenantGateway = PaymentGatewaySetting::withoutGlobalScopes()->forceCreate([
            'environment_id' => $provider->id,
            'gateway_name' => 'TaraMoney',
            'code' => 'taramoney',
            'display_name' => 'TaraMoney',
            'status' => true,
            'is_default' => true,
            'mode' => 'live',
            'sort_order' => 0,
            'settings' => ['webhook_secret' => 'tenant-secret'],
        ]);

        // A platform gateway with the SAME code and DIFFERENT secrets — the
        // wrong answer the old fallback produced.
        PaymentGatewaySetting::withoutGlobalScopes()->forceCreate([
            'environment_id' => null,
            'gateway_name' => 'TaraMoney',
            'code' => 'taramoney',
            'display_name' => 'TaraMoney',
            'status' => true,
            'is_default' => true,
            'mode' => 'live',
            'sort_order' => 0,
            'settings' => ['webhook_secret' => 'platform-secret'],
        ]);

        // Webhooks carry no session; the URL carries the tenant id.
        session()->forget('current_environment_id');

        $resolved = app(PaymentGatewayResolver::class)->forCode('taramoney', $tenant->id);

        $this->assertNotNull($resolved);
        $this->assertSame($tenantGateway->id, $resolved->id, 'must not fall through to the platform gateway');
        $this->assertSame('tenant-secret', $resolved->settings['webhook_secret'] ?? null);
    }
}
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `php artisan test --filter=CentralizedWebhookTest`
Expected: PASS once Task 1 is merged — it pins the resolver behaviour the edit relies on. Confirm green before editing.

- [ ] **Step 3: Resolve the webhook gateway through the resolver**

In `app/Http/Controllers/Api/TransactionController.php`, add `use App\Services\Payments\PaymentGatewayResolver;` and at **line 389** replace the query with:

```php
        // The URL carries the TENANT environment (kept for attribution, and
        // because URLs already issued to providers cannot be changed). Resolve
        // the environment that actually owns the gateway before looking it up,
        // or a centralized tenant silently verifies against the platform
        // gateway's secrets.
        $gatewaySettings = app(PaymentGatewayResolver::class)
            ->forCode($gateway, (int) $environment_id);
```

Leave the platform fallback at `:393-398` intact — it is still correct for genuinely platform-scoped webhooks, and it is now only reached when the environment truly owns no gateway.

- [ ] **Step 4: Run the tests**

Run: `php artisan test --filter=Webhook`
Expected: PASS; `WebhookAuthorityTest` and `WebhookSignatureTest` must stay green.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/Api/TransactionController.php tests/Feature/Payments/CentralizedWebhookTest.php
git commit -m "fix(payments): resolve webhook gateway by effective environment"
```

---

## Task 6: The remaining non-payment call sites

**Files:**
- Modify: `app/Services/SubscriptionManager.php:813`
- Modify: `app/Services/PaymentService.php:994`
- Modify: `app/Http/Controllers/Api/Learner/OrderController.php:34,72`
- Modify: `app/Http/Controllers/Api/OrderController.php:281`

**Interfaces:**
- Consumes: `PaymentGatewayResolver::listFor(int $environmentId, bool $activeOnly = true)`, `::forId(int|string $id, int $environmentId)` from Task 1.

None of these block a payment. They make a centralized tenant see an empty list of methods, or a raw numeric id where a gateway name belongs.

- [ ] **Step 1: Fix the method lists**

`app/Services/SubscriptionManager.php:813` — replace the query with:

```php
        $gateways = app(\App\Services\Payments\PaymentGatewayResolver::class)
            ->listFor($environmentId);
```

`app/Services/PaymentService.php:994` — replace the query with:

```php
        $gateways = app(PaymentGatewayResolver::class)->listFor($environmentId);
```

This also removes a second, independent bug at that line: it filtered on `where('active', true)`, and there is no `active` column — the column is `status`. The resolver applies `status` correctly.

- [ ] **Step 2: Fix the display lookups**

`app/Http/Controllers/Api/Learner/OrderController.php` at lines 34 and 72, and `app/Http/Controllers/Api/OrderController.php:281` — replace each `PaymentGatewaySetting::find($order->payment_method)` / `find($paymentMethod)` with:

```php
        $gateway = app(\App\Services\Payments\PaymentGatewayResolver::class)
            ->forId($paymentMethod, $order->environment_id);
```

`OrderController::resolvePaymentMethod` receives the payment method but may not have an `$order` in scope. Read the method signature before editing: if it has no order, add an `int $environmentId` parameter and pass `$order->environment_id` from each of its callers. The environment passed must be the order's, never the session's — that is the whole point of the change.

- [ ] **Step 3: Run the suite**

Run: `php artisan test`
Expected: no new failures against the baseline from Task 2 Step 7.

- [ ] **Step 4: Commit**

```bash
git add app/Services/SubscriptionManager.php app/Services/PaymentService.php app/Http/Controllers/Api/Learner/OrderController.php app/Http/Controllers/Api/OrderController.php
git commit -m "fix(payments): resolve gateway lists and display names by effective environment"
```

---

## Task 7: A guard against the next unimported class

**Files:**
- Test: `tests/Feature/StaticAnalysis/ControllerImportsTest.php` (create)

The `Response::` defect was invisible until a rarely-taken branch executed. One test makes the whole class of it visible.

- [ ] **Step 1: Write the test**

Create `tests/Feature/StaticAnalysis/ControllerImportsTest.php`:

```php
<?php

namespace Tests\Feature\StaticAnalysis;

use Tests\TestCase;

/**
 * A controller that writes `Response::HTTP_*` without importing a Response
 * class resolves it against its own namespace and fatals at runtime -- but
 * only on whichever branch happens to use it. That is how a graceful 422
 * became a production 500 on the checkout path.
 */
class ControllerImportsTest extends TestCase
{
    public function test_every_controller_that_uses_response_constants_imports_response(): void
    {
        $offenders = [];

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(app_path('Http/Controllers'))
        );

        foreach ($files as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = file_get_contents($file->getPathname());

            $usesConstant = preg_match('/(?<![\w\\\\$>])Response::HTTP_/', $source);
            if (! $usesConstant) {
                continue;
            }

            $imports = preg_match('/^use\s+[^;]*\\\\Response;/m', $source)
                || preg_match('/^use\s+[^;]*\s+as\s+Response;/m', $source);

            if (! $imports) {
                $offenders[] = str_replace(app_path().'/', '', $file->getPathname());
            }
        }

        $this->assertSame([], $offenders, "These controllers use Response::HTTP_* without importing a Response class:\n".implode("\n", $offenders));
    }
}
```

- [ ] **Step 2: Run it**

Run: `php artisan test --filter=ControllerImportsTest`
Expected: PASS once Task 2 added the import to `StorefrontController`. If it fails, it has found another file with the same defect — fix that file's imports the same way; do not weaken the test.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/StaticAnalysis/ControllerImportsTest.php
git commit -m "test: fail when a controller uses Response constants without importing Response"
```

---

## Deliberately out of scope

The audit found five more sites affected by the same scoping problem. None can
stop a payment, and each is listed here so a later reader knows they were seen
and judged rather than missed:

| site | effect | why deferred |
|---|---|---|
| `EntitlementService.php:213` | a centralized tenant counts 0 gateways, so `licence.limit:payment_gateways` mis-counts | licence limits, not money movement; changing it alters entitlement behaviour and deserves its own decision |
| `PaymentGatewayController.php:424,498,672` | a platform admin in env A gets 404 showing/updating/deleting a gateway in env B | admin CRUD, and arguably the scope is doing its job here — cross-env admin access is a permissions question, not a resolution bug |
| `PaymentGatewaySetting.php:38-42` (`saving`) and `:101-110` (`validateUniqueConstraints`) | `self::query()` carries the scope, so unsetting a previous default and detecting a duplicate `code` silently no-op across environments | data-integrity edge, needs its own tests around the default flag; folding it in would widen this plan's blast radius |
| `WebhookProcessor.php:69,246,321` | `optional($transaction->paymentGatewaySetting)->code` yields `null` → `'unknown'` in settlement/refund/dispute audit rows when a session env is set | audit metadata only; no money is misrouted |
| `SubscriptionManager.php:550` (`initializeGateway`) | broken by the same pattern | **dead code** — `initializePlatformGateway` at `:587` is what is actually called. Delete it or fix it in a separate cleanup; do not leave it half-fixed |

## A note on the TDD shape of Tasks 3-5

Task 1's tests fail first and drive the resolver into existence — normal TDD.

Tasks 3, 4 and 5 are different: once the resolver exists, their tests **pass
before the edits**, because they pin the resolver's behaviour rather than the
call site's. That is deliberate, not an oversight. Proving the old call sites
broken would mean instantiating each controller with a faked session and
request, which is far more scaffolding than the fix, and the resolver test
already proves the underlying defect. Run them before editing to confirm green,
edit, and run again — a red result then means the edit reached for the wrong
environment.

The genuine end-to-end proof is the manual staging check in Verification: a
real centralized checkout writing a real `transactions` row.

## Verification

- `php artisan test --filter=PaymentGatewayResolverTest` — 7 pass.
- `php artisan test --filter=Centralized` — checkout, subscription and webhook suites pass.
- `php artisan test --filter=ControllerImportsTest` — passes, and stays passing.
- `php artisan test` — no new failures against the baseline captured in Task 2 Step 7. The auth suite has pre-existing failures; do not chase them here.
- **Manual, on staging before deploy:** with a centralized tenant, complete a real checkout end to end and confirm a `transactions` row is written with the tenant's `environment_id` and the provider's `payment_gateway_setting_id`. Production currently has **zero** transactions for the centralized pair, so a successful row is itself the proof the path works.

## Deployment note

This is code-only — no migration, no schema change. Deploy is the standard image build from `main` plus a rollout; see `docs/` for the service's deploy path. After deploy, re-run the production probe from the spec:

```
PaymentGatewaySetting::find(<provider gateway id>)   with session env = <tenant id>
```

It must now be reachable through `PaymentGatewayResolver::forId(<id>, <tenant id>)`.
