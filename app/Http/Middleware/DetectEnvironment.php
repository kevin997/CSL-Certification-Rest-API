<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\EnvironmentResolver;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class DetectEnvironment
{
    public function __construct(private readonly EnvironmentResolver $resolver) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $context = $this->resolver->resolve($request);
        $request->attributes->set(EnvironmentResolver::REQUEST_ATTRIBUTE, $context);

        $environment = $context->environment;
        $domain = $context->host;
        $frontendDomainHeader = $request->header('X-Frontend-Domain');

        if (! $environment) {
            Log::debug('DetectEnvironment: No environment resolved for host', [
                'detected_domain' => $domain,
                'frontend_header' => $frontendDomainHeader,
            ]);
            // Do NOT fall back to an arbitrary environment — that silently leaks another
            // tenant's data. Routes that require a resolved environment carry the
            // `environment.required` middleware (EnsureEnvironmentResolved).
        }

        if ($environment) {
            // Share the environment with all views
            view()->share('environment', $environment);

            // Add environment to the request for easy access in controllers
            $request->merge(['environment' => $environment]);

            // Store environment in session for persistence
            session(['current_environment_id' => $environment->id]);

            // If user is authenticated, associate them with this environment if not already
            if ($request->user()) {
                // Check if the user is already associated with this environment
                $existingAssociation = $request->user()->environments()
                    ->where('environment_id', $environment->id)
                    ->exists();

                // If not, create the association
                if (! $existingAssociation) {
                    $request->user()->environments()->attach($environment->id, [
                        'joined_at' => now(),
                    ]);
                    // Log::info('DetectEnvironment: Associated user with environment', [
                    //     'user_id' => $request->user()->id,
                    //     'environment_id' => $environment->id
                    // ]);
                }

                // KURSA Phase 9: throttled active-learner heartbeat. Updates the
                // pivot's last_active_at at most once/hour per user+env so
                // EntitlementService::activeLearnersCount() can measure learners
                // who accessed the academy during the current window (doc §4.4).
                $this->touchLastActive((int) $environment->id, (int) $request->user()->id);

                // Store the environment credentials context for the auth provider
                $environmentCredentials = $request->user()->getEnvironmentCredentials($environment->id);
                if ($environmentCredentials) {
                    session([
                        'environment_credentials' => [
                            'environment_id' => $environment->id,
                            'email' => $environmentCredentials->email,
                        ],
                    ]);
                }
            }
        }

        // Process the request
        $response = $next($request);

        // Add environment information to API responses
        if ($response instanceof JsonResponse) {
            $data = $response->getData(true);

            if (! isset($data['environment'])) {
                $environmentData = $environment ? [
                    'id' => $environment->id,
                    'is_demo' => $environment->is_demo,
                    'name' => $environment->name,
                    'primary_domain' => $environment->primary_domain,
                    'domain_verified_at' => $environment->domain_verified_at?->toIso8601String(),
                    'source' => $context->source,
                    'detected_domain' => $domain,
                    'header_domain' => $frontendDomainHeader,
                ] : [
                    'message' => 'No environment found',
                    'source' => $context->source,
                    'detected_domain' => $domain,
                    'header_domain' => $frontendDomainHeader,
                ];

                if (is_array($data)) {
                    $data['environment'] = $environmentData;
                    $response->setData($data);
                }
            }
        }

        return $response;
    }

    /**
     * Throttled heartbeat for the active-learner metric (KURSA Phase 9).
     * At most one write per user+environment per throttle window. Guarded by a
     * cache lock so hot request paths don't hammer the pivot.
     */
    private function touchLastActive(int $environmentId, int $userId): void
    {
        $ttl = (int) config('licensing.last_active_throttle_seconds', 3600);
        $lockKey = "last_active:{$environmentId}:{$userId}";

        // Cache::add is atomic — returns true only for the first caller in the
        // window, so exactly one heartbeat write happens per window.
        if (! Cache::add($lockKey, true, $ttl)) {
            return;
        }

        try {
            if (! Schema::hasColumn('environment_user', 'last_active_at')) {
                return;
            }

            DB::table('environment_user')
                ->where('environment_id', $environmentId)
                ->where('user_id', $userId)
                ->update(['last_active_at' => now()]);
        } catch (\Throwable $e) {
            // Never let a metrics write break the request pipeline.
            Cache::forget($lockKey);
        }
    }
}
