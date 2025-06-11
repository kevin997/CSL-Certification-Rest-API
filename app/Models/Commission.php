<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Commission extends Model
{
    use SoftDeletes;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'environment_id',
        'name',
        'rate',
        'is_active',
        'description',
        'conditions',
        'priority',
        'valid_from',
        'valid_until',
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'rate' => 'float',
        'is_active' => 'boolean',
        'conditions' => 'json',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'priority' => 'integer',
    ];
    
    /**
     * Get the environment that this commission belongs to.
     *
     * @return BelongsTo
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }
    
    /**
     * Get the active commission for a specific environment or the global default.
     *
     * @param int|null $environmentId
     * @return Commission|null
     */
    public static function getActiveCommission(?int $environmentId = null): ?Commission
    {
        // First try to find an active environment-specific commission
        if ($environmentId) {
            $commission = self::where('environment_id', $environmentId)
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('valid_from')
                        ->orWhere('valid_from', '<=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('valid_until')
                        ->orWhere('valid_until', '>=', now());
                })
                ->orderBy('priority', 'desc')
                ->first();
                
            if ($commission) {
                return $commission;
            }
        }
        
        // Fall back to the global commission if no environment-specific one is found
        return self::whereNull('environment_id')
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', now());
            })
            ->orderBy('priority', 'desc')
            ->first();
    }
    
    /**
     * Calculate the fee amount based on the commission rate.
     * Note: This method no longer calculates tax - that's now handled by TaxZoneService.
     *
     * @param float $amount The base amount to calculate commission on
     * @return array Returns ['fee_amount' => float, 'base_amount' => float, 'commission_rate' => float]
     */
    public function calculateAmounts(float $amount): array
    {
        // Calculate the commission fee amount (100% of commission is now fee)
        $feeAmount = round($amount * ($this->rate / 100), 2);
        
        return [
            'fee_amount' => $feeAmount,
            'base_amount' => $amount,
            'commission_rate' => $this->rate,
        ];
    }
}
