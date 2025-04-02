<?php

namespace App\Models;

use App\Traits\BelongsToEnvironment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Transaction extends Model
{
    use HasFactory, SoftDeletes, BelongsToEnvironment;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'transaction_id',
        'environment_id',
        'payment_gateway_setting_id',
        'order_id',
        'invoice_id',
        'customer_id',
        'customer_email',
        'customer_name',
        'amount',
        'fee_amount',
        'tax_amount',
        'total_amount',
        'currency',
        'status',
        'payment_method',
        'payment_method_details',
        'gateway_transaction_id',
        'gateway_status',
        'gateway_response',
        'description',
        'notes',
        'ip_address',
        'user_agent',
        'paid_at',
        'refunded_at',
        'refund_reason',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'gateway_response' => 'json',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
    ];

    /**
     * Transaction statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transaction) {
            // Generate a UUID for the transaction if not provided
            if (!$transaction->transaction_id) {
                $transaction->transaction_id = (string) Str::uuid();
            }

            // Calculate total amount if not provided
            if (!$transaction->total_amount) {
                $transaction->total_amount = $transaction->amount + $transaction->fee_amount + $transaction->tax_amount;
            }
        });
    }

    /**
     * Get the environment that the transaction belongs to.
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * Get the payment gateway setting that the transaction belongs to.
     */
    public function paymentGatewaySetting(): BelongsTo
    {
        return $this->belongsTo(PaymentGatewaySetting::class);
    }

    /**
     * Scope a query to only include transactions with a specific status.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $status
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include transactions for a specific environment.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  int  $environmentId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForEnvironment($query, $environmentId)
    {
        return $query->where('environment_id', $environmentId);
    }

    /**
     * Scope a query to only include transactions for a specific order.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $orderId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForOrder($query, $orderId)
    {
        return $query->where('order_id', $orderId);
    }

    /**
     * Scope a query to only include transactions for a specific customer.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $customerId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForCustomer($query, $customerId)
    {
        return $query->where('customer_id', $customerId);
    }

    /**
     * Scope a query to only include transactions within a date range.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @param  string  $startDate
     * @param  string  $endDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBetweenDates($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    /**
     * Mark the transaction as completed.
     *
     * @param  string|null  $gatewayTransactionId
     * @param  string|null  $gatewayStatus
     * @param  array|null  $gatewayResponse
     * @return bool
     */
    public function markAsCompleted($gatewayTransactionId = null, $gatewayStatus = null, $gatewayResponse = null)
    {
        return $this->updateStatus(
            self::STATUS_COMPLETED,
            $gatewayTransactionId,
            $gatewayStatus,
            $gatewayResponse,
            now()
        );
    }

    /**
     * Mark the transaction as failed.
     *
     * @param  string|null  $gatewayTransactionId
     * @param  string|null  $gatewayStatus
     * @param  array|null  $gatewayResponse
     * @return bool
     */
    public function markAsFailed($gatewayTransactionId = null, $gatewayStatus = null, $gatewayResponse = null)
    {
        return $this->updateStatus(
            self::STATUS_FAILED,
            $gatewayTransactionId,
            $gatewayStatus,
            $gatewayResponse
        );
    }

    /**
     * Mark the transaction as refunded.
     *
     * @param  string|null  $reason
     * @param  string|null  $gatewayTransactionId
     * @param  string|null  $gatewayStatus
     * @param  array|null  $gatewayResponse
     * @return bool
     */
    public function markAsRefunded($reason = null, $gatewayTransactionId = null, $gatewayStatus = null, $gatewayResponse = null)
    {
        $this->refund_reason = $reason;
        $this->refunded_at = now();

        return $this->updateStatus(
            self::STATUS_REFUNDED,
            $gatewayTransactionId,
            $gatewayStatus,
            $gatewayResponse
        );
    }

    /**
     * Update the transaction status and related fields.
     *
     * @param  string  $status
     * @param  string|null  $gatewayTransactionId
     * @param  string|null  $gatewayStatus
     * @param  array|null  $gatewayResponse
     * @param  \Carbon\Carbon|null  $paidAt
     * @return bool
     */
    protected function updateStatus($status, $gatewayTransactionId = null, $gatewayStatus = null, $gatewayResponse = null, $paidAt = null)
    {
        $this->status = $status;

        if ($gatewayTransactionId) {
            $this->gateway_transaction_id = $gatewayTransactionId;
        }

        if ($gatewayStatus) {
            $this->gateway_status = $gatewayStatus;
        }

        if ($gatewayResponse) {
            $this->gateway_response = $gatewayResponse;
        }

        if ($paidAt) {
            $this->paid_at = $paidAt;
        }

        return $this->save();
    }

    /**
     * Check if the transaction is completed.
     *
     * @return bool
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the transaction is pending.
     *
     * @return bool
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the transaction is processing.
     *
     * @return bool
     */
    public function isProcessing(): bool
    {
        return $this->status === self::STATUS_PROCESSING;
    }

    /**
     * Check if the transaction is failed.
     *
     * @return bool
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }

    /**
     * Check if the transaction is refunded.
     *
     * @return bool
     */
    public function isRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED || $this->status === self::STATUS_PARTIALLY_REFUNDED;
    }
}
