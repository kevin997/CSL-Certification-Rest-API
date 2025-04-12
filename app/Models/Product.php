<?php

namespace App\Models;

use App\Traits\BelongsToEnvironment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasCreatedBy;
use App\Traits\HasEnvironmentSlug;
use Illuminate\Database\Eloquent\Casts\Attribute;
class Product extends Model
{
    use HasFactory, SoftDeletes, BelongsToEnvironment, HasCreatedBy, HasEnvironmentSlug;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'slug',
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
        'category_id',
        'sku',
        'stock_quantity',
        'is_featured',
        'meta_title',
        'meta_description',
        'meta_keywords',
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
            'stock_quantity' => 'integer',
            'is_featured' => 'boolean',
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
     * Get the category this product belongs to.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category_id');
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
    
    /**
     * Get the reviews for this product.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(ProductReview::class)->where('status', 'approved');
    }
    
    /**
     * Get all reviews for this product (including pending and rejected).
     */
    public function allReviews(): HasMany
    {
        return $this->hasMany(ProductReview::class);
    }
    
    /**
     * Get the average rating for this product.
     */
    protected function averageRating(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->reviews()->avg('rating') ?? 0,
        );
    }
    
    /**
     * Get the review count for this product.
     */
    protected function reviewsCount(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->reviews()->count(),
        );
    }
}
