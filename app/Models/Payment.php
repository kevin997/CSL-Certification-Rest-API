<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class Payment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'subscription_id',
        'amount',
        'fee_amount',
        'tax_amount',
        'tax_rate',
        'tax_zone',
        'total_amount',
        'currency',
        'payment_method',
        'transaction_id',
        'status',
        'description',
        'metadata',
        // Currency conversion fields
        'converted_amount',
        'target_currency',
        'exchange_rate',
        'source_currency',
        'original_amount',
        'conversion_date',
        'conversion_provider',
        'conversion_meta',
        'gateway_transaction_id',
        'gateway_status',
        'gateway_response',
        'paid_at',
        'refunded_at',
        'refund_reason',
    ];


    /**
     * Payment statuses
     */
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_PARTIALLY_REFUNDED = 'partially_refunded';

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'fee_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'converted_amount' => 'decimal:2',
        'exchange_rate' => 'decimal:6',
        'original_amount' => 'decimal:2',
        'metadata' => 'array',
        'gateway_response' => 'json',
        'conversion_meta' => 'json',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
        'conversion_date' => 'datetime',
    ];

    /**
     * Get the user that owns the payment.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the subscription that owns the payment.
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the transaction that owns the payment.
     */
    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    /**
     * Boot the model.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($payment) {
            // Generate a UUID for the payment transaction if not provided
            if (!$payment->transaction_id) {
                $payment->transaction_id = 'PXN_' . (string) Str::uuid();
            }

            // Calculate total amount if not provided
            if (!$payment->total_amount) {
                $payment->total_amount = $payment->amount + ($payment->fee_amount ?? 0) + ($payment->tax_amount ?? 0);
            }
        });
    }

    /**
     * Convert the total_amount to XAF currency and store conversion data
     *
     * @param float|null $amount Amount to convert (defaults to payment's total_amount if null)
     * @param bool $saveToDatabase Whether to save the conversion data to the database
     * @return float|null The converted amount in XAF or null if conversion fails
     */
    public function convertToXAF(?float $amount = null, bool $saveToDatabase = true): ?float
    {
        // Use provided amount or fall back to payment's total_amount
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
                'conversion_meta' => $conversionMeta
            ]);
        }
        
        return $convertedAmount;
    }

    /**
     * Mark the payment as completed.
     */
    public function markAsCompleted($gatewayTransactionId = null, $gatewayStatus = null, $gatewayResponse = null)
    {
        return $this->update([
            'status' => self::STATUS_COMPLETED,
            'gateway_transaction_id' => $gatewayTransactionId,
            'gateway_status' => $gatewayStatus,
            'gateway_response' => $gatewayResponse,
            'paid_at' => now()
        ]);
    }

    /**
     * Mark the payment as failed.
     */
    public function markAsFailed($gatewayTransactionId = null, $gatewayStatus = null, $gatewayResponse = null)
    {
        return $this->update([
            'status' => self::STATUS_FAILED,
            'gateway_transaction_id' => $gatewayTransactionId,
            'gateway_status' => $gatewayStatus,
            'gateway_response' => $gatewayResponse
        ]);
    }

    /**
     * Check if the payment is completed.
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if the payment is pending.
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if the payment is failed.
     */
    public function isFailed(): bool
    {
        return $this->status === self::STATUS_FAILED;
    }
}
