<?php

namespace App\Support\Marketing;

/**
 * Renders outbound marketing/retention messages in every configured language
 * and joins them, so a single WhatsApp message can be bilingual (FR + EN) for a
 * mixed-language audience. Configured via `services.retention.locales`.
 */
class Multilingual
{
    /** @return array<int, string> */
    public static function locales(): array
    {
        $raw = config('services.retention.locales');

        $locales = is_array($raw)
            ? $raw
            : array_map('trim', explode(',', (string) ($raw ?: config('services.retention.locale') ?: config('app.locale', 'fr'))));

        $locales = array_values(array_filter($locales));

        return $locales !== [] ? $locales : ['fr'];
    }

    /**
     * Render once per configured locale (via the callback) and join the
     * non-empty parts with a blank line.
     *
     * @param  callable(string): string  $perLocale
     */
    public static function render(callable $perLocale): string
    {
        $parts = array_filter(array_map(
            fn (string $locale): string => trim((string) $perLocale($locale)),
            self::locales(),
        ));

        return implode("\n\n", $parts);
    }
}
