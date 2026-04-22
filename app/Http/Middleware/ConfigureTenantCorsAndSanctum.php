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
        $allowedHosts = $this->getAllowedHosts();

        $origin = $request->header('Origin');
        $originHost = $this->extractHostWithPortFromOrigin($origin);

        if ($origin && $originHost && in_array($originHost, $allowedHosts, true)) {
            config()->set('cors.allowed_origins', [$origin]);
        } else {
            config()->set('cors.allowed_origins', []);
        }

        // Only domains that can actually receive cookies from the current API
        // host should be treated as stateful. Cross-root admin apps still need
        // CORS access, but must authenticate with bearer tokens instead of CSRF.
        $existingStateful = config('sanctum.stateful', []);
        $apiHost = strtolower($request->getHost());

        $statefulHosts = array_values(array_unique(array_filter(
            array_merge($existingStateful, $allowedHosts),
            fn (string $host): bool => $this->canShareCookiesWithApiHost($host, $apiHost),
        )));

        config()->set('sanctum.stateful', $statefulHosts);

        return $next($request);
    }

    /**
     * @return array<int, string>
     */
    protected function getAllowedHosts(): array
    {
        return TenantDomainRegistry::getAllowedHosts();
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

    protected function canShareCookiesWithApiHost(string $frontendHost, string $apiHost): bool
    {
        $normalizedFrontendHost = $this->normalizeHostForComparison($frontendHost);
        $normalizedApiHost = $this->normalizeHostForComparison($apiHost);

        if (!$normalizedFrontendHost || !$normalizedApiHost) {
            return false;
        }

        if ($normalizedFrontendHost === $normalizedApiHost) {
            return true;
        }

        if ($this->isLocalHost($normalizedFrontendHost) || $this->isLocalHost($normalizedApiHost)) {
            return $normalizedFrontendHost === $normalizedApiHost;
        }

        return $this->getRegistrableDomain($normalizedFrontendHost) === $this->getRegistrableDomain($normalizedApiHost);
    }

    protected function normalizeHostForComparison(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('/:\d+$/', '', $host) ?? $host;

        return ltrim($host, '.');
    }

    protected function isLocalHost(string $host): bool
    {
        return $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP) !== false;
    }

    protected function getRegistrableDomain(string $host): ?string
    {
        if ($this->isLocalHost($host)) {
            return $host;
        }

        $host = preg_replace('/^\*\./', '', $host) ?? $host;
        $parts = explode('.', $host);

        if (count($parts) < 2) {
            return null;
        }

        return implode('.', array_slice($parts, -2));
    }
}
