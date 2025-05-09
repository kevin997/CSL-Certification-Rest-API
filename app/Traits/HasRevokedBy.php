<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;

trait HasRevokedBy
{
    /**
     * Boot the trait.
     * Automatically assigns the authenticated user's ID to the revoked_by field when updating status to 'revoked'.
     *
     * @return void
     */
    protected static function bootHasRevokedBy()
    {
        static::updating(function ($model) {
            // Set revoked_by and revoked_at when the status is being changed to 'revoked'
            // and the user is authenticated
            if ($model->isDirty('status') && 
                $model->status === 'revoked' && 
                Auth::check()) {
                $model->revoked_by = Auth::id();
                $model->revoked_at = now();
            }
        });
    }

    /**
     * Get the user who revoked this certificate.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function revoker()
    {
        return $this->belongsTo(\App\Models\User::class, 'revoked_by');
    }
    
    /**
     * Revoke the certificate with a reason.
     *
     * @param string $reason The reason for revocation
     * @return bool
     */
    public function revoke($reason = null)
    {
        if (Auth::check()) {
            $this->status = 'revoked';
            $this->revoked_by = Auth::id();
            $this->revoked_at = now();
            
            if ($reason) {
                $this->revocation_reason = $reason;
            }
            
            return $this->save();
        }
        
        return false;
    }
    
    /**
     * Check if the certificate has been revoked.
     *
     * @return bool
     */
    public function isRevoked()
    {
        return $this->status === 'revoked' && !is_null($this->revoked_by);
    }
    
    /**
     * Reinstate a previously revoked certificate.
     *
     * @return bool
     */
    public function reinstate()
    {
        if (Auth::check() && $this->isRevoked()) {
            $this->status = 'active';
            // We don't clear revoked_by or revoked_at to maintain an audit trail
            // But we can add a reinstated_at and reinstated_by if needed
            return $this->save();
        }
        
        return false;
    }
}
