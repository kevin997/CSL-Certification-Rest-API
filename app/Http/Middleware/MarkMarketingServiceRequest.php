<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MarkMarketingServiceRequest
{
    public const ATTRIBUTE = 'marketing_service_private';

    public function handle(Request $request, Closure $next): Response
    {
        // Route middleware runs before service authentication and request
        // validation, allowing global response decorators to recognize every
        // outcome from this private endpoint.
        $request->attributes->set(self::ATTRIBUTE, true);

        return $next($request);
    }
}
