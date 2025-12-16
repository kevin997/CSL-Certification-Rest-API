<?php

use App\Http\Middleware\BrandingMiddleware;
use App\Http\Middleware\ConfigureTenantCorsAndSanctum;
use App\Http\Middleware\DetectEnvironment;
use App\Http\Middleware\EnforceHttps;
use App\Providers\EnvironmentAuthServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        channels: __DIR__ . '/../routes/channels.php',
        health: '/up',
        then: function () {
            // Public API routes without authentication
            Route::prefix('api')
                ->middleware(['throttle:api'])
                ->group(base_path('routes/api-public.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->statefulApi();

        $middleware->prepend(ConfigureTenantCorsAndSanctum::class);

        // HTTPS enforcement middleware (runs first for security)
        $middleware->append(EnforceHttps::class);

        $middleware->append(DetectEnvironment::class);
        $middleware->append(BrandingMiddleware::class);
        $middleware->append(\App\Http\Middleware\PreventIndexing::class);

        // Chat rate limiting middleware aliases
        $middleware->alias([
            'chat.rate.messages' => \App\Http\Middleware\ChatRateLimitMiddleware::class . ':messages',
            'chat.rate.typing' => \App\Http\Middleware\ChatRateLimitMiddleware::class . ':typing',
        ]);

        // Rate limiters are configured in FortifyServiceProvider
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withProviders([
        EnvironmentAuthServiceProvider::class,
    ])
    ->create();
