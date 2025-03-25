<?php

namespace App\Providers;

use App\Auth\Providers\EnvironmentUserProvider;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class EnvironmentAuthServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Auth::provider('environment', function ($app, array $config) {
            return new EnvironmentUserProvider(
                $app['hash'],
                $config['model'] ?? User::class,
                session('current_environment_id')
            );
        });
    }
}
