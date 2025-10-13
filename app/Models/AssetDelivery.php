<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AssetDelivery extends Model
{
    const STATUS_ACTIVE = 'active';
    const STATUS_EXPIRED = 'expired';
    const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'order_id',
        'order_item_id',
        'product_asset_id',
        'user_id',
        'environment_id',
        'download_token',
        'secure_url',
        'access_granted_at',
        'expires_at',
        'access_count',
        'max_access_count',
        'last_accessed_at',
        'ip_address',
        'user_agent',
        'status',
    ];

    protected $casts = [
        'access_granted_at' => 'datetime',
        'expires_at' => 'datetime',
        'last_accessed_at' => 'datetime',
        'access_count' => 'integer',
        'max_access_count' => 'integer',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($delivery) {
            if (empty($delivery->download_token)) {
                $delivery->download_token = Str::uuid();
            }
            if (empty($delivery->access_granted_at)) {
                $delivery->access_granted_at = now();
            }
        });
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function productAsset(): BelongsTo
    {
        return $this->belongsTo(ProductAsset::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if delivery is still valid
     */
    public function isValid(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && (!$this->expires_at || $this->expires_at->isFuture())
            && ($this->max_access_count === null || $this->access_count < $this->max_access_count);
    }

    /**
     * Increment access count and update timestamp
     */
    public function recordAccess(string $ipAddress = null, string $userAgent = null): void
    {
        $this->increment('access_count');
        $this->update([
            'last_accessed_at' => now(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
        ]);

        // Auto-expire if max access count reached
        if ($this->max_access_count && $this->access_count >= $this->max_access_count) {
            $this->update(['status' => self::STATUS_EXPIRED]);
        }
    }
}
