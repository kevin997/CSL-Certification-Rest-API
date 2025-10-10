<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Withdrawal Request Model
 *
 * Manages withdrawal requests from instructors for their earned commissions.
 */
class WithdrawalRequest extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'withdrawal_requests';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'environment_id',
        'requested_by',
        'amount',
        'currency',
        'status',
        'withdrawal_method',
        'withdrawal_details',
        'commission_ids',
        'approved_by',
        'approved_at',
        'processed_by',
        'processed_at',
        'payment_reference',
        'rejection_reason',
        'notes',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'withdrawal_details' => 'array',
        'commission_ids' => 'array',
        'approved_at' => 'datetime',
        'processed_at' => 'datetime',
    ];

    /**
     * Get the environment that owns the withdrawal request.
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * Get the user who requested the withdrawal.
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * Get the user who approved the withdrawal.
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Get the user who processed the withdrawal.
     */
    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    /**
     * Get the instructor commissions associated with this withdrawal.
     */
    public function instructorCommissions(): HasMany
    {
        return $this->hasMany(InstructorCommission::class);
    }
}
