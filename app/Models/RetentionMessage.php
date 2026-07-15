<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single retention nudge sent (or attempted) to one recipient for one
 * scenario. Used by the retention engine to enforce cooldowns and to audit.
 *
 * @property string $recipient_type
 * @property string $recipient_id
 * @property string $scenario_key
 * @property string|null $phone
 * @property string $status
 * @property \Illuminate\Support\Carbon|null $sent_at
 */
class RetentionMessage extends Model
{
    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'recipient_type',
        'recipient_id',
        'scenario_key',
        'phone',
        'status',
        'error',
        'meta',
        'sent_at',
    ];

    protected $casts = [
        'meta' => 'array',
        'sent_at' => 'datetime',
    ];
}
