<?php

namespace App\Services\Commission;

use App\Models\Commission;
use App\Models\Order;
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
     * Extract commission from product price (new flow - commission already included in product price)
     *
     * @param float $productPriceWithCommission The product price that already includes commission
     * @param int|null $environmentId The environment ID to get commission for
     * @return array Returns ['original_price' => float, 'commission_amount' => float, 'commission_rate' => float]
     */
    public function extractCommissionFromProductPrice(float $productPriceWithCommission, ?int $environmentId = null): array
    {
        // Get the active commission
        $commission = $this->getActiveCommission($environmentId);
        
        if (!$commission) {
            Log::warning('No active commission found, using default 17% commission', [
                'environment_id' => $environmentId,
                'product_price_with_commission' => $productPriceWithCommission
            ]);
            
            // Create a temporary commission object with default rate
            $commission = new Commission();
            $commission->rate = 17.0; // Default 17% if no commission record found
        }
        
        $commissionRate = $commission->rate / 100; // Convert percentage to decimal
        
        // Calculate original price: productPriceWithCommission = originalPrice + (originalPrice * commissionRate)
        // So: productPriceWithCommission = originalPrice * (1 + commissionRate)
        // Therefore: originalPrice = productPriceWithCommission / (1 + commissionRate)
        $originalPrice = $productPriceWithCommission / (1 + $commissionRate);
        $commissionAmount = $productPriceWithCommission - $originalPrice;
        
        return [
            'original_price' => $originalPrice,
            'commission_amount' => $commissionAmount,
            'commission_rate' => $commission->rate
        ];
    }
    
    /**
     * Calculate transaction amounts with tax only (commission already included in product price)
     *
     * @param float $productPriceWithCommission The product price that already includes commission
     * @param int|null $environmentId The environment ID to get commission for
     * @param Order|null $order Optional order to use for billing country if environment has no country code
     * @return array Returns ['fee_amount' => float, 'tax_amount' => float, 'total_amount' => float, 'base_amount' => float, 'commission_rate' => float, 'tax_rate' => float, 'tax_zone' => string|null]
     */
    public function calculateTransactionAmountsWithCommissionIncluded(float $productPriceWithCommission, ?int $environmentId = null, ?Order $order = null): array
    {
        // Extract commission from product price
        $commissionInfo = $this->extractCommissionFromProductPrice($productPriceWithCommission, $environmentId);
        
        $originalPrice = $commissionInfo['original_price'];
        $commissionAmount = $commissionInfo['commission_amount'];
        $commissionRate = $commissionInfo['commission_rate'];
        
        // Calculate the tax amount using the tax zone service (tax is applied to the original price, not the price with commission)
        $taxInfo = $this->taxZoneService->calculateTaxByEnvironment($originalPrice, $environmentId, $order);
        $taxAmount = $taxInfo['tax_amount'];
        $taxRate = $taxInfo['tax_rate'];
        $taxZone = $taxInfo['zone_name'];
        
        // Total amount = product price with commission + tax
        $totalAmount = $productPriceWithCommission + $taxAmount;
        
        // Log tax zone information
        if ($taxZone === null) {
            Log::warning('No tax zone found for environment, using 0% tax rate', [
                'environment_id' => $environmentId,
                'product_price_with_commission' => $productPriceWithCommission,
                'original_price' => $originalPrice,
                'commission_amount' => $commissionAmount
            ]);
        }
        
        return [
            'fee_amount' => $commissionAmount, // Commission amount extracted from product price
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'base_amount' => $originalPrice, // Original price without commission
            'commission_rate' => $commissionRate,
            'tax_rate' => $taxRate,
            'tax_zone' => $taxZone
        ];
    }
    
    /**
     * Calculate transaction amounts including commission and tax (legacy method - for backward compatibility)
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
