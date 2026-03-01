<?php

namespace App\Http\Middleware;

use App\Support\TenantDomainRegistry;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ConfigureTenantCorsAndSanctum
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $allowedHosts = TenantDomainRegistry::getAllowedHosts();

        $origin = $request->header('Origin');
        $originHost = $this->extractHostWithPortFromOrigin($origin);

        if ($origin && $originHost && in_array($originHost, $allowedHosts, true)) {
            config()->set('cors.allowed_origins', [$origin]);
        } else {
            config()->set('cors.allowed_origins', []);
        }

        // Dynamically set Sanctum stateful domains from the tenant registry.
        // We merge with the existing sanctum.stateful config (which includes local and base prod hosts)
        // so every tenant custom domain (primary + additional) and subdomain gets
        // session-cookie auth automatically without needing to maintain a static list.
        $existingStateful = config('sanctum.stateful', []);
        config()->set('sanctum.stateful', array_values(array_unique(array_merge($existingStateful, $allowedHosts))));

        return $next($request);
    }

    protected function extractHostWithPortFromOrigin(?string $origin): ?string
    {
        if (!$origin) {
            return null;
        }

        $parsed = parse_url($origin);
        if (!is_array($parsed)) {
            return null;
        }

        $host = $parsed['host'] ?? null;
        if (!$host) {
            return null;
        }

        $host = strtolower($host);
        $port = $parsed['port'] ?? null;

        return $port ? ($host . ':' . $port) : $host;
    }
}
