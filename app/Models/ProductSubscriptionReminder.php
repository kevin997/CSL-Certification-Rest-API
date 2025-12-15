<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductSubscriptionReminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_subscription_id',
        'reminder_key',
        'sent_at',
        'meta',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'meta' => 'array',
    ];

    public function productSubscription(): BelongsTo
    {
        return $this->belongsTo(ProductSubscription::class);
    }
}
