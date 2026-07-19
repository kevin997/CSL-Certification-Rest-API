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

    // Phase 9 dark-launch flag. When FALSE, all licence.feature / licence.limit
    // middleware pass through untouched (deploy dark, flip after Phase 8
    // reconciliation).
    'enforcement_enabled' => (bool) env('LICENSING_ENFORCEMENT_ENABLED', false),

    // ---------------------------------------------------------------------
    // Phase 9 enforcement config
    // ---------------------------------------------------------------------

    // Usage-counter cache TTL, in seconds. Keeps limit gates cheap without
    // being so long that a just-created resource is invisible for long.
    'usage_cache_ttl' => 60,

    // "Active learner" measurement window (doc §4.4: learners who accessed the
    // academy during the current period, not every historical record).
    'active_learner_window_days' => 30,

    // How often (seconds) a single user+environment refreshes its
    // environment_user.last_active_at heartbeat (throttled in DetectEnvironment).
    'last_active_throttle_seconds' => 3600,

    // environment_user.role values that consume an "admin/instructor seat"
    // (doc §4.4). Everything else (learner / user / system) is NOT a seat.
    'admin_seat_roles' => ['owner', 'admin', 'company_teacher', 'host', 'sales_agent'],

    // Ordered feature-level ladders for `licence.feature:key,minlevel` gates.
    // The middleware picks the FIRST ladder that contains the required minlevel,
    // then compares the environment's current level index against it. A value
    // not present in the ladder (or absent) ranks below every rung → blocked.
    // Ordering matters: the ['none','limited','full'] ladder is listed first so
    // any `,full` gate treats basic/limited/advanced/absent as "below full".
    'level_ladders' => [
        ['none', 'limited', 'full'],           // api_webhooks
        ['basic', 'advanced', 'advanced_exports'], // analytics
        ['basic', 'advanced', 'complete'],     // branding_control
        ['limited', 'full'],                   // live_sessions
        ['basic', 'full'],                     // coupons_referrals, communities, financial_reports
    ],
];
