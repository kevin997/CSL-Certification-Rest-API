<?php

namespace App\Http\Middleware;

use App\Models\Environment;
use App\Models\EnvironmentLicence;
use App\Services\Licensing\EntitlementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Environment-scoped feature gate (KURSA plan Phase 9).
 *
 *   licence.feature:sales_forms            -> boolean feature must be enabled
 *   licence.feature:api_webhooks,limited   -> feature level must be >= "limited"
 *
 * Behaviour:
 *   - When licensing.enforcement_enabled is false the middleware passes through
 *     untouched (dark deploy).
 *   - The environment is resolved exactly like LicenceController does, from the
 *     request attribute set by DetectEnvironment ($request->get('environment')).
 *     If no environment is in context (public / non-tenant call) the gate
 *     fails OPEN — it never blocks a request it cannot scope.
 *   - During grace / past_due, premium configuration is read-only (doc §12):
 *     WRITE methods on a premium-feature route return `licence_in_grace`, reads
 *     pass. Billing / licence routes are simply never given this middleware.
 *
 * Denied (feature unavailable) response (403):
 *   { error: 'plan_feature_unavailable', feature, plan, upgrade_url: '/billing' }
 */
class CheckPlanFeature
{
    public function handle(Request $request, Closure $next, string $feature, ?string $minLevel = null): Response
    {
        if (! config('licensing.enforcement_enabled')) {
            return $next($request);
        }

        $environment = $request->get('environment');
        if (! $environment instanceof Environment) {
            // No tenant context to scope against — fail open.
            return $next($request);
        }

        $entitlement = EntitlementService::for($environment);

        // Grace / past_due: premium config is read-only (doc §12).
        if ($this->isWrite($request) && $this->inGrace($entitlement)) {
            return response()->json([
                'error' => 'licence_in_grace',
                'plan' => $entitlement->planType(),
                'upgrade_url' => '/billing',
            ], Response::HTTP_FORBIDDEN);
        }

        if (! $this->featureAllowed($entitlement, $feature, $minLevel)) {
            return response()->json([
                'error' => 'plan_feature_unavailable',
                'feature' => $feature,
                'plan' => $entitlement->planType(),
                'upgrade_url' => '/billing',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    private function featureAllowed(EntitlementService $entitlement, string $feature, ?string $minLevel): bool
    {
        if ($minLevel !== null && $minLevel !== '') {
            $ladder = $this->ladderFor($minLevel);

            if ($ladder === null) {
                // Unknown ladder — require an exact match.
                return $entitlement->featureLevel($feature) === $minLevel;
            }

            $current = $entitlement->featureLevel($feature);
            $currentIdx = array_search($current, $ladder, true);
            $requiredIdx = array_search($minLevel, $ladder, true);

            if ($currentIdx === false) {
                return false; // absent / not on this ladder ranks below every rung
            }

            return $currentIdx >= $requiredIdx;
        }

        // Boolean feature: enabled unless false / null / the "none" sentinel.
        $value = $entitlement->features()[$feature] ?? false;

        return $value !== false && $value !== null && $value !== 'none';
    }

    /**
     * First configured ladder that contains the required minlevel.
     *
     * @return array<int, string>|null
     */
    private function ladderFor(string $minLevel): ?array
    {
        foreach ((array) config('licensing.level_ladders', []) as $ladder) {
            if (in_array($minLevel, $ladder, true)) {
                return $ladder;
            }
        }

        return null;
    }

    private function inGrace(EntitlementService $entitlement): bool
    {
        return in_array($entitlement->status(), [
            EnvironmentLicence::STATUS_GRACE,
            EnvironmentLicence::STATUS_PAST_DUE,
        ], true);
    }

    private function isWrite(Request $request): bool
    {
        return in_array($request->getMethod(), ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }
}
