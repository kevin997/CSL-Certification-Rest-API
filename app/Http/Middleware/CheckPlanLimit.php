<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Services\PlanFeatureService;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckPlanLimit
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
     * @param  string  $resource
     * @param  string  $countResolver  Class path to a resolver that returns the current count
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $resource, string $countResolver)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Resolve the current count using the provided resolver
        $resolver = app($countResolver);
        $currentCount = $resolver->getCount($user, $resource);

        if (!$this->planFeatureService->withinLimits($user, $resource, $currentCount)) {
            $limit = $this->planFeatureService->getLimitValue($user, $resource);
            
            return response()->json([
                'success' => false,
                'message' => "You have reached your plan's limit for {$resource}.",
                'limit_info' => [
                    'resource' => $resource,
                    'current_count' => $currentCount,
                    'limit' => $limit,
                    'current_plan' => $this->planFeatureService->getUserPlan($user)?->name ?? 'No active plan',
                    'upgrade_url' => route('api.plans.index')
                ]
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
