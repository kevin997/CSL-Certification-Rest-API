<?php

namespace App\Support\Retention;

/**
 * Resolves the tap-through link appended to each retention message. Because
 * Wachap/email are outbound-only, the message can't be "replied to" in an
 * automated flow — instead every nudge carries a link that lets the recipient
 * complete the action (open their instructor panel, or their course's site).
 *
 * Ported from shopikat's RetentionLinks, adapted to KURSA's two audiences:
 * instructors always land on the platform panel (or a WhatsApp chat with
 * support for the "let's talk visibility" nudge); learners land on the
 * specific environment/site they're enrolled in when known, falling back to
 * the generic marketing site otherwise.
 */
class RetentionLinks
{
    /**
     * @param  array<string, string|int|float|null>  $context
     */
    public function forScenario(string $scenarioKey, array $context = []): string
    {
        $c = (array) config('services.retention.links', []);
        $panel = (string) ($c['panel'] ?? 'https://kursa.cfpcsl.com');
        $site = (string) ($c['site'] ?? 'https://kursa.cfpcsl.com');
        $support = $c['support_whatsapp'] ?? null;

        return match ($scenarioKey) {
            // "Let's talk visibility" → chat a human if a support number is configured.
            'instructor_no_sales' => $support
                ? $this->wa((string) $support, 'Bonjour, je veux en savoir plus sur la visibilité de ma boutique de formation sur KURSA')
                : $this->instructorLink($context, $panel),
            'instructor_trial_expiring', 'instructor_no_course', 'instructor_no_product', 'instructor_inactive' => $this->instructorLink($context, $panel),
            // Learner scenarios: deep-link to the learner's own environment when known.
            default => $this->learnerLink($context, $site),
        };
    }

    /**
     * Instructors land on THEIR OWN academy's dashboard (multi-tenant — each
     * environment has its own domain); the marketing landing page is only a
     * fallback when no domain is on record.
     *
     * @param  array<string, string|int|float|null>  $context
     */
    private function instructorLink(array $context, string $default): string
    {
        $url = $context['environment_url'] ?? null;

        return $url ? $this->withPath((string) $url, '/dashboard') : $default;
    }

    /**
     * Learner nudges point at the specific environment/site the learner is
     * enrolled in (its own domain), falling back to the generic marketing site
     * when the environment has no domain on record.
     *
     * @param  array<string, string|int|float|null>  $context
     */
    private function learnerLink(array $context, string $default): string
    {
        $url = $context['environment_url'] ?? null;

        return $url ? (string) $url : $default;
    }

    /**
     * Insert a path before the query string of a TenantUrl base link, so a
     * shared-host link keeps its environment_id while gaining /dashboard.
     */
    private function withPath(string $url, string $path): string
    {
        $parts = parse_url($url);
        $base = ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? '')
            .(isset($parts['port']) ? ':'.$parts['port'] : '');

        return $base.$path.(isset($parts['query']) ? '?'.$parts['query'] : '');
    }

    private function wa(string $number, string $text): string
    {
        $number = preg_replace('/\D+/', '', $number) ?? '';

        return 'https://wa.me/'.$number.'?text='.rawurlencode($text);
    }
}
