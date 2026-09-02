<?php

namespace App\Traits;

use App\Models\Environment;
use App\Scopes\EnvironmentScope;
use App\Support\Tenancy\EnvironmentContext;
use App\Support\Tenancy\EnvironmentResolver;
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
     * The environment new rows are stamped with: the resolved request context
     * first, then an explicit environment_id input (console and queue callers
     * pass one), else null. There is deliberately no fallback tenant.
     */
    public static function detectEnvironmentId()
    {
        $request = request();
        $context = $request?->attributes->get(EnvironmentResolver::REQUEST_ATTRIBUTE);

        if ($context instanceof EnvironmentContext && $context->resolved()) {
            return $context->environment->id;
        }

        if ($request && $request->has('environment_id')) {
            return $request->input('environment_id');
        }

        if (session()->has('current_environment_id')) {
            return session('current_environment_id');
        }

        return null;
    }
}
