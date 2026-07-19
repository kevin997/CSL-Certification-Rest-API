<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Retries create a NEW PaymentAttempt row; attempts are immutable except for
 * their status which transitions only via the methods below (never mass-assignable).
 */
class PaymentAttempt extends Model
{
    use HasFactory;

    protected $table = 'payment_attempts';

    /**
     * The attributes that are mass assignable.
     *
     * IMPORTANT: 'status' is intentionally excluded — it is system-owned and
     * may only change via the markX() transition methods below.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'uuid',
        'transaction_id',
        'checkout_source_type',
        'checkout_source_id',
        'gateway',
        'gateway_account_environment_id',
        'expected_amount',
        'expected_currency',
        'provider_reference',
        'provider_event_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'expected_amount' => 'decimal:2',
    ];

    /**
     * Payment attempt statuses
     */
    const STATUS_CREATED = 'created';
    const STATUS_PROCESSING = 'processing';
    const STATUS_PAID = 'paid';
    const STATUS_FAILED = 'failed';
    const STATUS_ABANDONED = 'abandoned';

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($paymentAttempt) {
            if (!$paymentAttempt->uuid) {
                $paymentAttempt->uuid = (string) Str::uuid();
            }

            if (!$paymentAttempt->status) {
                $paymentAttempt->status = self::STATUS_CREATED;
            }
        });
    }

    /**
     * Get the transaction associated with this payment attempt.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    /**
     * Transition the payment attempt to "processing".
     */
    public function markProcessing(): bool
    {
        $this->status = self::STATUS_PROCESSING;

        return $this->save();
    }

    /**
     * Transition the payment attempt to "paid".
     */
    public function markPaid(?int $providerEventId = null): bool
    {
        $this->status = self::STATUS_PAID;

        if ($providerEventId !== null) {
            $this->provider_event_id = $providerEventId;
        }

        return $this->save();
    }

    /**
     * Transition the payment attempt to "failed".
     */
    public function markFailed(): bool
    {
        $this->status = self::STATUS_FAILED;

        return $this->save();
    }

    /**
     * Transition the payment attempt to "abandoned".
     */
    public function markAbandoned(): bool
    {
        $this->status = self::STATUS_ABANDONED;

        return $this->save();
    }
}
