<?php

namespace App\Support;

use App\Models\Environment;
use Illuminate\Support\Facades\Cache;

class TenantDomainRegistry
{
    protected const CACHE_KEY = 'tenant_domains:all_hosts_v2';

    /**
     * @return array<int, string> hosts (no scheme), may include ports
     */
    public static function getAllowedHosts(): array
    {
        return Cache::remember(self::CACHE_KEY, 300, function () {
            $hosts = [];

            $environments = Environment::query()
                ->where('is_active', true)
                ->get(['primary_domain', 'additional_domains']);

            foreach ($environments as $environment) {
                foreach ((array) $environment->getAllDomains() as $domain) {
                    $normalized = self::normalizeHost($domain);
                    if ($normalized !== null) {
                        $hosts[] = $normalized;
                    }
                }
            }

            foreach (self::getDevHosts() as $devHost) {
                $hosts[] = $devHost;
            }

            $hosts = array_values(array_unique(array_filter($hosts)));
            sort($hosts);

            return $hosts;
        });
    }

    /**
     * @return array<int, string>
     */
    protected static function getDevHosts(): array
    {
        return [
            'kursa.csl-brands.com',
            'sales.csl-brands.com',
            'localhost:3000',  // Certification
            'localhost:3001',  // Sales Website
            'localhost:3002',  // Training Website
            'localhost:3003',  // Ambassador App
            'localhost:3004',  // System Admin
            'localhost:3005',  // Brands IDP
            'localhost:5173',  // Marketplace Web
            '127.0.0.1:3000',
            '127.0.0.1:3001',
            '127.0.0.1:3002',
            '127.0.0.1:3003',
            '127.0.0.1:3004',
            '127.0.0.1:3005',
            '127.0.0.1:5173',
        ];
    }

    protected static function normalizeHost(?string $value): ?string
    {
        if (!$value) {
            return null;
        }

        $value = trim(strtolower($value));

        // If a full URL is stored, extract host[:port]
        if (str_starts_with($value, 'http://') || str_starts_with($value, 'https://')) {
            $parsed = parse_url($value);
            $host = $parsed['host'] ?? null;
            if (!$host) {
                return null;
            }

            $port = $parsed['port'] ?? null;

            return $port ? ($host . ':' . $port) : $host;
        }

        // Assume already host[:port]
        return $value;
    }
}
