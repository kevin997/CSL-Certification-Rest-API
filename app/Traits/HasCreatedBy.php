<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait HasCreatedBy
{
    /**
     * Boot the trait.
     * Automatically assigns the authenticated user's ID to the created_by field when creating a model.
     *
     * @return void
     */
    protected static function bootHasCreatedBy()
    {
        static::creating(function ($model) {
            // Only set created_by if it's not already set and user is authenticated
            if (!$model->created_by && Auth::check()) {
                $model->created_by = Auth::id();
            }
        });
    }

    /**
     * Get the user who created this model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function creator()
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }
}
