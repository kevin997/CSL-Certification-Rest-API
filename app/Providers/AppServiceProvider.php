<?php

namespace App\Providers;

use App\Listeners\MailBackupWithAttachment;
use App\Mail\PHPMailerTransport;
use App\Models\Environment;
use App\Models\User;
use App\Support\Tenancy\DnsHttpDomainProbe;
use App\Support\Tenancy\DomainProbe;
use App\Support\Tenancy\TenantUrl;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Spatie\Backup\Events\BackupZipWasCreated;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(DomainProbe::class, DnsHttpDomainProbe::class);
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
            return new PHPMailerTransport($config);
        });

        // Register backup email with attachment listener
        Event::listen(
            BackupZipWasCreated::class,
            MailBackupWithAttachment::class
        );

        // Point password reset links at the learner's own environment
        $this->configurePasswordResetUrl();

        // Dynamically configure Sanctum stateful domains for multi-tenancy
        // This must be done here (not config file) to avoid CLI crashes when request() is unavailable
        if (! $this->app->runningInConsole() && request()->hasHeader('Origin')) {
            $origin = request()->header('Origin');
            $host = parse_url($origin, PHP_URL_HOST);

            if ($host && str_ends_with($host, 'csl-brands.com')) {
                $currentStateful = config('sanctum.stateful', []);
                if (! in_array($host, $currentStateful)) {
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
            return Limit::perMinute(3)->by($request->input('email').'|'.$request->ip());
        });
    }

    /**
     * Send password reset links to the learner's own environment domain.
     *
     * The stock notification builds its URL from APP_URL, which is the central
     * API host. A learner who requests a reset from their academy therefore
     * received a link to an unfamiliar, unbranded page on another domain and
     * abandoned it. Environment-issued links (EnvironmentResetPasswordMail)
     * already point at the environment; this makes the self-service flow match.
     */
    protected function configurePasswordResetUrl(): void
    {
        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            $environment = $this->resolveEnvironmentForReset($notifiable);

            if ($environment === null) {
                return url(route('password.reset', [
                    'token' => $token,
                    'email' => $notifiable->getEmailForPasswordReset(),
                ], false));
            }

            return TenantUrl::to($environment, '/auth/reset-password', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
                'environment_id' => $environment->id,
            ]);
        });
    }

    /**
     * Pick the environment whose domain a reset link should point at.
     *
     * Preference order: the environment named on the current request, then one
     * the user owns, then any they belong to. A user with no environment falls
     * back to the central host.
     */
    protected function resolveEnvironmentForReset(object $notifiable): ?Environment
    {
        if (! $notifiable instanceof User) {
            return null;
        }

        if (! $this->app->runningInConsole()) {
            $requestedId = request()->input('environment_id');

            if (filled($requestedId)) {
                $requested = $notifiable->environments()->whereKey($requestedId)->first();

                if ($requested !== null) {
                    return $requested;
                }
            }
        }

        return $notifiable->ownedEnvironments()->first()
            ?? $notifiable->environments()->first();
    }
}
