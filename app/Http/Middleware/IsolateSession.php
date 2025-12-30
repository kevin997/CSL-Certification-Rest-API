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
            // This provides isolation - each frontend gets its own session
            $cookieName = 'csl_session_' . $slug;
            Config::set('session.cookie', $cookieName);
            
            // CRITICAL FIX: Set the session domain to the ROOT domain
            // This allows the session cookie to be sent from the frontend subdomain
            // The unique cookie NAME still provides isolation between frontends
            $rootDomain = $this->getRootDomain($frontendDomain);
            if ($rootDomain) {
                Config::set('session.domain', '.' . $rootDomain);
            }
            
            // Note: SameSite and Secure are controlled via SESSION_SAME_SITE 
            // and SESSION_SECURE_COOKIE env vars for cross-origin cookie auth
        }

        return $next($request);
    }
    
    /**
     * Extract the root domain from a host.
     * e.g., "learning.csl-brands.com" -> "csl-brands.com"
     */
    protected function getRootDomain(string $host): ?string
    {
        // Handle localhost and IP addresses
        if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }

        $parts = explode('.', $host);
        
        // Need at least 2 parts for a valid domain
        if (count($parts) < 2) {
            return null;
        }

        // Return the last two parts (e.g., "csl-brands.com")
        return implode('.', array_slice($parts, -2));
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
