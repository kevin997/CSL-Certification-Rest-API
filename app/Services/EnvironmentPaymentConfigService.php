<?php

namespace App\Services;

use App\Models\EnvironmentPaymentConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Environment Payment Configuration Service
 *
 * Manages payment gateway configuration for environments,
 * including centralized payment routing and commission rates.
 */
class EnvironmentPaymentConfigService
{
    /**
     * Cache TTL in seconds (1 hour)
     */
    private const CACHE_TTL = 3600;

    /**
     * Cache key prefix
     */
    private const CACHE_PREFIX = 'env_payment_config:';

    /**
     * Get payment config for environment (with caching)
     *
     * @param int $environmentId
     * @return EnvironmentPaymentConfig|null
     */
    public function getConfig(int $environmentId): ?EnvironmentPaymentConfig
    {
        $cacheKey = self::CACHE_PREFIX . $environmentId;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($environmentId) {
            return EnvironmentPaymentConfig::where('environment_id', $environmentId)
                ->where('is_active', true)
                ->first();
        });
    }

    /**
     * Update payment config
     *
     * @param int $environmentId
     * @param array $data
     * @return EnvironmentPaymentConfig
     * @throws \Exception
     */
    public function updateConfig(int $environmentId, array $data): EnvironmentPaymentConfig
    {
        $config = EnvironmentPaymentConfig::where('environment_id', $environmentId)->first();

        if (!$config) {
            throw new \Exception("Payment config not found for environment ID: {$environmentId}");
        }

        $config->update($data);

        // Invalidate cache
        $this->invalidateCache($environmentId);

        Log::info('Payment config updated', [
            'environment_id' => $environmentId,
            'updated_fields' => array_keys($data),
        ]);

        return $config->fresh();
    }

    /**
     * Enable centralized payments
     *
     * @param int $environmentId
     * @return bool
     */
    public function enableCentralizedPayments(int $environmentId): bool
    {
        try {
            $config = EnvironmentPaymentConfig::where('environment_id', $environmentId)->first();

            if (!$config) {
                throw new \Exception("Payment config not found for environment ID: {$environmentId}");
            }

            $config->update(['use_centralized_gateways' => true]);

            // Invalidate cache
            $this->invalidateCache($environmentId);

            Log::info('Centralized payments enabled', [
                'environment_id' => $environmentId,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to enable centralized payments', [
                'environment_id' => $environmentId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Disable centralized payments
     *
     * @param int $environmentId
     * @return bool
     */
    public function disableCentralizedPayments(int $environmentId): bool
    {
        try {
            $config = EnvironmentPaymentConfig::where('environment_id', $environmentId)->first();

            if (!$config) {
                throw new \Exception("Payment config not found for environment ID: {$environmentId}");
            }

            $config->update(['use_centralized_gateways' => false]);

            // Invalidate cache
            $this->invalidateCache($environmentId);

            Log::info('Centralized payments disabled', [
                'environment_id' => $environmentId,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to disable centralized payments', [
                'environment_id' => $environmentId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Check if environment uses centralized gateways
     *
     * @param int $environmentId
     * @return bool
     */
    public function isCentralized(int $environmentId): bool
    {
        $config = $this->getConfig($environmentId);

        return $config ? $config->use_centralized_gateways : false;
    }

    /**
     * Get default config values
     *
     * @return array
     */
    public function getDefaultConfig(): array
    {
        return [
            'use_centralized_gateways' => false,
            'commission_rate' => 0.1700, // 17% for instructors
            'payment_terms' => 'NET_30',
            'withdrawal_method' => null,
            'withdrawal_details' => null,
            'minimum_withdrawal_amount' => 50000.00,
            'is_active' => true,
        ];
    }

    /**
     * Invalidate cache for environment
     *
     * @param int $environmentId
     * @return void
     */
    private function invalidateCache(int $environmentId): void
    {
        $cacheKey = self::CACHE_PREFIX . $environmentId;
        Cache::forget($cacheKey);

        Log::debug('Cache invalidated', [
            'cache_key' => $cacheKey,
            'environment_id' => $environmentId,
        ]);
    }
}
