<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Durable checkout intent for a licence purchase (doc §11 LicenceCheckout).
 *
 * `environment_id` is null for anonymous sales-site onboarding until a verified
 * paid event provisions the environment (doc §5, §9.5). `onboarding_payload`
 * carries the full environment payload for that deferred provisioning.
 */
class LicenceCheckout extends Model
{
    use HasFactory;

    protected $table = 'licence_checkouts';

    protected $fillable = [
        'uuid',
        'environment_id',
        'plan_id',
        'plan_type',
        'quoted_amount',
        'quoted_currency',
        'tax_snapshot',
        'onboarding_payload',
        'status',
        'return_url',
        'payment_attempt_id',
        'transaction_id',
        'expires_at',
    ];

    protected $casts = [
        'quoted_amount' => 'decimal:2',
        'tax_snapshot' => 'array',
        'onboarding_payload' => 'array',
        'expires_at' => 'datetime',
    ];

    const STATUS_PENDING_PAYMENT = 'pending_payment';
    const STATUS_PAID = 'paid';
    const STATUS_FAILED = 'failed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_EXPIRED = 'expired';

    protected static function boot()
    {
        parent::boot();

        static::creating(function (LicenceCheckout $checkout) {
            if (! $checkout->uuid) {
                $checkout->uuid = (string) Str::uuid();
            }

            if (! $checkout->status) {
                $checkout->status = self::STATUS_PENDING_PAYMENT;
            }
        });
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    /**
     * The tax amount captured in the immutable quote snapshot.
     */
    public function taxAmount(): float
    {
        return (float) ($this->tax_snapshot['tax_amount'] ?? 0);
    }

    /**
     * quoted_amount + tax.
     */
    public function totalAmount(): float
    {
        return round((float) $this->quoted_amount + $this->taxAmount(), 2);
    }
}
