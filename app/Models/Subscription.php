<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'environment_id',
        'plan_id',
        'billing_cycle', // 'monthly', 'annual'
        'status', // 'active', 'canceled', 'expired', 'trial'
        'trial_ends_at',
        'starts_at',
        'ends_at',
        'canceled_at',
        'payment_method',
        'payment_details', // JSON field for storing payment details
        'last_payment_at',
        'next_payment_at',
        'setup_fee_paid',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'payment_details' => 'array',
        'trial_ends_at' => 'datetime',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'canceled_at' => 'datetime',
        'last_payment_at' => 'datetime',
        'next_payment_at' => 'datetime',
        'setup_fee_paid' => 'boolean',
    ];

    /**
     * Get the environment that owns the subscription.
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * Get the plan that the subscription is for.
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Check if the subscription is active.
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && 
               ($this->ends_at === null || $this->ends_at->isFuture());
    }

    /**
     * Check if the subscription is in trial period.
     */
    public function onTrial(): bool
    {
        return $this->trial_ends_at !== null && 
               $this->trial_ends_at->isFuture();
    }

    /**
     * Check if the subscription is canceled.
     */
    public function isCanceled(): bool
    {
        return $this->canceled_at !== null;
    }

    /**
     * Check if the subscription has expired.
     */
    public function hasExpired(): bool
    {
        return $this->ends_at !== null && 
               $this->ends_at->isPast();
    }
}
