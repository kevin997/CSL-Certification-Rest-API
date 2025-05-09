<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait HasReviewedBy
{
    /**
     * Boot the trait.
     * Automatically assigns the authenticated user's ID to the reviewed_by field when updating a model.
     *
     * @return void
     */
    protected static function bootHasReviewedBy()
    {
        static::updating(function ($model) {
            // Set reviewed_by when the review_status is being changed to a reviewed state
            // and the user is authenticated
            if ($model->isDirty('review_status') && 
                in_array($model->review_status, ['approved', 'rejected']) && 
                Auth::check()) {
                $model->reviewed_by = Auth::id();
            }
        });
    }

    /**
     * Get the user who reviewed this model.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function reviewer()
    {
        return $this->belongsTo(\App\Models\User::class, 'reviewed_by');
    }
    
    /**
     * Mark the model as reviewed by the current authenticated user.
     *
     * @param string $status The review status ('approved' or 'rejected')
     * @return bool
     */
    public function markAsReviewed($status = 'approved')
    {
        if (!in_array($status, ['approved', 'rejected'])) {
            throw new \InvalidArgumentException("Review status must be 'approved' or 'rejected'");
        }
        
        if (Auth::check()) {
            $this->reviewed_by = Auth::id();
            $this->review_status = $status;
            return $this->save();
        }
        
        return false;
    }
}
