<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to fix XSRF-TOKEN cookie domain for cross-subdomain requests.
 * 
 * When IsolateSession sets session.domain to null for session isolation,
 * the XSRF-TOKEN also gets scoped to the API domain, making it unreadable
 * by JavaScript on the frontend domain (different subdomain).
 * 
 * This middleware overrides the XSRF-TOKEN cookie to use the shared root domain,
 * allowing the frontend to read it while keeping sessions isolated.
 */
class FixXsrfCookieDomain
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Check if the response has an XSRF-TOKEN cookie that needs fixing
        $cookies = $response->headers->getCookies();
        $frontendDomain = $this->detectFrontendDomain($request);
        
        if (!$frontendDomain) {
            return $response;
        }

        // Calculate the root domain for cookie sharing
        $rootDomain = $this->getRootDomain($frontendDomain);
        
        if (!$rootDomain) {
            return $response;
        }

        foreach ($cookies as $cookie) {
            if ($cookie->getName() === 'XSRF-TOKEN') {
                // Remove the existing XSRF-TOKEN cookie
                $response->headers->removeCookie(
                    $cookie->getName(),
                    $cookie->getPath(),
                    $cookie->getDomain()
                );

                // Add a new one with the root domain
                $response->headers->setCookie(new Cookie(
                    'XSRF-TOKEN',
                    $cookie->getValue(),
                    $cookie->getExpiresTime(),
                    '/',
                    '.' . $rootDomain, // Shared across subdomains
                    $cookie->isSecure(),
                    false, // Must be readable by JS
                    false, // Raw
                    Config::get('session.same_site', 'lax'),
                    $cookie->isPartitioned()
                ));

                break;
            }
        }

        return $response;
    }

    /**
     * Detect the frontend domain from request headers.
     */
    protected function detectFrontendDomain(Request $request): ?string
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
     * Extract the root domain for cookie sharing.
     * e.g., "learning.csl-brands.com" -> "csl-brands.com"
     */
    protected function getRootDomain(string $host): ?string
    {
        // Handle localhost and IP addresses
        if ($host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP)) {
            return null; // Don't set domain for localhost/IP
        }

        $parts = explode('.', $host);
        
        // Need at least 2 parts for a valid domain
        if (count($parts) < 2) {
            return null;
        }

        // Return the last two parts (e.g., "csl-brands.com")
        return implode('.', array_slice($parts, -2));
    }
}
