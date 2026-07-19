<?php

/*
|--------------------------------------------------------------------------
| KURSA Licensing (Phase 4)
|--------------------------------------------------------------------------
|
| Canonical, server-owned economics for the environment-licence lifecycle.
| Prices are USD-only (doc §4, §9.4). The trial is exactly 14 days (doc §5);
| the failed-renewal grace window is 7 days (plan Phase 4 default).
|
| `enforcement_enabled` is a forward-compat dark-launch flag for Phase 9 —
| Phase 4 does NOT enforce entitlements anywhere; EntitlementService only
| resolves them.
*/

return [
    // Exact trial length, in days (doc §5 — "persist exactly 14 days").
    'trial_days' => 14,

    // Failed-renewal / past-due grace window, in days.
    'grace_days' => 7,

    'currency' => 'USD',

    // Canonical licence prices (doc §4.2 / §4.3). NO setup fee (§9.4).
    'prices' => [
        'creator_monthly' => 20.00,
        'white_label_annual' => 500.00,
    ],

    // Platform environment used as billing/tax context for anonymous
    // onboarding checkouts (no tenant environment exists yet). The gateway
    // itself is platform-scoped (environment_id IS NULL) regardless.
    'platform_environment_id' => (int) env('LICENSING_PLATFORM_ENVIRONMENT_ID', 1),

    // How long a checkout intent stays payable before it is considered expired.
    'checkout_ttl_minutes' => 120,

    // EntitlementService resolution cache TTL, in seconds.
    'entitlement_cache_ttl' => 3600,

    // Phase 9 dark-launch flag (unused in Phase 4).
    'enforcement_enabled' => (bool) env('LICENSING_ENFORCEMENT_ENABLED', false),
];
