<?php

namespace App\Models;

use App\Traits\HasCreatedBy;
use App\Traits\HasUpdatedBy;
use App\Traits\BelongsToEnvironment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Transaction extends Model
{
    use HasFactory, SoftDeletes, BelongsToEnvironment, HasCreatedBy, HasUpdatedBy;

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
        'tax_rate',
        'tax_zone',
        'country_code',
        'state_code',
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
        // Currency conversion fields
        'converted_amount',
        'target_currency',
        'exchange_rate',
        'source_currency',
        'original_amount',
        'conversion_date',
        'conversion_provider',
        'conversion_meta',
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
        'tax_rate' => 'decimal:2',
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
     * Convert the total_amount to XAF currency and store conversion data
     *
     * @param float|null $amount Amount to convert (defaults to transaction's total_amount if null)
     * @param bool $saveToDatabase Whether to save the conversion data to the database
     * @return float|null The converted amount in XAF or null if conversion fails
     */
    public function convertToXAF(?float $amount = null, bool $saveToDatabase = true): ?float
    {
        // Use provided amount or fall back to transaction's total_amount
        $amountToConvert = $amount ?? $this->total_amount;
        $sourceCurrency = strtoupper($this->currency);
        $targetCurrency = 'XAF';
        
        // If amount is null or 0, return 0
        if ($amountToConvert === null || $amountToConvert == 0) {
            return 0;
        }
        
        // If currency is already XAF, return the amount as is
        if ($sourceCurrency === $targetCurrency) {
            return $amountToConvert;
        }
        
        // Cache key based on the currency
        $cacheKey = 'exchange_rate_' . $sourceCurrency . '_to_' . $targetCurrency;
        $conversionProvider = 'ExchangeRate-API';
        $conversionMeta = [];
        
        // Try to get exchange rate from cache (cache for 24 hours)
        $exchangeRate = Cache::remember($cacheKey, 86400, function () use ($sourceCurrency, &$conversionMeta, &$conversionProvider) {
            try {
                // Call the ExchangeRate-API (free version, no API key required)
                $response = Http::get('https://open.er-api.com/v6/latest/' . $sourceCurrency);
                
                if ($response->successful()) {
                    $data = $response->json();
                    
                    // Store additional metadata about the conversion
                    $conversionMeta = [
                        'provider_response' => [
                            'time_last_update_utc' => $data['time_last_update_utc'] ?? null,
                            'time_next_update_utc' => $data['time_next_update_utc'] ?? null,
                            'base_code' => $data['base_code'] ?? null,
                        ],
                        'api_status' => 'success',
                        'api_url' => 'https://open.er-api.com/v6/latest/' . $sourceCurrency
                    ];
                    
                    // Check if XAF rate exists in the response
                    if (isset($data['rates']['XAF'])) {
                        return $data['rates']['XAF'];
                    }
                } else {
                    $conversionMeta = [
                        'api_status' => 'error',
                        'api_response' => $response->body(),
                        'api_url' => 'https://open.er-api.com/v6/latest/' . $sourceCurrency
                    ];
                }
                
                // Fallback exchange rates if API call fails
                // These are approximate and should be updated regularly
                $fallbackRates = [
                    'USD' => 600.0,   // 1 USD ≈ 600 XAF
                    'EUR' => 655.957, // 1 EUR = 655.957 XAF (fixed rate)
                    'GBP' => 780.0,   // 1 GBP ≈ 780 XAF
                    'CAD' => 450.0,   // 1 CAD ≈ 450 XAF
                    'AUD' => 400.0,   // 1 AUD ≈ 400 XAF
                ];
                
                $conversionProvider = 'Fallback Rates';
                $conversionMeta['used_fallback'] = true;
                
                return $fallbackRates[$sourceCurrency] ?? null;
                
            } catch (\Exception $e) {
                Log::error('Currency conversion error: ' . $e->getMessage());
                $conversionProvider = 'Error';
                $conversionMeta = [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'used_fallback' => false
                ];
                return null;
            }
        });
        
        // If we couldn't get an exchange rate, return null
        if ($exchangeRate === null) {
            return null;
        }
        
        // Convert the amount and round to 2 decimal places
        $convertedAmount = round($amountToConvert * $exchangeRate, 2);
        
        // Store the conversion data in the database if requested
        if ($saveToDatabase) {
            $this->update([
                'converted_amount' => $convertedAmount,
                'target_currency' => $targetCurrency,
                'exchange_rate' => $exchangeRate,
                'source_currency' => $sourceCurrency,
                'original_amount' => $amountToConvert,
                'conversion_date' => now(),
                'conversion_provider' => $conversionProvider,
                'conversion_meta' => json_encode($conversionMeta)
            ]);
        }
        
        return $convertedAmount;
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
