<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class IsolateSession
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $domain = $this->detectDomain($request);
        
        if ($domain) {
            $slug = Str::slug($domain, '_');
            // Check if we are in expected environment context or explicit api call
            // Ideally we stick to one specific cookie name per "Frontend Domain"
            // Default Laravel session cookie is usually 'laravel_session' or similar.
            // We prepend a prefix to avoid collisions.
            
            $cookieName = 'csl_session_' . $slug;
            
            Config::set('session.cookie', $cookieName);
        }

        return $next($request);
    }

    /**
     * Detect the frontend domain from headers.
     */
    protected function detectDomain(Request $request): ?string
    {
        // Explicit header from our frontend
        if ($header = $request->header('X-Frontend-Domain')) {
            return $this->cleanHost($header);
        }

        // Origin header (CORS)
        if ($origin = $request->header('Origin')) {
            return $this->parseHost($origin);
        }

        // Referer header
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
