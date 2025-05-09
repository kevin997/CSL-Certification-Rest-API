<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait HasGradedBy
{
    /**
     * Boot the trait.
     * Automatically assigns the authenticated user's ID to the graded_by field when updating status to 'graded'.
     *
     * @return void
     */
    protected static function bootHasGradedBy()
    {
        static::updating(function ($model) {
            // Set graded_by and graded_at when the status is being changed to 'graded'
            // and the user is authenticated
            if ($model->isDirty('status') && 
                $model->status === 'graded' && 
                Auth::check()) {
                $model->graded_by = Auth::id();
                $model->graded_at = now();
            }
        });
    }

    /**
     * Get the user who graded this submission.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function grader()
    {
        return $this->belongsTo(\App\Models\User::class, 'graded_by');
    }
    
    /**
     * Mark the submission as graded with a score and optional feedback.
     *
     * @param float|int $score The score to assign
     * @param string|null $feedback Optional feedback text
     * @return bool
     */
    public function grade($score, $feedback = null)
    {
        if (Auth::check()) {
            $this->status = 'graded';
            $this->score = $score;
            $this->feedback = $feedback;
            $this->graded_by = Auth::id();
            $this->graded_at = now();
            return $this->save();
        }
        
        return false;
    }
    
    /**
     * Check if the submission has been graded.
     *
     * @return bool
     */
    public function isGraded()
    {
        return $this->status === 'graded' && !is_null($this->graded_by);
    }
}
