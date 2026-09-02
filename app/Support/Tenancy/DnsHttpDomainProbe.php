<?php

namespace App\Support\Tenancy;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A tenant domain counts as live once it resolves in DNS and something answers
 * HTTPS on it. Both halves matter: a record can exist before the host is
 * attached to the frontend project, and links must not switch until it serves.
 */
final class DnsHttpDomainProbe implements DomainProbe
{
    public function isLive(string $host): bool
    {
        $host = strtolower(trim($host));

        if ($host === '') {
            return false;
        }

        $bare = preg_replace('/:\d+$/', '', $host);

        // Local hosts are never "live" in the sense links depend on.
        if (in_array($bare, ['localhost', '127.0.0.1'], true)) {
            return false;
        }

        $records = @dns_get_record($bare, DNS_A | DNS_AAAA | DNS_CNAME);

        if (! is_array($records) || $records === []) {
            return false;
        }

        try {
            $response = Http::timeout((int) config('tenancy.domain_probe.http_timeout_seconds', 5))
                ->withoutRedirecting()
                ->get('https://'.$host.'/');

            // "Something answers" is not enough. A customer's existing site, a
            // parking page or a registrar redirect all answer, and stamping the
            // flag on one of those moves every link for that tenant to the wrong
            // place permanently. Require a 2xx that carries the frontend's own
            // marker, so the domain is only live once it serves KURSA.
            if (! $response->successful()) {
                return false;
            }

            return $this->looksLikeTheFrontend($response->header('X-Kursa-App'), (string) $response->body());
        } catch (Throwable $e) {
            Log::info('tenancy.domain_probe_failed', ['host' => $host, 'reason' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * The frontend is recognised by a response header it sets, falling back to
     * the marker its HTML carries. Both are cheap and neither matches a parking
     * page. A tenant domain stays pending until one of them appears.
     */
    private function looksLikeTheFrontend(?string $header, string $body): bool
    {
        if (filled($header)) {
            return true;
        }

        foreach ((array) config('tenancy.domain_probe.body_markers', []) as $marker) {
            if ($marker !== '' && str_contains($body, (string) $marker)) {
                return true;
            }
        }

        return false;
    }
}
