<?php

namespace App\Http\Middleware;

use App\Models\Environment;
use App\Models\EnvironmentLicence;
use App\Services\Licensing\EntitlementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Environment-scoped numeric-limit gate (KURSA plan Phase 9).
 *
 *   licence.limit:published_courses -> block when adding one more would exceed
 *                                      the plan's limits[published_courses].
 *
 * A null limit means unlimited (passes through). Current usage comes from
 * EntitlementService::usage(), which counts CURRENT state only — pre-existing
 * over-limit data (e.g. a migrated env with 5 published courses on a Free
 * plan) is always readable; only the NEW action is blocked (doc §5 / §12).
 *
 * Behaviour mirrors CheckPlanFeature: dark-deploy pass-through, fail-open with
 * no tenant context, and grace / past_due blocks new writes.
 *
 * Denied response (403):
 *   { error: 'plan_limit_reached', limit, current, plan, upgrade_url: '/billing' }
 */
class CheckPlanLimit
{
    public function handle(Request $request, Closure $next, string $limitKey): Response
    {
        if (! config('licensing.enforcement_enabled')) {
            return $next($request);
        }

        $environment = $request->get('environment');
        if (! $environment instanceof Environment) {
            return $next($request);
        }

        $entitlement = EntitlementService::for($environment);

        // Grace / past_due: no new over-provisioning during a lapsing licence
        // (doc §12). All limit routes are writes.
        if ($this->isWrite($request) && $this->inGrace($entitlement)) {
            return response()->json([
                'error' => 'licence_in_grace',
                'plan' => $entitlement->planType(),
                'upgrade_url' => '/billing',
            ], Response::HTTP_FORBIDDEN);
        }

        $limit = $entitlement->limit($limitKey);
        if ($limit === null) {
            return $next($request); // unlimited
        }

        $current = $entitlement->usage($limitKey);

        // Adding one more must not exceed the limit.
        if ($current + 1 > $limit) {
            return response()->json([
                'error' => 'plan_limit_reached',
                'limit' => $limit,
                'current' => $current,
                'plan' => $entitlement->planType(),
                'upgrade_url' => '/billing',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
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
