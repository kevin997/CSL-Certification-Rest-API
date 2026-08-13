<?php

namespace App\Helpers;

use App\Models\Branding;
use App\Models\Environment;

class EmailBrandingHelper
{
    /**
     * KURSA default branding values.
     */
    private const DEFAULTS = [
        'company_name' => 'KURSA',
        'primary_color' => '#19682f',
        'secondary_color' => '#f59c00',
        'accent_color' => '#ffb733',
        'font_family' => "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
        'logo_url' => null,
    ];

    /**
     * Resolve branding for an environment, falling back to KURSA defaults.
     *
     * @return array{company_name: string, primary_color: string, secondary_color: string, accent_color: string, font_family: string, logo_url: string, login_url: string}
     */
    public static function resolve(Environment $environment): array
    {
        $branding = Branding::currentForEnvironment($environment->id);

        if ($branding && $branding->logo_path) {
            $logoUrl = preg_match('#^https?://#i', $branding->logo_path)
                ? $branding->logo_path
                : url('storage/'.ltrim($branding->logo_path, '/'));
        } elseif ($environment->logo_url) {
            $logoUrl = $environment->logo_url;
        } else {
            $logoUrl = asset('images/logo-kursa.svg');
        }

        $domain = preg_replace('#^https?://#i', '', trim($environment->primary_domain));

        return [
            'company_name' => $branding?->company_name ?: ($environment->name ?: self::DEFAULTS['company_name']),
            'primary_color' => $branding?->primary_color ?? self::DEFAULTS['primary_color'],
            'secondary_color' => $branding?->secondary_color ?? self::DEFAULTS['secondary_color'],
            'accent_color' => $branding?->accent_color ?? self::DEFAULTS['accent_color'],
            'font_family' => $branding?->font_family ?? self::DEFAULTS['font_family'],
            'logo_url' => $logoUrl,
            'login_url' => 'https://'.rtrim($domain, '/').'/auth/login',
        ];
    }
}
