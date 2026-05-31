<?php

namespace App\Models;

use App\Traits\BelongsToEnvironment;
use App\Traits\HasCreatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class SalesForm extends Model
{
    use HasFactory, SoftDeletes, BelongsToEnvironment, HasCreatedBy;

    const STATUS_DRAFT = 'draft';
    const STATUS_PUBLISHED = 'published';
    const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'environment_id',
        'created_by',
        'title',
        'description',
        'slug',
        'status',
        'cover_image_path',
        'youtube_url',
        'settings',
        'submissions_count',
        'views_count',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'submissions_count' => 'integer',
            'views_count' => 'integer',
        ];
    }

    /**
     * Generate a unique public slug for a form title.
     */
    public static function generateUniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'form';
        $slug = $base;
        $i = 1;

        while (static::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base . '-' . $i;
            $i++;
        }

        return $slug;
    }

    public function fields(): HasMany
    {
        return $this->hasMany(SalesFormField::class)->orderBy('order');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'sales_form_products')->withTimestamps();
    }

    public function accessBlocks(): HasMany
    {
        return $this->hasMany(SalesFormAccessBlock::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(SalesFormSubmission::class);
    }

    /**
     * The public-facing URL for this form, based on the environment domain.
     */
    public function getPublicUrlAttribute(): ?string
    {
        if ($this->environment && $this->environment->primary_domain) {
            return 'https://' . $this->environment->primary_domain . '/forms/' . $this->slug;
        }

        return null;
    }
}
