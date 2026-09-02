<?php

namespace App\Support\Tenancy;

use App\Models\Environment;

/**
 * Where a link for an environment should point. The tenant's own domain once
 * it is verified live; the shared host, with the environment id carried in the
 * query string, until then. Every outbound link in mail, notifications and
 * redirects is built here so the decision lives in one place.
 */
final class TenantUrl
{
    public static function isLive(Environment $environment): bool
    {
        return $environment->domain_verified_at !== null && filled($environment->primary_domain);
    }

    public static function base(Environment $environment): string
    {
        $host = self::isLive($environment)
            ? self::bareHost((string) $environment->primary_domain)
            : self::bareHost((string) config('tenancy.shared_host', 'app.getkursa.space'));

        return self::scheme($host).'://'.$host;
    }

    /**
     * @param  array<string, mixed>  $query
     */
    public static function to(Environment $environment, string $path = '/', array $query = []): string
    {
        if (! self::isLive($environment) && ! array_key_exists('environment_id', $query)) {
            $query['environment_id'] = $environment->id;
        }

        $path = '/'.ltrim($path, '/');
        $url = self::base($environment).($path === '/' ? '' : $path);

        if ($query === []) {
            return $url === self::base($environment) ? $url.'/' : $url;
        }

        return ($url === self::base($environment) ? $url.'/' : $url).'?'.http_build_query($query);
    }

    public static function scheme(string $host): string
    {
        $bare = preg_replace('/:\d+$/', '', strtolower($host));

        if (in_array($bare, ['localhost', '127.0.0.1'], true) || app()->environment('local')) {
            return 'http';
        }

        return 'https';
    }

    private static function bareHost(string $value): string
    {
        $value = preg_replace('#^https?://#i', '', trim($value));

        return strtolower(rtrim((string) $value, '/'));
    }
}
