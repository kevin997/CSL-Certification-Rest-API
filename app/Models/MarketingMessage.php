<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A single AI-generated piece of marketing/retention copy, pooled by channel
 * so the scheduler can pull the next unused message without repeating content.
 *
 * @property string $channel
 * @property string|null $topic
 * @property string|null $title
 * @property string $body
 * @property string|null $email_subject
 * @property string|null $email_html
 * @property array|null $source
 * @property string|null $model
 * @property string $hash
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $sent_at
 */
class MarketingMessage extends Model
{
    public const CHANNEL_GROUP_TIP = 'group_tip';

    public const CHANNEL_STATUS = 'status';

    public const CHANNEL_EMAIL = 'email_campaign';

    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_RETIRED = 'retired';

    protected $fillable = [
        'channel',
        'topic',
        'title',
        'body',
        'email_subject',
        'email_html',
        'source',
        'model',
        'hash',
        'status',
        'sent_at',
    ];

    protected $casts = [
        'source' => 'array',
        'sent_at' => 'datetime',
    ];

    /**
     * Scope: pending messages, oldest first — optionally narrowed to a channel.
     */
    public function scopePending(Builder $query, ?string $channel = null): Builder
    {
        return $query
            ->when($channel !== null, fn (Builder $q) => $q->where('channel', $channel))
            ->where('status', self::STATUS_PENDING)
            ->orderBy('created_at');
    }

    /**
     * Fetch the next pending message for a channel (oldest first), or null
     * when the pool is empty.
     */
    public static function nextFor(string $channel): ?self
    {
        return static::query()->pending($channel)->first();
    }
}
