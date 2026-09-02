<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Support\Tenancy\EnvironmentContext;
use App\Support\Tenancy\EnvironmentResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tenant routes must know which environment they act on. Without this guard an
 * unresolved request ran with no environment scope at all.
 *
 * Platform staff (admin, super_admin, sales_agent) work from hosts that are not
 * tenants and legitimately carry no binding.
 */
class EnsureEnvironmentResolved
{
    public const CODE = 'environment_required';

    private const PLATFORM_ROLES = [
        UserRole::ADMIN->value,
        UserRole::SUPER_ADMIN->value,
        UserRole::SALES_AGENT->value,
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $context = $request->attributes->get(EnvironmentResolver::REQUEST_ATTRIBUTE);

        if ($context instanceof EnvironmentContext && $context->resolved()) {
            return $next($request);
        }

        $user = $request->user();
        $role = $user?->role instanceof UserRole ? $user->role->value : $user?->role;

        if (is_string($role) && in_array($role, self::PLATFORM_ROLES, true)) {
            return $next($request);
        }

        // Fail closed: only the exact string 'log' lets an unresolved request
        // through. A typo in TENANCY_ENVIRONMENT_GUARD ('Enforce', 'enforced',
        // a stray space) must not silently reopen every tenant's rows.
        $mode = strtolower(trim((string) config('tenancy.environment_guard', 'log')));

        if ($mode !== 'log' && $mode !== 'enforce') {
            Log::error('tenancy.environment_guard_invalid', [
                'value' => config('tenancy.environment_guard'),
            ]);
        }

        if ($mode === 'log') {
            Log::warning('tenancy.environment_required', [
                'method' => $request->method(),
                // The route pattern, not the path: identifiers stay out of logs.
                'route' => $request->route()?->uri(),
                'host' => $context instanceof EnvironmentContext ? $context->host : null,
                'user_id' => $user?->id,
            ]);

            return $next($request);
        }

        return response()->json([
            'code' => self::CODE,
            'message' => 'No academy selected. Sign in to an academy or open it from its own address.',
        ], 403);
    }
}
