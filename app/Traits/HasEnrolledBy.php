<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait HasEnrolledBy
{
    /**
     * Boot the trait.
     * Automatically assigns the authenticated user's ID to the enrolled_by field when creating a model.
     *
     * @return void
     */
    protected static function bootHasEnrolledBy()
    {
        static::creating(function ($model) {
            // Only set enrolled_by if it's not already set and user is authenticated
            if (!$model->enrolled_by && Auth::check()) {
                $model->enrolled_by = Auth::id();
            }
            
            // Set enrolled_at timestamp if it's not already set
            if (!$model->enrolled_at) {
                $model->enrolled_at = now();
            }
        });
    }

    /**
     * Get the user who enrolled this model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function enroller()
    {
        return $this->belongsTo(\App\Models\User::class, 'enrolled_by');
    }
    
    /**
     * Manually enroll by a specific user.
     *
     * @param int $userId The ID of the user performing the enrollment
     * @return bool
     */
    public function enrollBy($userId)
    {
        $this->enrolled_by = $userId;
        $this->enrolled_at = now();
        return $this->save();
    }
    
    /**
     * Enroll by the currently authenticated user.
     *
     * @return bool
     */
    public function enrollByCurrentUser()
    {
        if (Auth::check()) {
            return $this->enrollBy(Auth::id());
        }
        
        return false;
    }
}
