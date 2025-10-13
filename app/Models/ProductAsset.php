<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class ProductAsset extends Model
{
    protected $fillable = [
        'product_id',
        'asset_type',
        'file_path',
        'file_name',
        'file_size',
        'file_type',
        'external_url',
        'email_template',
        'title',
        'description',
        'display_order',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'file_size' => 'integer',
        'display_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(AssetDelivery::class);
    }

    /**
     * Get the full URL for file assets
     */
    public function getFileUrl(): ?string
    {
        if ($this->asset_type !== 'file' || !$this->file_path) {
            return null;
        }

        return Storage::disk('s3')->url($this->file_path);
    }

    /**
     * Scope for active assets only
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
