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
     * Calculate the fee and tax amounts based on the commission rate.
     * The commission is split: 70% as fee_amount and 30% as tax_amount.
     *
     * @param float $amount The base amount to calculate commission on
     * @return array Returns ['fee_amount' => float, 'tax_amount' => float, 'total_amount' => float]
     */
    public function calculateAmounts(float $amount): array
    {
        // Calculate the total commission amount
        $commissionAmount = $amount * ($this->rate / 100);
        
        // Split the commission: 70% as fee and 30% as tax
        $feeAmount = round($commissionAmount * 0.7, 2);
        $taxAmount = round($commissionAmount * 0.3, 2);
        
        // Calculate the total amount including commission
        $totalAmount = $amount + $feeAmount + $taxAmount;
        
        return [
            'fee_amount' => $feeAmount,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'base_amount' => $amount,
            'commission_rate' => $this->rate,
        ];
    }
}
