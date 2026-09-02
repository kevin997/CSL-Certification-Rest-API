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
                ->head('https://'.$host.'/');

            // Anything below 500 means a server is answering for this host; a 404
            // or a redirect still proves the domain is wired up.
            return $response->status() < 500;
        } catch (Throwable $e) {
            Log::info('tenancy.domain_probe_failed', ['host' => $host, 'reason' => $e->getMessage()]);

            return false;
        }
    }
}
