<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Spatie\Backup\Events\BackupZipWasCreated;
use App\Listeners\MailBackupWithAttachment;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // Define rate limiters (required for Laravel 12)
        $this->configureRateLimiting();

        // Register PHPMailer transport
        Mail::extend('phpmailer', function (array $config = []) {
            return new \App\Mail\PHPMailerTransport($config);
        });

        // Register backup email with attachment listener
        Event::listen(
            BackupZipWasCreated::class,
            MailBackupWithAttachment::class
        );

        // Dynamically configure Sanctum stateful domains for multi-tenancy
        // This must be done here (not config file) to avoid CLI crashes when request() is unavailable
        if (!$this->app->runningInConsole() && request()->hasHeader('Origin')) {
            $origin = request()->header('Origin');
            $host = parse_url($origin, PHP_URL_HOST);
            
            if ($host && str_ends_with($host, 'csl-brands.com')) {
                $currentStateful = config('sanctum.stateful', []);
                if (!in_array($host, $currentStateful)) {
                    $currentStateful[] = $host;
                    config(['sanctum.stateful' => $currentStateful]);
                }
            }
        }
    }

    /**
     * Configure the rate limiters for the application.
     */
    protected function configureRateLimiting(): void
    {
        // API rate limiter - 120 requests per minute per user/IP (increased for SPA needs)
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        // Public API rate limiter - 180 requests per minute for public/readonly endpoints
        // Used for server-side rendering and unauthenticated browsing
        RateLimiter::for('public-api', function (Request $request) {
            return Limit::perMinute(180)->by($request->ip());
        });

        // Login rate limiter - 5 attempts per minute per IP
        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        // Password reset rate limiter - 3 attempts per minute per email/IP
        RateLimiter::for('reset', function (Request $request) {
            return Limit::perMinute(3)->by($request->input('email') . '|' . $request->ip());
        });
    }
}
