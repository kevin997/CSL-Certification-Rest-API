<?php

namespace App\Services\Commission;

use App\Models\Commission;
use App\Models\Transaction;
use Illuminate\Support\Facades\Log;

class CommissionService
{
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
     * Calculate transaction amounts including commission
     *
     * @param float $baseAmount The original amount without commission
     * @param int|null $environmentId The environment ID to get commission for
     * @return array Returns ['fee_amount' => float, 'tax_amount' => float, 'total_amount' => float, 'base_amount' => float, 'commission_rate' => float]
     */
    public function calculateTransactionAmounts(float $baseAmount, ?int $environmentId = null): array
    {
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
        
        return $commission->calculateAmounts($baseAmount);
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
        
        // Check if commission has already been applied
        $commissionAlreadyApplied = 
            $transaction->fee_amount !== null && 
            $transaction->tax_amount !== null && 
            $transaction->total_amount > $transaction->amount;
        
        if (!$commissionAlreadyApplied) {
            // Calculate amounts with commission
            $amounts = $this->calculateTransactionAmounts($baseAmount, $transaction->environment_id);
            
            // Update transaction with calculated amounts
            $transaction->fee_amount = $amounts['fee_amount'];
            $transaction->tax_amount = $amounts['tax_amount'];
            $transaction->total_amount = $amounts['total_amount'];
            
            // Log the commission application
            Log::info('Applied commission to transaction', [
                'transaction_id' => $transaction->transaction_id,
                'base_amount' => $baseAmount,
                'fee_amount' => $amounts['fee_amount'],
                'tax_amount' => $amounts['tax_amount'],
                'total_amount' => $amounts['total_amount'],
                'commission_rate' => $amounts['commission_rate']
            ]);
        }
        
        return $transaction;
    }
}
