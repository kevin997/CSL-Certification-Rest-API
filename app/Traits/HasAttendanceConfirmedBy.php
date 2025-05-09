<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait HasAttendanceConfirmedBy
{
    /**
     * Boot the trait.
     * Automatically assigns the authenticated user's ID to the attendance_confirmed_by field when updating status to 'attended'.
     *
     * @return void
     */
    protected static function bootHasAttendanceConfirmedBy()
    {
        static::updating(function ($model) {
            // Set attendance_confirmed_by and attendance_confirmed_at when the status is being changed to 'attended'
            // and the user is authenticated
            if ($model->isDirty('status') && 
                $model->status === 'attended' && 
                Auth::check()) {
                $model->attendance_confirmed_by = Auth::id();
                $model->attendance_confirmed_at = now();
            }
        });
    }

    /**
     * Get the user who confirmed the attendance.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function attendanceConfirmer()
    {
        return $this->belongsTo(\App\Models\User::class, 'attendance_confirmed_by');
    }
    
    /**
     * Mark the registration as attended.
     *
     * @return bool
     */
    public function confirmAttendance()
    {
        if (Auth::check()) {
            $this->status = 'attended';
            $this->attendance_confirmed_by = Auth::id();
            $this->attendance_confirmed_at = now();
            return $this->save();
        }
        
        return false;
    }
    
    /**
     * Check if attendance has been confirmed.
     *
     * @return bool
     */
    public function isAttendanceConfirmed()
    {
        return $this->status === 'attended' && !is_null($this->attendance_confirmed_by);
    }
    
    /**
     * Mark the registration as no-show.
     *
     * @return bool
     */
    public function markAsNoShow()
    {
        if (Auth::check()) {
            $this->status = 'no-show';
            $this->attendance_confirmed_by = Auth::id();
            $this->attendance_confirmed_at = now();
            return $this->save();
        }
        
        return false;
    }
}
