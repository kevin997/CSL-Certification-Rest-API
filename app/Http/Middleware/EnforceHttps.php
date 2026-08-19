<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

class EnforceHttps
{
    /**
     * Callers that never reach this app through nginx, and so never carry a
     * forwarded scheme.
     *
     * csl-marketplace-api talks to MAIN_API_URL=http://csl-certification-rest-api
     * over the compose network. Refusing it would break that integration
     * without making anything more private: the traffic never leaves the host.
     *
     * @var list<string>
     */
    private const PRIVATE_RANGES = [
        '127.0.0.0/8',
        '::1',
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
    ];

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($this->shouldSkipHttpsEnforcement($request)) {
            return $next($request);
        }

        if (! $request->secure()) {
            Log::warning('[EnforceHttps] Insecure HTTP request blocked', [
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json([
                'status' => 'error',
                'message' => 'HTTPS is required for this request',
                'error' => 'INSECURE_CONNECTION',
                'hint' => 'Please use HTTPS protocol to access this resource',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }

    /**
     * Whether this request is exempt from the HTTPS requirement.
     */
    protected function shouldSkipHttpsEnforcement(Request $request): bool
    {
        if (in_array(config('app.env'), ['local', 'testing'], true)) {
            return true;
        }

        // Enforcement is an explicit choice. It used to be inferred from
        // APP_URL starting with http://localhost, so production -- where
        // APP_URL was exactly that -- ran with the control silently off, and
        // correcting APP_URL would have switched it on as a side effect.
        if (! config('app.enforce_https')) {
            return true;
        }

        return IpUtils::checkIp((string) $request->ip(), self::PRIVATE_RANGES);
    }
}
