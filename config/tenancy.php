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

    // 'log' records would-be refusals and lets the request through; any other
    // value, including an unrecognised one, returns 403 { code:
    // environment_required } -- a typo here must not reopen the tenant routes.
    'environment_guard' => env('TENANCY_ENVIRONMENT_GUARD', 'log'),

    'domain_probe' => [
        'http_timeout_seconds' => (int) env('TENANCY_DOMAIN_PROBE_TIMEOUT', 5),

        /*
         * A domain counts as live only when it serves the KURSA frontend, not
         * merely when something answers on it: a customer's existing site or a
         * parking page would otherwise capture every link for that tenant.
         * Recognised by this response header, or failing that by any of these
         * strings in the body.
         */
        'body_markers' => array_values(array_filter(explode(
            ',',
            env('TENANCY_DOMAIN_PROBE_BODY_MARKERS', '__NEXT_DATA__,/_next/static')
        ))),
    ],

    // Lifetime of the one-time sign-in token minted at onboarding.
    'onboarding_switch_token_ttl_seconds' => (int) env('TENANCY_ONBOARDING_TOKEN_TTL', 300),
];
