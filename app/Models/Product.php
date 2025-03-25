<?php

namespace App\Models;

use App\Traits\BelongsToEnvironment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes, BelongsToEnvironment;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'price',
        'discount_price',
        'currency',
        'is_subscription',
        'subscription_interval', // monthly, yearly
        'subscription_interval_count', // 1, 3, 6, 12
        'trial_days',
        'status', // draft, active, inactive
        'thumbnail_path',
        'created_by',
        'environment_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'float',
            'discount_price' => 'float',
            'is_subscription' => 'boolean',
            'subscription_interval_count' => 'integer',
            'trial_days' => 'integer',
        ];
    }

    /**
     * Get the user who created this product.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the courses included in this product.
     */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'product_courses')
            ->withTimestamps();
    }

    /**
     * Get the orders for this product.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
