<?php

use App\Http\Middleware\BrandingMiddleware;
use App\Http\Middleware\ChatRateLimitMiddleware;
use App\Http\Middleware\CheckPlanFeature;
use App\Http\Middleware\CheckPlanLimit;
use App\Http\Middleware\ConfigureTenantCorsAndSanctum;
use App\Http\Middleware\DetectEnvironment;
use App\Http\Middleware\EnforceHttps;
use App\Http\Middleware\FixXsrfCookieDomain;
use App\Http\Middleware\IsolateSession;
use App\Http\Middleware\PreventIndexing;
use App\Providers\EnvironmentAuthServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
        then: function () {
            // Public API routes without authentication - use higher rate limit for SSR
            Route::prefix('api')
                ->middleware(['throttle:public-api'])
                ->group(base_path('routes/api-public.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();

        // nginx terminates TLS and forwards X-Forwarded-Proto, but no proxy was
        // trusted, so Laravel saw every request as plain http: route('login')
        // emitted http:// URLs and $request->ip() was the proxy rather than the
        // caller, putting all traffic in one rate-limit bucket.
        //
        // Only loopback and private ranges are trusted. The container publishes
        // port 8080 on 0.0.0.0, so trusting '*' would let anyone reaching it
        // directly spoof X-Forwarded-Proto and X-Forwarded-For; a public source
        // address never falls in these ranges.
        //
        // X-Forwarded-Host is deliberately not trusted: tenants are resolved by
        // domain and nginx already forwards the real Host header.
        $middleware->trustProxies(
            at: ['127.0.0.1', '::1', '10.0.0.0/8', '172.16.0.0/12', '192.168.0.0/16'],
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->prepend(ConfigureTenantCorsAndSanctum::class);
        $middleware->prepend(IsolateSession::class);

        // HTTPS enforcement middleware (runs first for security)
        $middleware->append(EnforceHttps::class);

        $middleware->append(DetectEnvironment::class);
        $middleware->append(BrandingMiddleware::class);
        $middleware->append(PreventIndexing::class);

        // Fix XSRF-TOKEN cookie domain for cross-subdomain auth
        // This must run after the response is generated to modify the cookie
        $middleware->append(FixXsrfCookieDomain::class);

        // Chat rate limiting middleware aliases
        $middleware->alias([
            'chat.rate.messages' => ChatRateLimitMiddleware::class.':messages',
            'chat.rate.typing' => ChatRateLimitMiddleware::class.':typing',

            // KURSA licence enforcement (Phase 9). Environment-scoped, dark by
            // default (config licensing.enforcement_enabled).
            'licence.feature' => CheckPlanFeature::class,
            'licence.limit' => CheckPlanLimit::class,
        ]);

        // Rate limiters are configured in FortifyServiceProvider
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // An unauthenticated api/* request that did not send
        // Accept: application/json was redirected to the login page. Browsers
        // follow that redirect cross-origin, the login page carries no CORS
        // headers, and the caller sees an opaque network error instead of a
        // readable 401 -- which is how a plain expired session surfaced as a
        // CORS failure on the certificate template upload.
        $exceptions->shouldRenderJsonWhen(
            fn ($request) => $request->is('api/*') || $request->expectsJson()
        );
    })
    ->withProviders([
        EnvironmentAuthServiceProvider::class,
    ])
    ->create();
