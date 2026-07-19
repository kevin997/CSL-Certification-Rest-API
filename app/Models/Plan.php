<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    /**
     * The KURSA licence plan type codes (doc §4.1-4.4).
     *
     * @var array<int, string>
     */
    public const LICENCE_TYPES = [
        'free_forever',
        'creator_monthly',
        'white_label_annual',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'type', // 'individual_teacher', 'business_teacher'
        'price_monthly',
        'price_annual',
        'setup_fee',
        'features', // JSON field for storing plan features
        'limits', // JSON field for storing plan limits (e.g., max users, max courses)
        'is_active',
        'sort_order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'features' => 'array',
        'limits' => 'array',
        'price_monthly' => 'decimal:2',
        'price_annual' => 'decimal:2',
        'setup_fee' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the subscriptions for the plan.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Get a numeric limit for this plan.
     *
     * Returns null when the limit is absent/unlimited (e.g. `null` in the
     * stored `limits` json, or the key is missing entirely).
     */
    public function getLimit(string $key): ?int
    {
        $value = $this->limits[$key] ?? null;

        return $value === null ? null : (int) $value;
    }

    /**
     * Determine whether this plan has a truthy feature flag.
     */
    public function hasFeature(string $key): bool
    {
        return (bool) ($this->features[$key] ?? false);
    }

    /**
     * Get a string-level feature value (e.g. 'basic', 'advanced', 'full').
     *
     * Returns null when the feature is absent or not a string level.
     */
    public function featureLevel(string $key): ?string
    {
        $value = $this->features[$key] ?? null;

        return is_string($value) ? $value : null;
    }

    /**
     * Determine whether this plan is one of the KURSA licence plans
     * (as opposed to a legacy plan).
     */
    public function isLicencePlan(): bool
    {
        return in_array($this->type, self::LICENCE_TYPES, true);
    }
}
