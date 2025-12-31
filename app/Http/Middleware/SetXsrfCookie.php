<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Config;

/**
 * Ensure an XSRF-TOKEN cookie is always set with proper domain and attributes.
 * This middleware runs after the session is started and the CSRF token is generated.
 * It creates the cookie if missing, or updates its domain/SameSite/Secure flags.
 */
class SetXsrfCookie
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only act on HTTP responses (skip console, etc.)
        if (php_sapi_name() === 'cli') {
            return $response;
        }

        // Retrieve the token from the session (Laravel stores it under "_token")
        $token = $request->session()->token();
        if (!$token) {
            // No CSRF token available – nothing to set
            return $response;
        }

        // Determine frontend root domain for cookie sharing
        $frontendDomain = $this->detectFrontendDomain($request);
        $rootDomain = $frontendDomain ? $this->getRootDomain($frontendDomain) : null;

        $cookieDomain = $rootDomain ? '.' . $rootDomain : null;
        $sameSite = Config::get('session.same_site', 'none');
        $secure = Config::get('session.secure', true);

        // Set the XSRF-TOKEN cookie (readable by JS)
        $response->headers->setCookie(new Cookie(
            'XSRF-TOKEN',
            $token,
            0, // session cookie (no explicit expiry)
            '/',
            $cookieDomain,
            $secure,
            false, // not HttpOnly – must be readable by JS
            false, // raw
            $sameSite,
            false // partitioned
        ));

        return $response;
    }

    /**
     * Detect the frontend domain from request headers.
     */
    protected function detectFrontendDomain(Request $request): ?string
    {
        if ($header = $request->header('X-Frontend-Domain')) {
            return $this->cleanHost($header);
        }
        if ($origin = $request->header('Origin')) {
            return $this->parseHost($origin);
        }
        if ($referer = $request->header('Referer')) {
            return $this->parseHost($referer);
        }
        return null;
    }

    protected function parseHost(string $url): ?string
    {
        $parsed = parse_url($url);
        return $parsed['host'] ?? null;
    }

    protected function cleanHost(string $host): string
    {
        return explode(':', $host)[0];
    }

    /**
     * Extract the root domain (e.g., learning.csl-brands.com => csl-brands.com).
     */
    protected function getRootDomain(string $host): ?string
    {
        if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
            return null;
        }
        $parts = explode('.', $host);
        if (count($parts) < 2) {
            return null;
        }
        return implode('.', array_slice($parts, -2));
    }
}
?>
