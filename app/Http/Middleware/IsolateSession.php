<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to isolate sessions per frontend domain.
 * 
 * This prevents session sharing across different subdomains of the same root domain
 * by setting a unique session cookie name AND scoping the cookie domain to the specific
 * frontend host instead of the root domain.
 * 
 * For CSRF to work correctly, both the session cookie and XSRF-TOKEN must be
 * scoped to the same domain to avoid token/session mismatches.
 */
class IsolateSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $frontendDomain = $this->detectDomain($request);
        
        if ($frontendDomain) {
            $slug = Str::slug($frontendDomain, '_');
            
            // Set a unique session cookie name per frontend domain
            $cookieName = 'csl_session_' . $slug;
            Config::set('session.cookie', $cookieName);
            
            // CRITICAL: Set the session domain to null or the specific frontend domain
            // This prevents the cookie from being shared with other subdomains.
            // Setting to null means the cookie will be scoped to the exact host.
            Config::set('session.domain', null);
            
            // Also ensure SameSite is set appropriately for cross-origin requests
            // 'lax' works for most cases, 'none' is needed for third-party contexts
            // but requires secure cookies (HTTPS)
            if (Config::get('session.secure')) {
                Config::set('session.same_site', 'none');
            }
        }

        return $next($request);
    }

    /**
     * Detect the frontend domain from headers.
     */
    protected function detectDomain(Request $request): ?string
    {
        // Explicit header from our frontend (most reliable)
        if ($header = $request->header('X-Frontend-Domain')) {
            return $this->cleanHost($header);
        }

        // Origin header (CORS requests)
        if ($origin = $request->header('Origin')) {
            return $this->parseHost($origin);
        }

        // Referer header (fallback)
        if ($referer = $request->header('Referer')) {
            return $this->parseHost($referer);
        }

        return null; // Fallback to default behavior if no frontend context detected
    }

    protected function parseHost(string $url): ?string
    {
        $parsed = parse_url($url);
        return $parsed['host'] ?? null;
    }

    protected function cleanHost(string $host): string
    {
        // Remove port if present
        return explode(':', $host)[0];
    }
}
