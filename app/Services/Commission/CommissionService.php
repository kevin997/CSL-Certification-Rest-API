<?php

namespace App\Services\Commission;

use App\Models\Commission;
use App\Models\Order;
use App\Models\Transaction;
use App\Services\Tax\TaxZoneService;
use App\Services\EnvironmentPaymentConfigService;
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
     * The environment payment config service instance.
     *
     * @var EnvironmentPaymentConfigService
     */
    protected $environmentPaymentConfigService;
    
    /**
     * Create a new commission service instance.
     *
     * @param TaxZoneService $taxZoneService
     * @param EnvironmentPaymentConfigService $environmentPaymentConfigService
     * @return void
     */
    public function __construct(
        TaxZoneService $taxZoneService,
        EnvironmentPaymentConfigService $environmentPaymentConfigService
    ) {
        $this->taxZoneService = $taxZoneService;
        $this->environmentPaymentConfigService = $environmentPaymentConfigService;
    }
    
    /**
     * Get the active commission for an environment
     * Uses platform commission (Environment 1) if centralized gateways enabled
     *
     * @param int|null $environmentId
     * @return Commission|null
     */
    public function getActiveCommission(?int $environmentId = null): ?Commission
    {
        if ($environmentId) {
            // Get effective environment ID (routes to Environment 1 if centralized)
            $effectiveEnvironmentId = $this->environmentPaymentConfigService->getEffectiveEnvironmentId($environmentId);
            
            if ($effectiveEnvironmentId !== $environmentId) {
                Log::info('Using platform commission due to centralized gateways', [
                    'original_environment_id' => $environmentId,
                    'effective_environment_id' => $effectiveEnvironmentId
                ]);
            }
            
            return Commission::getActiveCommission($effectiveEnvironmentId);
        }
        
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
        // KURSA licensing transition (Phase 2): course sales carry 0% platform commission
        // on every plan. The price is the creator's selling price and is used AS-IS — no
        // commission is extracted and NO reverse `/(1 + rate)` calculation is performed.
        // This method is retained as a zero-returning shim so every existing caller keeps
        // working without change. The 17% fallback has been removed permanently.
        return [
            'original_price' => $productPriceWithCommission,
            'commission_amount' => 0.0,
            'commission_rate' => 0.0,
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
        // Phase 2: 0% platform commission. The price is the creator's selling price and is
        // used AS-IS — the platform fee is always 0 and there is no reverse calculation.
        // Tax is computed on the selling price via the same TaxZoneService call as before.
        $taxInfo = $this->taxZoneService->calculateTaxByEnvironment($productPriceWithCommission, $environmentId, $order);
        $taxAmount = $taxInfo['tax_amount'];
        $taxRate = $taxInfo['tax_rate'];
        $taxZone = $taxInfo['zone_name'];

        // Total amount = selling price + tax
        $totalAmount = $productPriceWithCommission + $taxAmount;

        // Log tax zone information
        if ($taxZone === null) {
            Log::warning('No tax zone found for environment, using 0% tax rate', [
                'environment_id' => $environmentId,
                'product_price' => $productPriceWithCommission,
            ]);
        }

        return [
            'fee_amount' => 0.0, // 0% platform commission on course sales
            'tax_amount' => $taxAmount,
            'total_amount' => $totalAmount,
            'base_amount' => $productPriceWithCommission, // Selling price (no commission extracted)
            'commission_rate' => 0.0,
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
        // Phase 2: 0% platform commission — the fee is always 0. The 17% fallback has been
        // removed. Tax computation is unchanged (applied to the base amount as before).
        $feeAmount = 0.0;

        // Calculate the tax amount using the tax zone service
        $taxInfo = $this->taxZoneService->calculateTaxByEnvironment($baseAmount, $environmentId);
        $taxAmount = $taxInfo['tax_amount'];
        $taxRate = $taxInfo['tax_rate'];
        $taxZone = $taxInfo['zone_name'];

        // Calculate the total amount (fee is 0)
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
            'commission_rate' => 0.0,
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
        
        // Commission is already applied when fee_amount is recorded on the transaction.
        // Removed the total_amount > amount check because with 0% tax, total_amount == amount
        // (commission is extracted, not added), causing false negatives.
        $commissionAlreadyApplied =
            $transaction->fee_amount !== null &&
            $transaction->tax_amount !== null;
        
        if (!$commissionAlreadyApplied) {
            // Commission is included in the product price; extract it rather than adding on top.
            $amounts = $this->calculateTransactionAmountsWithCommissionIncluded($baseAmount, $environmentId);
            
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
