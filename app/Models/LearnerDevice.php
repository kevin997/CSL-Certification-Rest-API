<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One device slot for a learner inside an academy.
 *
 * `uuid` is the opaque value the browser keeps in tenant-scoped storage; it is
 * never returned by the API. `status` moves active -> revoked; an idle device
 * simply falls out of the active window (see config/devices.php).
 */
class LearnerDevice extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_REVOKED = 'revoked';

    public const TYPE_DESKTOP = 'desktop';

    public const TYPE_MOBILE = 'mobile';

    public const TYPE_TABLET = 'tablet';

    public const TYPE_UNKNOWN = 'unknown';

    protected $fillable = [
        'environment_id',
        'user_id',
        'uuid',
        'label',
        'platform',
        'browser',
        'device_type',
        'status',
        'first_seen_at',
        'last_seen_at',
        'last_ip_hash',
        'last_user_agent',
        'revoked_at',
        'revoked_by',
        'revoke_reason',
    ];

    protected $hidden = [
        'uuid',
        'last_ip_hash',
        'last_user_agent',
    ];

    protected function casts(): array
    {
        return [
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function scopeForSlot(Builder $query, int $environmentId, int $userId): Builder
    {
        return $query
            ->where('environment_id', $environmentId)
            ->where('user_id', $userId);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }
}
