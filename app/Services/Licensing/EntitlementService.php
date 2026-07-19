<?php

namespace App\Services\Licensing;

use App\Models\Environment;
use App\Models\EnvironmentLicence;
use App\Models\Plan;
use Illuminate\Support\Facades\Cache;

/**
 * Resolves the effective entitlements (plan, limits, features) for an
 * environment from its EnvironmentLicence, falling back to a synthesized
 * Free Forever when no licence row exists (Free is a valid licence, never
 * "missing" — doc §4.1).
 *
 * Phase 4 scope: this service ONLY resolves entitlements. It is NOT enforced
 * anywhere yet — server-side enforcement is Phase 9. The resolution is cached
 * per environment and invalidated by EnvironmentLicenceObserver.
 */
class EntitlementService
{
    private array $resolved;

    private function __construct(array $resolved)
    {
        $this->resolved = $resolved;
    }

    /**
     * Build the entitlement resolver for an environment (cached).
     */
    public static function for(Environment $environment): self
    {
        $ttl = (int) config('licensing.entitlement_cache_ttl', 3600);

        $resolved = Cache::remember(
            self::cacheKey((int) $environment->id),
            $ttl,
            fn () => self::resolve($environment)
        );

        return new self($resolved);
    }

    public static function cacheKey(int $environmentId): string
    {
        return "entitlement:env:{$environmentId}";
    }

    public static function forgetCache(int $environmentId): void
    {
        Cache::forget(self::cacheKey($environmentId));
    }

    /**
     * @return array{plan_type:string, status:string, limits:array, features:array}
     */
    private static function resolve(Environment $environment): array
    {
        $licence = EnvironmentLicence::where('environment_id', $environment->id)->first();

        $planType = $licence?->plan_type ?? EnvironmentLicence::PLAN_FREE;
        $status = $licence?->status ?? EnvironmentLicence::STATUS_FREE_ACTIVE;

        $plan = $licence?->plan_id
            ? Plan::find($licence->plan_id)
            : Plan::where('type', $planType)->first();

        // Fall back to the catalogue Free Forever plan if the licence's plan
        // row is unavailable for any reason.
        if (! $plan) {
            $plan = Plan::where('type', EnvironmentLicence::PLAN_FREE)->first();
        }

        return [
            'plan_type' => $planType,
            'status' => $status,
            'limits' => $plan?->limits ?? [],
            'features' => $plan?->features ?? [],
        ];
    }

    public function planType(): string
    {
        return $this->resolved['plan_type'];
    }

    public function status(): string
    {
        return $this->resolved['status'];
    }

    /**
     * @return array<string, mixed>
     */
    public function limits(): array
    {
        return $this->resolved['limits'];
    }

    /**
     * @return array<string, mixed>
     */
    public function features(): array
    {
        return $this->resolved['features'];
    }

    /**
     * A numeric limit (null = unlimited/absent).
     */
    public function limit(string $key): ?int
    {
        $value = $this->resolved['limits'][$key] ?? null;

        return $value === null ? null : (int) $value;
    }

    public function hasFeature(string $key): bool
    {
        return (bool) ($this->resolved['features'][$key] ?? false);
    }

    public function featureLevel(string $key): ?string
    {
        $value = $this->resolved['features'][$key] ?? null;

        return is_string($value) ? $value : null;
    }
}
