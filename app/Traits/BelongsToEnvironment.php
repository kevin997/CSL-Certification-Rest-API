<?php

namespace App\Traits;

use App\Models\Environment;
use App\Scopes\EnvironmentScope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

trait BelongsToEnvironment
{
    /**
     * Boot the trait.
     *
     * @return void
     */
    protected static function bootBelongsToEnvironment()
    {
        static::addGlobalScope(new EnvironmentScope);

        // Auto-set environment_id when creating a new model.
        //
        // array_key_exists, not a falsy check: `! $model->environment_id` cannot
        // tell an OMITTED attribute from one deliberately set to null, so a row
        // meant to be platform-scoped was silently reassigned to whichever
        // environment the request resolved. For PaymentGatewaySetting that made
        // platform gateways uncreatable through the model, while the code that
        // reads them queries exactly whereNull('environment_id').
        static::creating(function ($model) {
            if (! array_key_exists('environment_id', $model->getAttributes())) {
                $model->environment_id = self::detectEnvironmentId();

                if ($model->environment_id) {
                    Log::info('BelongsToEnvironment: Auto-set environment_id', [
                        'model' => get_class($model),
                        'environment_id' => $model->environment_id,
                    ]);
                } else {
                    Log::warning('BelongsToEnvironment: Could not determine environment_id for new model', [
                        'model' => get_class($model),
                    ]);
                }
            }
        });
    }

    /**
     * Get the environment that owns this model.
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * Scope a query to only include records from a specific environment.
     *
     * @param  Builder  $query
     * @param  int|null  $environmentId
     * @return Builder
     */
    public function scopeInEnvironment($query, $environmentId = null)
    {
        $environmentId = $environmentId ?: self::detectEnvironmentId();

        if ($environmentId) {
            return $query->where('environment_id', $environmentId);
        }

        return $query;
    }

    /**
     * Detect the current environment ID from various sources.
     *
     * @return int|null
     */
    public static function detectEnvironmentId()
    {
        // Priority 1: Check request parameter (from API)
        $request = request();
        if ($request && $request->has('environment_id')) {
            return $request->input('environment_id');
        }

        // Priority 2: Check session
        if (session()->has('current_environment_id')) {
            return session('current_environment_id');
        }

        // Priority 3: Try to detect from domain
        $environment = self::detectEnvironmentFromDomain();
        if ($environment) {
            // Store in session for future use
            session(['current_environment_id' => $environment->id]);

            return $environment->id;
        }

        // Priority 4: Fallback to first active environment
        $fallbackEnvironment = Environment::where('is_active', true)->first();
        if ($fallbackEnvironment) {
            Log::info('BelongsToEnvironment: Using fallback environment', [
                'environment_id' => $fallbackEnvironment->id,
            ]);
            session(['current_environment_id' => $fallbackEnvironment->id]);

            return $fallbackEnvironment->id;
        }

        return null;
    }

    /**
     * Detect environment from the current domain.
     *
     * @return Environment|null
     */
    private static function detectEnvironmentFromDomain()
    {
        $request = request();
        if (! $request) {
            return null;
        }

        // Try to get domain from headers in priority order
        $domain = null;
        $apiDomain = $request->getHost();

        // First check for the explicit X-Frontend-Domain header
        $frontendDomainHeader = $request->header('X-Frontend-Domain');

        // Then try Origin or Referer as fallbacks
        $origin = $request->header('Origin');
        $referer = $request->header('Referer');

        if ($frontendDomainHeader) {
            $domain = $frontendDomainHeader;
        } elseif ($origin) {
            $parsedOrigin = parse_url($origin);
            $domain = $parsedOrigin['host'] ?? null;
        } elseif ($referer) {
            $parsedReferer = parse_url($referer);
            $domain = $parsedReferer['host'] ?? null;
        }

        // If still no domain, fall back to the API domain
        if (! $domain) {
            $domain = $apiDomain;
        }

        // Find the environment that matches the domain
        $environment = Environment::where('primary_domain', $domain)
            ->orWhereJsonContains('additional_domains', $domain)
            ->where('is_active', true)
            ->first();

        if ($environment) {
            Log::info('BelongsToEnvironment: Environment detected from domain', [
                'domain' => $domain,
                'environment_id' => $environment->id,
            ]);
        }

        return $environment;
    }
}
