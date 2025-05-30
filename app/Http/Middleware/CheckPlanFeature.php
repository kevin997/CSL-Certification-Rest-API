<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\PlanFeatureService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanFeature
{
    /**
     * @var PlanFeatureService
     */
    protected $planFeatureService;

    /**
     * Create a new middleware instance.
     *
     * @param PlanFeatureService $planFeatureService
     */
    public function __construct(PlanFeatureService $planFeatureService)
    {
        $this->planFeatureService = $planFeatureService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @param  string  $feature
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $feature)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (!$this->planFeatureService->hasFeature($user, $feature)) {
            return response()->json([
                'success' => false,
                'message' => 'Your current plan does not include access to this feature.',
                'upgrade_info' => [
                    'current_plan' => $this->planFeatureService->getUserPlan($user)?->name ?? 'No active plan',
                    'upgrade_url' => route('api.plans.index')
                ]
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
