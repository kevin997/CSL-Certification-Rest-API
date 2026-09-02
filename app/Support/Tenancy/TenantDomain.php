<?php

namespace App\Support\Tenancy;

use RuntimeException;

/**
 * Composition and validation of tenant hostnames. A "subdomain" tenant is a
 * label under the configured base; earlier tenants live under the legacy
 * bases and are still recognised as KURSA subdomains.
 */
final class TenantDomain
{
    public const RESERVED_LABELS = [
        'app', 'www', 'api', 'idp', 'manager', 'admin', 'mail',
        'marketplace', 'ads', 'marketing', 'sales',
    ];

    private const LABEL_PATTERN = '/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])?$/';

    public static function base(): string
    {
        return strtolower(trim((string) config('tenancy.subdomain_base', 'getkursa.space')));
    }

    /**
     * @return array<int, string> current base first, then legacy bases
     */
    public static function knownBases(): array
    {
        $legacy = array_map(
            fn ($base) => strtolower(trim((string) $base)),
            (array) config('tenancy.legacy_subdomain_bases', [])
        );

        return array_values(array_unique(array_filter([self::base(), ...$legacy])));
    }

    /**
     * @throws RuntimeException when a subdomain label is invalid or reserved
     */
    public static function compose(string $type, string $value): string
    {
        $value = strtolower(trim(preg_replace('#^https?://#i', '', trim($value)) ?? ''));
        $value = rtrim($value, '/');

        if ($type !== 'subdomain') {
            return $value;
        }

        $label = self::labelOf($value) ?? $value;

        if (! self::isValidLabel($label)) {
            throw new RuntimeException('Invalid subdomain: use 3 to 63 letters, numbers or hyphens, not starting or ending with a hyphen.');
        }

        if (self::isReservedLabel($label)) {
            throw new RuntimeException('This subdomain is reserved.');
        }

        return $label.'.'.self::base();
    }

    /**
     * The label when the host sits directly under one of our bases, else null.
     */
    public static function labelOf(string $host): ?string
    {
        $host = strtolower(trim($host));

        foreach (self::knownBases() as $base) {
            $suffix = '.'.$base;

            if (str_ends_with($host, $suffix)) {
                $label = substr($host, 0, -strlen($suffix));

                return $label === '' ? null : $label;
            }
        }

        return null;
    }

    public static function isValidLabel(string $label): bool
    {
        return preg_match(self::LABEL_PATTERN, $label) === 1 && strlen($label) >= 3;
    }

    public static function isReservedLabel(string $label): bool
    {
        return in_array(strtolower($label), self::RESERVED_LABELS, true);
    }

    public static function isKursaSubdomain(string $host): bool
    {
        return self::labelOf($host) !== null;
    }
}
