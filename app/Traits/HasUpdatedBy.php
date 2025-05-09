<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait HasUpdatedBy
{
    /**
     * Boot the trait.
     * Automatically assigns the authenticated user's ID to the updated_by field when updating a model.
     *
     * @return void
     */
    protected static function bootHasUpdatedBy()
    {
        static::updating(function ($model) {
            // Set updated_by if user is authenticated
            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });
    }

    /**
     * Get the user who last updated this model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function updater()
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }
    
    /**
     * Get the last update timestamp and user information.
     *
     * @return array|null
     */
    public function getLastUpdateInfo()
    {
        if (!$this->updated_at || !$this->updated_by) {
            return null;
        }
        
        return [
            'timestamp' => $this->updated_at,
            'user_id' => $this->updated_by,
            'user' => $this->updater
        ];
    }
    
    /**
     * Check if the model was updated by a specific user.
     *
     * @param int $userId
     * @return bool
     */
    public function wasUpdatedBy($userId)
    {
        return $this->updated_by == $userId;
    }
}
