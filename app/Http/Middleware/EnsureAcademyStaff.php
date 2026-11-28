<?php

namespace App\Http\Middleware;

use App\Support\Tenancy\EnvironmentContext;
use App\Support\Tenancy\EnvironmentResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Instructor data requires staff standing in the resolved academy. */
class EnsureAcademyStaff
{
    public const ACADEMY_ID = 'authorized_staff_academy_id';

    public function handle(Request $request, Closure $next): Response
    {
        $context = $request->attributes->get(EnvironmentResolver::REQUEST_ATTRIBUTE);
        $academyId = $context instanceof EnvironmentContext ? $context->environment?->id : null;

        // The tenancy middleware may run in log-only mode, and platform
        // admins can work without a tenant elsewhere. Neither is a default
        // academy for endpoints that return academy-wide instructor data.
        if ($academyId === null || ! $request->user()?->isStaffIn((int) $academyId)) {
            return response()->json([
                'code' => 'academy_staff_required',
                'message' => 'This page is only available to staff of this academy.',
            ], 403, ['Cache-Control' => 'private, no-store']);
        }

        $request->attributes->set(self::ACADEMY_ID, (int) $academyId);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
