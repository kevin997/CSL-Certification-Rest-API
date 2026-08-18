# Centralized Gateway Resolution — Defect Analysis

**Status:** root cause proven, fix planned
**Date:** 2026-08-18
**Repo:** `CSL-Certification-Rest-API`
**Plan:** `docs/superpowers/plans/2026-08-18-centralized-gateway-resolution.md`

## The reported failure

`POST https://certification.csl-brands.com/api/storefront/44/checkout` returns
**500 Server Error** for environment 44 (MEA DIGITAL, `meadigitalsarl.csl-brands.com`),
which is a centralized-gateway tenant. Payload selected `payment_gateway: "taramoney"`,
`payment_method: "7"`.

## Two stacked defects

### Defect A — the gateway lookup is scoped to the wrong environment

A centralized tenant transacts through **another** environment's gateways.
`EnvironmentPaymentConfigService::getEffectiveEnvironmentId(44)` returns **15**.

`PaymentGatewaySetting` uses `App\Traits\BelongsToEnvironment`, which adds
`App\Scopes\EnvironmentScope`. That scope applies whenever a session environment
is set (`app/Scopes/EnvironmentScope.php:22`):

```php
if (Schema::hasColumn($model->getTable(), 'environment_id') && session()->has('current_environment_id')) {
    $query->where($model->getTable().'.environment_id', session('current_environment_id'))
          ->orWhereNull($model->getTable().'.environment_id');
```

`DetectEnvironment` is **global** middleware (`bootstrap/app.php:36`) and writes
`session(['current_environment_id' => $environment->id])`
(`app/Http/Middleware/DetectEnvironment.php:113`), so during a storefront request
the session env is the **tenant** (44).

Measured on production:

| context | `PaymentGatewaySetting::find(7)` |
|---|---|
| no session (CLI, queue) | **found** (env 15) |
| session env = 15 (effective) | **found** |
| session env = 44 (requesting) | **NULL** |

Gateway 7 belongs to env 15, so under the tenant's session it is invisible.

Critically, the storefront **lists** gateways correctly — `getPaymentGateways`
uses `withoutGlobalScopes()` against the effective env
(`StorefrontController.php:1560`) — so the browser is handed id `7`, and then
`checkout` looks that id up **without** bypassing the scope
(`StorefrontController.php:1727`). The listing side was fixed; the consuming
side was not.

### Defect B — a fatal error replaces the graceful failure

`StorefrontController.php` uses `Response::HTTP_*` at lines **1724, 1734, 1863,
1921** but imports no `Response` class — its only `*Response*` import is
`Illuminate\Http\JsonResponse` (line 30). PHP therefore resolves
`App\Http\Controllers\Api\Response`, which does not exist.

`git log -S` confirms the import **never existed** in this file's history; the
usages arrived in commit `ea0b976`. This is pre-existing and independent of the
centralized work.

The two defects intersect at line 1734: Defect A drives execution into the
"Selected payment method is not available" branch, whose 422 response then
throws `Error: Class "App\Http\Controllers\Api\Response" not found`. Because
that is an `Error` and not an `Exception`, the `catch (\Exception $e)` at
`:1928` does not catch it — it escapes to the framework handler as a 500, after
`DB::rollBack()` at `:1730` has already run.

## Blast radius

Every payment touch point that resolves a gateway from the **requesting** or
**stored order** environment, rather than the effective one.

**Broken — payment cannot complete:**

| # | site | flow |
|---|---|---|
| 1 | `StorefrontController.php:1727` | storefront checkout |
| 2 | `StorefrontController.php:2350` | storefront continue-payment (also the sales-form payment link) |
| 3 | `SubscriptionProductController.php:315` | subscription subscribe |
| 4 | `SubscriptionProductController.php:440` | subscription renew |
| 5 | `SubscriptionProductController.php:636` | subscription continue-payment |
| 6 | `PaymentService.php:452` | `processGatewayPayment` — filters on the effective env, but the global scope ANDs the session env on top, making the predicates mutually exclusive |
| 7 | `PaymentService.php:365` | `getGatewaySettings` — same collision |
| 8 | `TransactionController.php:389` | webhook — resolves from the `{environment_id}` in the URL (the tenant), misses, then falls through to a **platform gateway with different secrets**, so signature verification fails or verifies against the wrong account |
| 9 | `PaymentService.php:760` | `verifyPayment` — silently falls back to `verifyLegacyPayment` |
| 10 | `RefundService.php:50` | admin refund → "Payment gateway settings not found" |

**Broken, non-payment:** `SubscriptionManager.php:813` and `PaymentService.php:994`
(method lists), `EntitlementService.php:213` (licence limit counting),
`PaymentGatewayController.php:424/498/672` (cross-env admin CRUD),
`Learner/OrderController.php:34,72` and `OrderController.php:281` (display names),
`PaymentGatewaySetting.php:38-42,101-110` (default-flag and uniqueness checks
silently no-op across environments).

**Safe:** everything routed through `PlatformPaymentGatewayResolver`
(licence checkout, platform payments, invoices, subscription retry) — it
already uses `withoutGlobalScopes()->whereNull('environment_id')`.

`PaymentService.php:994` carries a second, independent bug: it filters on an
`active` column that does not exist (the column is `status`).

## Why no test caught it

- `CentralizedGatewayEnvironmentTest` (16 tests) exercises the service in
  isolation and never issues a `PaymentGatewaySetting` query.
- `PublicGatewayPayloadTest` covers the two **listing** endpoints — the sites
  that were already correct — and never the checkout lookup.
- No feature test exists for `POST /storefront/{env}/checkout`, either
  continue-payment path, or subscribe/renew.
- No test anywhere sets `session(['current_environment_id' => …])` and then
  queries a gateway belonging to a different environment. That single missing
  setup is what made every one of these sites look correct under test.

## Consequence

No transaction rows exist for environments 15 or 44. **No payment has ever
completed through the centralized path** — this has been broken continuously
since centralized routing was deployed, not intermittently.

## Design decisions for the fix

**One resolver, not fifteen patches.** The repeated mistake is that each call
site independently decides which environment to filter on and whether to bypass
the scope. A single resolver removes that decision from the call sites:
resolve the effective environment, then query with `withoutGlobalScopes()` and
an explicit `environment_id`. Being context-independent, the same code is
correct with a session (checkout), without one (queue, CLI), and from a stored
order (continue-payment, webhook).

**Webhook URLs keep carrying the tenant environment.** The `{environment_id}`
segment is meaningful for attribution, and URLs already issued to providers
cannot be changed retroactively. The handler resolves the effective environment
from it instead.

**`Order.environment_id` stays the tenant.** The order belongs to the selling
tenant; that is correct. It must simply be run through the resolver before it
is used to find a gateway.

**`Transaction.environment_id` must become consistent.** It is currently written
three different ways — effective env at `PaymentService.php:137`, requesting env
at `:511` when callers pass `$order->environment_id`, and session env at `:573`.
Gateway adapters build callback URLs from it
(`LygosGateway.php:74,196`, `MonerooGateway.php:50`, `MonetbillGateway.php:113`,
`TaraMoneyGateway.php:108,325`), so the inconsistency is what feeds an
unpredictable `{environment_id}` into the webhook URL. It standardises on the
**tenant** environment, matching the order, with the resolver applied wherever a
gateway is then looked up.
