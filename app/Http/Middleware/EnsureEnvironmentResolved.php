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

        if (config('tenancy.environment_guard') !== 'enforce') {
            Log::warning('tenancy.environment_required', [
                'method' => $request->method(),
                'route' => $request->path(),
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
