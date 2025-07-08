<?php

namespace App\Services\Tax;

use App\Models\TaxZone;
use App\Models\Environment;
use App\Models\Order;
use Illuminate\Support\Facades\Log;

class TaxZoneService
{
    /**
     * Find a tax zone by country code and optionally state code.
     *
     * @param string $countryCode
     * @param string|null $stateCode
     * @return TaxZone|null
     */
    public function findTaxZone(string $countryCode, ?string $stateCode = null): ?TaxZone
    {
        $taxZone = TaxZone::findByLocation($countryCode, $stateCode);
        
        if (!$taxZone) {
            // Log missing tax zone with detailed information
            Log::warning('Missing tax zone for location', [
                'country_code' => $countryCode,
                'state_code' => $stateCode,
                'request_time' => now()->toDateTimeString(),
                'source' => 'TaxZoneService::findTaxZone'
            ]);
        }
        
        return $taxZone;
    }
    
    /**
     * Find a tax zone by environment ID.
     * This method assumes that environments have a country_code attribute.
     *
     * @param int|null $environmentId
     * @return TaxZone|null
     */
    public function findTaxZoneByEnvironment(?int $environmentId = null): ?TaxZone
    {
        if (!$environmentId) {
            Log::warning('Missing environment ID for tax zone lookup', [
                'environment_id' => $environmentId,
                'request_time' => now()->toDateTimeString(),
                'source' => 'TaxZoneService::findTaxZoneByEnvironment'
            ]);
            return null;
        }
        
        // Try to load the environment and get its country code
        try {
            $environment = Environment::find($environmentId);
            
            if (!$environment) {
                Log::warning('Environment not found for tax zone lookup', [
                    'environment_id' => $environmentId,
                    'request_time' => now()->toDateTimeString(),
                    'source' => 'TaxZoneService::findTaxZoneByEnvironment'
                ]);
                return null;
            }
            
            if (!$environment->country_code) {
                Log::warning('Environment missing country code for tax zone lookup', [
                    'environment_id' => $environmentId,
                    'environment_name' => $environment->name,
                    'request_time' => now()->toDateTimeString(),
                    'source' => 'TaxZoneService::findTaxZoneByEnvironment'
                ]);
                return null;
            }
            
            return $this->findTaxZone(
                $environment->country_code,
                $environment->state_code ?? null
            );
        } catch (\Exception $e) {
            Log::error('Error finding tax zone by environment', [
                'environment_id' => $environmentId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_time' => now()->toDateTimeString(),
                'source' => 'TaxZoneService::findTaxZoneByEnvironment'
            ]);
            
            return null;
        }
    }
    
    /**
     * Calculate tax amount based on base amount and country/state.
     *
     * @param float $baseAmount
     * @param string $countryCode
     * @param string|null $stateCode
     * @return array Returns ['tax_rate' => float, 'tax_amount' => float]
     */
    public function calculateTaxByLocation(float $baseAmount, string $countryCode, ?string $stateCode = null): array
    {
        $taxZone = $this->findTaxZone($countryCode, $stateCode);
        
        if (!$taxZone) {
            Log::warning('No tax zone found for location, using 0% tax rate', [
                'country_code' => $countryCode,
                'state_code' => $stateCode,
                'base_amount' => $baseAmount,
                'request_time' => now()->toDateTimeString(),
                'source' => 'TaxZoneService::calculateTaxByLocation'
            ]);
            
            return [
                'tax_rate' => 0.0,
                'tax_amount' => 0.0,
                'zone_name' => null
            ];
        }
        
        $taxAmount = round($baseAmount * ($taxZone->tax_rate / 100), 2);
        
        return [
            'tax_rate' => $taxZone->tax_rate,
            'tax_amount' => $taxAmount,
            'zone_name' => $taxZone->zone_name
        ];
    }
    
    /**
     * Calculate tax amount based on base amount and environment ID.
     *
     * @param float $baseAmount
     * @param int|null $environmentId
     * @param Order|null $order Optional order to use for billing country if environment has no country code
     * @return array Returns ['tax_rate' => float, 'tax_amount' => float, 'zone_name' => string|null]
     */
    public function calculateTaxByEnvironment(float $baseAmount, ?int $environmentId = null, ?Order $order = null): array
    {
        if (!$environmentId) {
            Log::warning('Missing environment ID for tax calculation, using 0% tax rate', [
                'base_amount' => $baseAmount,
                'environment_id' => $environmentId,
                'request_time' => now()->toDateTimeString(),
                'source' => 'TaxZoneService::calculateTaxByEnvironment'
            ]);
            
            return [
                'tax_rate' => 0.0,
                'tax_amount' => 0.0,
                'zone_name' => null
            ];
        }
        
        try {
            $environment = Environment::find($environmentId);
            
            if (!$environment) {
                Log::warning('Environment not found for tax calculation, using 0% tax rate', [
                    'environment_id' => $environmentId,
                    'base_amount' => $baseAmount,
                    'request_time' => now()->toDateTimeString(),
                    'source' => 'TaxZoneService::calculateTaxByEnvironment'
                ]);
                
                return [
                    'tax_rate' => 0.0,
                    'tax_amount' => 0.0,
                    'zone_name' => null
                ];
            }
            
            // Prioritize order billing country if available
            if ($order && $order->billing_country) {
                Log::info('Using order billing country for tax calculation', [
                    'environment_id' => $environmentId,
                    'environment_name' => $environment->name,
                    'order_id' => $order->id,
                    'billing_country' => $order->billing_country,
                    'billing_state' => $order->billing_state,
                    'base_amount' => $baseAmount,
                    'request_time' => now()->toDateTimeString(),
                    'source' => 'TaxZoneService::calculateTaxByEnvironment'
                ]);
                
                return $this->calculateTaxByLocation(
                    $baseAmount,
                    $order->billing_country,
                    $order->billing_state ?? null
                );
            }
            
            // Fall back to environment country code if available
            if ($environment->country_code) {
                Log::info('Using environment country code for tax calculation', [
                    'environment_id' => $environmentId,
                    'environment_name' => $environment->name,
                    'country_code' => $environment->country_code,
                    'state_code' => $environment->state_code,
                    'base_amount' => $baseAmount,
                    'request_time' => now()->toDateTimeString(),
                    'source' => 'TaxZoneService::calculateTaxByEnvironment'
                ]);
                
                return $this->calculateTaxByLocation(
                    $baseAmount,
                    $environment->country_code,
                    $environment->state_code ?? null
                );
            }
            
            // No country information available
            Log::warning('No country information available for tax calculation, using 0% tax rate', [
                'environment_id' => $environmentId,
                'environment_name' => $environment->name,
                'base_amount' => $baseAmount,
                'request_time' => now()->toDateTimeString(),
                'source' => 'TaxZoneService::calculateTaxByEnvironment'
            ]);
            
            return [
                'tax_rate' => 0.0,
                'tax_amount' => 0.0,
                'zone_name' => null
            ];
        } catch (\Exception $e) {
            Log::error('Error calculating tax by environment, using 0% tax rate', [
                'environment_id' => $environmentId,
                'base_amount' => $baseAmount,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_time' => now()->toDateTimeString(),
                'source' => 'TaxZoneService::calculateTaxByEnvironment'
            ]);
            
            return [
                'tax_rate' => 0.0,
                'tax_amount' => 0.0,
                'zone_name' => null
            ];
        }
    }
}
