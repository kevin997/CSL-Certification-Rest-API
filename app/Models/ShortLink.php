<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A short, branded checkout link for one order.
 *
 * The code is random and the row expires; resolving it issues a fresh
 * encrypted checkout token, so the long token never appears in a message.
 */
class ShortLink extends Model
{
    protected $fillable = [
        'code',
        'order_id',
        'environment_id',
        'expires_at',
        'hits',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'hits' => 'integer',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }
}
