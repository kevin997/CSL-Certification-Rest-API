<?php

namespace App\Traits;

use App\Models\Environment;
use App\Scopes\EnvironmentScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        
        // Auto-set environment_id when creating a new model
        static::creating(function ($model) {
            if (!$model->environment_id && session()->has('current_environment_id')) {
                $model->environment_id = session('current_environment_id');
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
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int|null  $environmentId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInEnvironment($query, $environmentId = null)
    {
        $environmentId = $environmentId ?: session('current_environment_id');
        
        if ($environmentId) {
            return $query->where('environment_id', $environmentId);
        }
        
        return $query;
    }
}
