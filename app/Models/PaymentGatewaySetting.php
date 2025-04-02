<?php

namespace App\Models;

use App\Traits\BelongsToEnvironment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentGatewaySetting extends Model
{
    use HasFactory, SoftDeletes, BelongsToEnvironment;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'environment_id',
        'gateway_name',
        'code',
        'description',
        'status',
        'is_default',
        'settings',
        'icon',
        'display_name',
        'transaction_fee_percentage',
        'transaction_fee_fixed',
        'webhook_url',
        'api_version',
        'mode',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'settings' => 'json',
        'status' => 'boolean',
        'is_default' => 'boolean',
        'transaction_fee_percentage' => 'decimal:2',
        'transaction_fee_fixed' => 'decimal:2',
    ];

    /**
     * Get the environment that the payment gateway setting belongs to.
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * Get the transactions for the payment gateway setting.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Scope a query to only include active payment gateway settings.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope a query to only include default payment gateway settings.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope a query to only include payment gateway settings for a specific environment.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $environmentId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForEnvironment($query, $environmentId)
    {
        return $query->where('environment_id', $environmentId);
    }

    /**
     * Calculate the fee for a given amount.
     *
     * @param  float  $amount
     * @return float
     */
    public function calculateFee($amount): float
    {
        $percentageFee = $amount * ($this->transaction_fee_percentage / 100);
        $fixedFee = $this->transaction_fee_fixed;
        
        return $percentageFee + $fixedFee;
    }

    /**
     * Check if the payment gateway is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === true;
    }

    /**
     * Check if the payment gateway is in sandbox mode.
     *
     * @return bool
     */
    public function isSandbox(): bool
    {
        return $this->mode === 'sandbox';
    }

    /**
     * Check if the payment gateway is in live mode.
     *
     * @return bool
     */
    public function isLive(): bool
    {
        return $this->mode === 'live';
    }

    /**
     * Check if the payment gateway is the default for its environment.
     *
     * @return bool
     */
    public function isDefault(): bool
    {
        return $this->is_default === true;
    }

    /**
     * Set this payment gateway as the default for its environment.
     *
     * @return bool
     */
    public function setAsDefault(): bool
    {
        // First, unset any existing default gateway for this environment
        self::query()
            ->where('environment_id', $this->environment_id)
            ->where('is_default', true)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        // Then set this one as default
        $this->is_default = true;
        return $this->save();
    }

    /**
     * Get a specific setting value.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function getSetting(string $key, $default = null)
    {
        $settings = $this->settings ?? [];
        return $settings[$key] ?? $default;
    }

    /**
     * Set a specific setting value.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return $this
     */
    public function setSetting(string $key, $value)
    {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;
        $this->settings = $settings;
        
        return $this;
    }
}
