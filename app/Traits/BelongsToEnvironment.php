<?php

namespace App\Traits;

use App\Enums\UserRole;
use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Scopes\EnvironmentScope;
use App\Support\Tenancy\EnvironmentContext;
use App\Support\Tenancy\EnvironmentResolver;
use Illuminate\Auth\Access\AuthorizationException;
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
                // Strict on the write path: a caller who names a tenant they do
                // not belong to gets a deliberate 403 rather than a NOT NULL
                // constraint violation surfacing as a 500.
                $model->environment_id = self::detectEnvironmentId(true);

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
     * first, then an explicit environment_id the caller supplied, then the
     * session, else null. There is deliberately no fallback tenant.
     *
     * The supplied value is membership-checked. Several endpoints pass their
     * environment in the request rather than relying on the host, so the path
     * has to stay; unchecked, it let any authenticated caller stamp a row into
     * another tenant simply by naming it. A caller with no authenticated user
     * (console, queue) is trusted as before.
     */
    public static function detectEnvironmentId(bool $throwWhenRefused = false)
    {
        $request = request();
        $context = $request?->attributes->get(EnvironmentResolver::REQUEST_ATTRIBUTE);

        if ($context instanceof EnvironmentContext && $context->resolved()) {
            return $context->environment->id;
        }

        if ($request && $request->has('environment_id')) {
            $requested = $request->input('environment_id');
            $user = $request->user();

            if (! $user || self::userBelongsToEnvironment($user, (int) $requested)) {
                return $requested;
            }

            if ($throwWhenRefused) {
                throw new AuthorizationException('You are not a member of the environment you named.');
            }

            // Reads fall through unscoped rather than throwing: an admin filter
            // that carries an environment_id must not become a 403 mid-listing.
            return null;
        }

        if (session()->has('current_environment_id')) {
            return session('current_environment_id');
        }

        return null;
    }

    /**
     * Owner or member of the environment they named. Platform staff pass: they
     * act across tenants by definition, and admin screens routinely filter by an
     * environment nobody has a membership row in.
     */
    private static function userBelongsToEnvironment($user, int $environmentId): bool
    {
        $role = $user->role instanceof UserRole ? $user->role->value : $user->role;

        if (in_array($role, [UserRole::ADMIN->value, UserRole::SUPER_ADMIN->value, UserRole::SALES_AGENT->value], true)) {
            return true;
        }

        $userId = (int) $user->getAuthIdentifier();

        if (Environment::query()->whereKey($environmentId)->where('owner_id', $userId)->exists()) {
            return true;
        }

        return EnvironmentUser::query()
            ->where('environment_id', $environmentId)
            ->where('user_id', $userId)
            ->exists();
    }
}
