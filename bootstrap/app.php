<?php

use App\Http\Middleware\BrandingMiddleware;
use App\Http\Middleware\DetectEnvironment;
use App\Providers\EnvironmentAuthServiceProvider;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->append(DetectEnvironment::class);
        $middleware->append(BrandingMiddleware::class);
        
        // Rate limiters are configured in FortifyServiceProvider
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->withProviders([
        EnvironmentAuthServiceProvider::class,
    ])
    ->create();
