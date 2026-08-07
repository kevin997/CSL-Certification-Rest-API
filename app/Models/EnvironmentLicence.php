<?php

namespace App\Models;

use App\Observers\EnvironmentLicenceObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The single current licence for an environment (doc §11 aggregate, §12 states).
 *
 * Exactly one row per environment (`environment_id` is unique). Free Forever is
 * a valid, active, non-expiring licence — an environment is never without one
 * (doc §4.1). Paid activation happens ONLY through
 * LicenceService::activateFromPaidEvent, driven by a verified gateway event
 * (doc §9.5).
 */
#[ObservedBy([EnvironmentLicenceObserver::class])]
class EnvironmentLicence extends Model
{
    use HasFactory;

    protected $table = 'environment_licences';

    protected $fillable = [
        'environment_id',
        'plan_id',
        'plan_type',
        'pending_plan_type',
        'status',
        'starts_at',
        'ends_at',
        'trial_ends_at',
        'cancel_at_period_end',
        'grace_ends_at',
        'price_snapshot',
        'activated_by_transaction_id',
        'trial_used_at',
        'reminders_sent',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'trial_ends_at' => 'datetime',
        'grace_ends_at' => 'datetime',
        'trial_used_at' => 'datetime',
        'cancel_at_period_end' => 'boolean',
        'price_snapshot' => 'array',
        'reminders_sent' => 'array',
    ];

    /**
     * Lifecycle states (doc §12).
     */
    const STATUS_FREE_ACTIVE = 'free_active';
    const STATUS_TRIALING = 'trialing';
    const STATUS_PENDING_PAYMENT = 'pending_payment';
    const STATUS_CREATOR_ACTIVE = 'creator_active';
    const STATUS_WHITE_LABEL_ACTIVE = 'white_label_active';
    const STATUS_CANCEL_AT_PERIOD_END = 'cancel_at_period_end';
    const STATUS_PAST_DUE = 'past_due';
    const STATUS_GRACE = 'grace';
    const STATUS_DOWNGRADED_TO_FREE = 'downgraded_to_free';

    const PLAN_FREE = 'free_forever';
    const PLAN_CREATOR = 'creator_monthly';
    const PLAN_WHITE_LABEL = 'white_label_annual';

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * A trial has already been consumed for this environment (one per env, §5).
     */
    public function hasUsedTrial(): bool
    {
        return $this->trial_used_at !== null;
    }

    /**
     * The licence is a currently-running White Label trial.
     */
    public function onTrial(): bool
    {
        return $this->status === self::STATUS_TRIALING
            && $this->trial_ends_at !== null
            && $this->trial_ends_at->isFuture();
    }

    /**
     * Whether this is the non-expiring Free Forever state (either resting
     * free_active or a post-downgrade free state).
     */
    public function isFree(): bool
    {
        return $this->plan_type === self::PLAN_FREE;
    }
}
