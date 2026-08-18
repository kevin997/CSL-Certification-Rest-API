<?php

namespace App\Services\Payments;

use App\Models\PaymentGatewaySetting;
use App\Services\EnvironmentPaymentConfigService;
use Illuminate\Support\Collection;

/**
 * The single place a payment gateway is resolved for an environment.
 *
 * Two decisions used to be made independently at ~15 call sites, and most got
 * at least one wrong:
 *
 *  1. WHICH environment owns the gateway. A centralized tenant transacts
 *     through another environment's gateways, so the effective id must be
 *     resolved first.
 *  2. WHETHER to bypass EnvironmentScope. That scope filters on
 *     session('current_environment_id'), which during a storefront request is
 *     the TENANT -- so it hides the very gateway the tenant is meant to use.
 *     A call site that filters correctly on the effective environment but does
 *     not bypass the scope ends up with two mutually exclusive predicates and
 *     resolves nothing.
 *
 * Depending on no session state also makes this correct in a queue job, a
 * console command and a webhook, where the scope is inert and the old code
 * happened to work by accident.
 */
class PaymentGatewayResolver
{
    public function __construct(private EnvironmentPaymentConfigService $paymentConfig) {}

    /** The environment whose gateways this environment actually transacts through. */
    public function effectiveEnvironmentId(int $environmentId): int
    {
        return $this->paymentConfig->getEffectiveEnvironmentId($environmentId);
    }

    /**
     * Resolve a gateway by its primary key, scoped to what `$environmentId` is
     * allowed to use. Returns null when the id belongs to an unrelated
     * environment — callers must treat that as "not available", never as an error.
     */
    public function forId(int|string $id, int $environmentId): ?PaymentGatewaySetting
    {
        return $this->query($environmentId)->whereKey($id)->first();
    }

    public function forCode(string $code, int $environmentId, bool $activeOnly = true): ?PaymentGatewaySetting
    {
        $query = $this->query($environmentId)->where('code', $code);

        if ($activeOnly) {
            $query->where('status', true);
        }

        return $query->orderByDesc('is_default')->first();
    }

    /** @return Collection<int, PaymentGatewaySetting> */
    public function listFor(int $environmentId, bool $activeOnly = true): Collection
    {
        $query = $this->query($environmentId);

        if ($activeOnly) {
            $query->where('status', true);
        }

        return $query->orderBy('sort_order')->get();
    }

    private function query(int $environmentId)
    {
        // withoutGlobalScopes() is the point of this class -- see the note above.
        return PaymentGatewaySetting::withoutGlobalScopes()
            ->where('environment_id', $this->effectiveEnvironmentId($environmentId));
    }
}
