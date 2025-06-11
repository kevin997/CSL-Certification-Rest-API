<?php

namespace App\Services\Commission;

use App\Models\Commission;
use App\Models\Transaction;
use App\Services\Tax\TaxZoneService;
use Illuminate\Support\Facades\Log;

class CommissionService
{
    /**
     * The tax zone service instance.
     *
     * @var TaxZoneService
     */
    protected $taxZoneService;
    
    /**
     * Create a new commission service instance.
     *
     * @param TaxZoneService $taxZoneService
     * @return void
     */
    public function __construct(TaxZoneService $taxZoneService)
    {
        $this->taxZoneService = $taxZoneService;
    }
    
    /**
     * Get the active commission for an environment
     *
     * @param int|null $environmentId
     * @return Commission|null
     */
    public function getActiveCommission(?int $environmentId = null): ?Commission
    {
        return Commission::getActiveCommission($environmentId);
    }
    
    /**
     * Calculate transaction amounts including commission and tax
     *
     * @param float $baseAmount The original amount without commission
     * @param int|null $environmentId The environment ID to get commission for
     * @return array Returns ['fee_amount' => float, 'tax_amount' => float, 'total_amount' => float, 'base_amount' => float, 'commission_rate' => float, 'tax_rate' => float, 'tax_zone' => string|null]
     */
    public function calculateTransactionAmounts(float $baseAmount, ?int $environmentId = null): array
    {
        // Get the active commission
        $commission = $this->getActiveCommission($environmentId);
        
        if (!$commission) {
            Log::warning('No active commission found, using default 17% commission', [
                'environment_id' => $environmentId,
                'base_amount' => $baseAmount
            ]);
            
            // Create a temporary commission object with default rate
            $commission = new Commission();
            $commission->rate = 17.0; // Default 17% if no commission record found
        }
        
        // Calculate the fee amount using the commission rate
        $commissionAmounts = $commission->calculateAmounts($baseAmount);
        $feeAmount = $commissionAmounts['fee_amount'];
        
        // Calculate the tax amount using the tax zone service
        $taxInfo = $this->taxZoneService->calculateTaxByEnvironment($baseAmount, $environmentId);
        $taxAmount = $taxInfo['tax_amount'];
        $taxRate = $taxInfo['tax_rate'];
        $taxZone = $taxInfo['zone_name'];
        
        // Calculate the total amount
        $totalAmount = $baseAmount + $feeAmount + $taxAmount;
        
        // Log tax zone information
        if ($taxZone === null) {
            Log::warning('No tax zone found for environment, using 0% tax rate', [
                'environment_id' => $environmentId,
                'base_amount' => $baseAmount,
                'fee_amount' => $feeAmount
            ]);
        }
        
        return [
            'fee_amount' => $feeAmount,
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'base_amount' => $baseAmount,
            'commission_rate' => $commission->rate,
            'tax_rate' => $taxRate,
            'tax_zone' => $taxZone
        ];
    }
    
    /**
     * Apply commission to a transaction if not already applied
     *
     * @param Transaction $transaction The transaction to apply commission to
     * @param float|null $baseAmount Optional base amount, if not provided will use transaction's amount
     * @return Transaction The updated transaction
     */
    public function applyCommissionToTransaction(Transaction $transaction, ?float $baseAmount = null): Transaction
    {
        // If base amount is not provided, use transaction's amount as base
        $baseAmount = $baseAmount ?? $transaction->amount;
        $environmentId = session("current_environment_id");
        
        // Check if commission has already been applied
        $commissionAlreadyApplied = 
            $transaction->fee_amount !== null && 
            $transaction->tax_amount !== null && 
            $transaction->total_amount > $transaction->amount;
        
        if (!$commissionAlreadyApplied) {
            // Calculate amounts with commission and tax
            $amounts = $this->calculateTransactionAmounts($baseAmount, $environmentId);
            
            // Update transaction with calculated amounts
            $transaction->fee_amount = $amounts['fee_amount'];
            $transaction->tax_amount = $amounts['tax_amount'];
            $transaction->total_amount = $amounts['total_amount'];
            
            // Log the commission and tax application
            Log::info('Applied commission and tax to transaction', [
                'transaction_id' => $transaction->transaction_id,
                'base_amount' => $baseAmount,
                'fee_amount' => $amounts['fee_amount'],
                'tax_amount' => $amounts['tax_amount'],
                'total_amount' => $amounts['total_amount'],
                'commission_rate' => $amounts['commission_rate'],
                'tax_rate' => $amounts['tax_rate'],
                'tax_zone' => $amounts['tax_zone']
            ]);
        }
        
        return $transaction;
    }
}
