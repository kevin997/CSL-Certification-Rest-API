<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class EnforceHttps
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip HTTPS enforcement in local/testing environments
        if ($this->shouldSkipHttpsEnforcement()) {
            return $next($request);
        }

        // Check if request is not secure (not HTTPS)
        if (!$request->secure()) {
            // Log the insecure request attempt
            Log::warning('[EnforceHttps] Insecure HTTP request blocked', [
                'url' => $request->fullUrl(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Return 403 Forbidden for non-HTTPS requests in production
            return response()->json([
                'status' => 'error',
                'message' => 'HTTPS is required for this request',
                'error' => 'INSECURE_CONNECTION',
                'hint' => 'Please use HTTPS protocol to access this resource'
            ], 403);
        }

        return $next($request);
    }

    /**
     * Determine if HTTPS enforcement should be skipped
     *
     * @return bool
     */
    protected function shouldSkipHttpsEnforcement(): bool
    {
        $appEnv = config('app.env');
        $appUrl = config('app.url');

        // Skip in local and testing environments
        if (in_array($appEnv, ['local', 'testing'])) {
            return true;
        }

        // Skip if APP_URL is localhost (additional safety check)
        if (str_starts_with($appUrl, 'http://localhost') || 
            str_starts_with($appUrl, 'http://127.0.0.1')) {
            return true;
        }

        return false;
    }
}
