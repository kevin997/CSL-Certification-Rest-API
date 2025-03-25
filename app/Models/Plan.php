<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

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
}
