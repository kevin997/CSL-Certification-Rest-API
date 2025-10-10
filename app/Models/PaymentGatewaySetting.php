<?php

namespace App\Models;

use App\Traits\BelongsToEnvironment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentGatewaySetting extends Model
{
    use HasFactory, SoftDeletes, BelongsToEnvironment;

    /**
     * Boot the model and register event listeners.
     */
    protected static function boot()
    {
        parent::boot();

        // Validate before creating
        static::creating(function ($gateway) {
            $gateway->validateUniqueConstraints();
        });

        // Validate before updating
        static::updating(function ($gateway) {
            $gateway->validateUniqueConstraints();
        });

        // Handle is_default changes
        static::saving(function ($gateway) {
            if ($gateway->is_default && $gateway->isDirty('is_default')) {
                // Unset any existing default gateway for this environment
                self::query()
                    ->where('environment_id', $gateway->environment_id)
                    ->where('is_default', true)
                    ->where('id', '!=', $gateway->id)
                    ->update(['is_default' => false]);
            }
        });
    }

    /**
     * Validate unique constraints for gateway code.
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    protected function validateUniqueConstraints()
    {
        // Check if another gateway with the same code already exists
        $existingGateway = self::query()
            ->where('code', $this->code)
            ->when($this->exists, function ($query) {
                // Exclude current record when updating
                $query->where('id', '!=', $this->id);
            })
            ->first();

        if ($existingGateway) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'code' => [
                    "A payment gateway with code '{$this->code}' already exists " .
                    "(Environment: {$existingGateway->environment_id}, Gateway: {$existingGateway->gateway_name}). " .
                    "Each gateway code must be unique across all environments."
                ]
            ]);
        }
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'environment_id',
        'gateway_name',
        'code',
        'description',
        'status',
        'is_default',
        'settings',
        'icon',
        'display_name',
        'transaction_fee_percentage',
        'transaction_fee_fixed',
        'webhook_url',
        'success_url',
        'failure_url',
        'api_version',
        'mode',
        'sort_order',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'settings' => 'json',
        'status' => 'boolean',
        'is_default' => 'boolean',
        'transaction_fee_percentage' => 'decimal:2',
        'transaction_fee_fixed' => 'decimal:2',
    ];

    /**
     * Get the environment that the payment gateway setting belongs to.
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * Get the transactions for the payment gateway setting.
     */
    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Scope a query to only include active payment gateway settings.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->where('status', true);
    }

    /**
     * Scope a query to only include default payment gateway settings.
     *
     * @param  \Illuminate\Database\Eloquent\Builder  $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeDefault($query)
    {
        return $query->where('is_default', true);
    }

    /**
     * Scope a query to only include payment gateway settings for a specific environment.
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
     * Calculate the fee for a given amount.
     *
     * @param  float  $amount
     * @return float
     */
    public function calculateFee($amount): float
    {
        $percentageFee = $amount * ($this->transaction_fee_percentage / 100);
        $fixedFee = $this->transaction_fee_fixed;
        
        return $percentageFee + $fixedFee;
    }

    /**
     * Check if the payment gateway is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === true;
    }

    /**
     * Check if the payment gateway is in sandbox mode.
     *
     * @return bool
     */
    public function isSandbox(): bool
    {
        return $this->mode === 'sandbox';
    }

    /**
     * Check if the payment gateway is in live mode.
     *
     * @return bool
     */
    public function isLive(): bool
    {
        return $this->mode === 'live';
    }

    /**
     * Check if the payment gateway is the default for its environment.
     *
     * @return bool
     */
    public function isDefault(): bool
    {
        return $this->is_default === true;
    }

    /**
     * Set this payment gateway as the default for its environment.
     *
     * @return bool
     */
    public function setAsDefault(): bool
    {
        // First, unset any existing default gateway for this environment
        self::query()
            ->where('environment_id', $this->environment_id)
            ->where('is_default', true)
            ->where('id', '!=', $this->id)
            ->update(['is_default' => false]);

        // Then set this one as default
        $this->is_default = true;
        return $this->save();
    }

    /**
     * Get a specific setting value.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function getSetting(string $key, $default = null)
    {
        // Ensure settings is properly handled regardless of its format
        $settings = $this->settings;
        
        // Add detailed logging about the settings format
        \Illuminate\Support\Facades\Log::info(
            "[PaymentGatewaySetting] Settings format for gateway '{$this->code}'", [
            'gateway_id' => $this->id,
            'gateway_code' => $this->code,
            'settings_type' => gettype($settings),
            'is_json_string' => is_string($settings) && $this->isJson($settings),
            'environment' => $this->environment_id
        ]);
        
        // Handle different types of settings data
        if (is_string($settings) && $this->isJson($settings)) {
            // If settings is a JSON string that wasn't auto-decoded
            $settings = json_decode($settings, true) ?? [];
        } elseif (!is_array($settings)) {
            // If settings is neither an array nor a valid JSON string
            $settings = [];
        }
        
        $value = $settings[$key] ?? $default;
        
        // Add logging for sensitive keys without exposing the actual values
        if (in_array($key, ['api_key', 'secret_key', 'webhook_secret'])) {
            \Illuminate\Support\Facades\Log::info(
                "[PaymentGatewaySetting] Retrieved {$key} for gateway '{$this->code}'", [
                'gateway_id' => $this->id,
                'gateway_code' => $this->code,
                'key_present' => !empty($value),
                'environment' => $this->environment_id
            ]);
        }
        
        return $value;
    }
    
    /**
     * Check if a string is valid JSON
     * 
     * @param string $string
     * @return bool
     */
    private function isJson($string) {
        if (!is_string($string)) {
            return false;
        }
        
        json_decode($string);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Set a specific setting value.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return $this
     */
    public function setSetting(string $key, $value)
    {
        $settings = $this->settings ?? [];
        $settings[$key] = $value;
        $this->settings = $settings;
        
        return $this;
    }
}
