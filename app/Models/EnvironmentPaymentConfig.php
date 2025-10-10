<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Environment Payment Configuration Model
 *
 * Manages payment gateway configuration for environments,
 * including centralized payment routing and commission rates.
 */
class EnvironmentPaymentConfig extends Model
{
    /**
     * The table associated with the model.
     */
    protected $table = 'environment_payment_configs';

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'environment_id',
        'use_centralized_gateways',
        'platform_fee_rate',
        'payment_terms',
        'withdrawal_method',
        'withdrawal_details',
        'minimum_withdrawal_amount',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'use_centralized_gateways' => 'boolean',
        'is_active' => 'boolean',
        'platform_fee_rate' => 'decimal:4',
        'minimum_withdrawal_amount' => 'decimal:2',
        'withdrawal_details' => 'array',
    ];

    /**
     * Get the environment that owns the payment config.
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * Get the withdrawal requests for this environment.
     */
    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class, 'environment_id', 'environment_id');
    }
}
