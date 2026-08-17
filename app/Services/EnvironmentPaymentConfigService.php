<?php

namespace App\Services;

use App\Models\Environment;
use App\Models\EnvironmentPaymentConfig;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
     */
    public function getConfig(int $environmentId): ?EnvironmentPaymentConfig
    {
        $cacheKey = self::CACHE_PREFIX.$environmentId;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($environmentId) {
            return EnvironmentPaymentConfig::where('environment_id', $environmentId)
                ->where('is_active', true)
                ->first();
        });
    }

    /**
     * Update payment config
     *
     * @throws \Exception
     */
    public function updateConfig(int $environmentId, array $data): EnvironmentPaymentConfig
    {
        $config = EnvironmentPaymentConfig::where('environment_id', $environmentId)->first();

        if (! $config) {
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
     */
    public function enableCentralizedPayments(int $environmentId): bool
    {
        try {
            $config = EnvironmentPaymentConfig::where('environment_id', $environmentId)->first();

            if (! $config) {
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
     */
    public function disableCentralizedPayments(int $environmentId): bool
    {
        try {
            $config = EnvironmentPaymentConfig::where('environment_id', $environmentId)->first();

            if (! $config) {
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
     */
    public function isCentralized(int $environmentId): bool
    {
        $config = $this->getConfig($environmentId);

        return $config ? $config->use_centralized_gateways : false;
    }

    /**
     * Resolve the environment whose gateways centralized tenants transact through.
     *
     * Order: an explicit config id (tests and local work), then the
     * environments.is_centralized_payment_provider flag an admin controls, then
     * the configured primary domain as the bootstrap default. Returns null when
     * nothing resolves — the caller must then fall back to the tenant's own
     * environment rather than guessing.
     *
     * Deliberately uncached: this decides which account a tenant's money lands
     * in, and a stale entry would keep routing to the previous provider after a
     * switch. Both lookups are single indexed reads.
     */
    public function getCentralizedEnvironmentId(): ?int
    {
        $override = config('payments.centralized.environment_id');

        if ($override !== null && $override !== '') {
            return (int) $override;
        }

        $flagged = Environment::query()
            ->where('is_centralized_payment_provider', true)
            ->where('is_active', true)
            ->value('id');

        if ($flagged !== null) {
            return (int) $flagged;
        }

        $domain = (string) config('payments.centralized.environment_domain');

        if ($domain === '') {
            return null;
        }

        // Deliberately not Environment::findByDomain(): its OR/AND precedence
        // lets an inactive environment match on primary_domain.
        $id = Environment::query()
            ->where('primary_domain', $domain)
            ->where('is_active', true)
            ->value('id');

        return $id === null ? null : (int) $id;
    }

    /**
     * Make an environment the centralized gateway provider.
     *
     * Exactly one environment holds the flag, so this clears the previous
     * holder in the same transaction. The new provider also stops borrowing
     * gateways, since it now owns them.
     *
     * @throws \Exception when the environment is missing or inactive
     */
    public function setCentralizedEnvironment(int $environmentId): Environment
    {
        $environment = Environment::find($environmentId);

        if (! $environment) {
            throw new \Exception("Environment not found: {$environmentId}");
        }

        if (! $environment->is_active) {
            throw new \Exception('An inactive environment cannot provide the centralized payment gateways.');
        }

        $previousIds = Environment::query()
            ->where('is_centralized_payment_provider', true)
            ->where('id', '!=', $environmentId)
            ->pluck('id')
            ->all();

        DB::transaction(function () use ($environment, $environmentId, $previousIds) {
            Environment::query()
                ->whereIn('id', $previousIds)
                ->update(['is_centralized_payment_provider' => false]);

            $environment->forceFill(['is_centralized_payment_provider' => true])->save();

            // A provider cannot borrow from itself.
            EnvironmentPaymentConfig::where('environment_id', $environmentId)
                ->update(['use_centralized_gateways' => false]);
        });

        $this->invalidateCache($environmentId);

        foreach ($previousIds as $previousId) {
            $this->invalidateCache((int) $previousId);
        }

        Log::info('Centralized payment provider changed', [
            'environment_id' => $environmentId,
            'previous_environment_ids' => $previousIds,
        ]);

        return $environment->refresh();
    }

    /**
     * Whether this environment is itself the centralized gateway environment.
     *
     * It owns the gateways everyone else borrows, so it must never opt in to
     * borrowing from itself.
     */
    public function isCentralizedEnvironment(int $environmentId): bool
    {
        return $this->getCentralizedEnvironmentId() === $environmentId;
    }

    /**
     * Get the effective environment ID for payment/commission operations.
     *
     * Returns the centralized environment's ID when this environment has opted
     * in to centralized gateways, otherwise the environment's own ID.
     */
    public function getEffectiveEnvironmentId(int $environmentId): int
    {
        if (! $this->isCentralized($environmentId)) {
            return $environmentId;
        }

        $centralizedId = $this->getCentralizedEnvironmentId();

        if ($centralizedId === null) {
            // Misconfiguration. Falling back to the tenant's own environment
            // surfaces as "no gateway configured"; routing to an arbitrary
            // environment would silently send money to the wrong account.
            Log::error('Centralized payment environment could not be resolved', [
                'environment_id' => $environmentId,
                'configured_domain' => config('payments.centralized.environment_domain'),
            ]);

            return $environmentId;
        }

        return $centralizedId;
    }

    /**
     * Get default config values
     */
    public function getDefaultConfig(): array
    {
        return [
            'use_centralized_gateways' => false,
            'commission_rate' => 0, // Phase 2: 0% platform commission on course sales
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
     * Public because callers that write the config row directly (rather than
     * through this service) must still drop the cached copy, otherwise the
     * change takes up to CACHE_TTL to become visible.
     */
    public function invalidateCache(int $environmentId): void
    {
        $cacheKey = self::CACHE_PREFIX.$environmentId;
        Cache::forget($cacheKey);

        Log::debug('Cache invalidated', [
            'cache_key' => $cacheKey,
            'environment_id' => $environmentId,
        ]);
    }
}
