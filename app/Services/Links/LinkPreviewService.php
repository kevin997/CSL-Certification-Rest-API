<?php

namespace App\Services\Links;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Fetches a URL's social-preview metadata for the learner-facing link cards.
 *
 * Instructors and learners paste links into rich text and documentation
 * activities; the app shows a card with the page's title, description and
 * image instead of only a bare URL. Browsers cannot read a third-party page
 * across origins, so the API fetches it and returns only the metadata.
 *
 * The fetcher is deliberately conservative: http(s) only, no userinfo, the
 * host must resolve exclusively to public addresses, redirects are followed
 * manually and re-checked at every hop, and responses are size and time
 * bounded. Results are cached because previews are read on every page view.
 */
class LinkPreviewService
{
    public const MAX_URLS_PER_REQUEST = 8;

    private const SUCCESS_CACHE_TTL_HOURS = 24;

    private const FAILURE_CACHE_TTL_MINUTES = 30;

    private const MAX_REDIRECTS = 3;

    /**
     * Total wall-clock budget for one batch. A page that hangs must not keep
     * the HTTP request open; links past the deadline resolve as unavailable.
     */
    private const BATCH_TIME_BUDGET_SECONDS = 12;

    /**
     * Bodies above this are abandoned outright.
     *
     * Everything below is parsed whole: YouTube, for example, puts its Open
     * Graph tags after more than 800 KB of inline scripts, so truncating at a
     * smaller window silently loses the metadata of the most common links.
     */
    private const HARD_MAX_BYTES = 2097152; // 2 MB

    private const CONNECT_TIMEOUT_SECONDS = 3;

    private const TIMEOUT_SECONDS = 6;

    private const USER_AGENT = 'KursaLinkPreview/1.0 (+https://www.csl-brands.com)';

    /**
     * @param  array<int, string>  $urls
     * @return array<int, array{url: string, preview: array<string, string|null>|null}>
     */
    public function previewMany(array $urls): array
    {
        $results = [];
        $seen = [];
        $deadline = microtime(true) + self::BATCH_TIME_BUDGET_SECONDS;

        foreach ($urls as $rawUrl) {
            $url = $this->normalizeInputUrl((string) $rawUrl);

            if ($url === null) {
                continue;
            }

            if (isset($seen[$url])) {
                continue;
            }

            if (count($seen) >= self::MAX_URLS_PER_REQUEST) {
                break;
            }

            $seen[$url] = true;
            $results[] = [
                'url' => $url,
                'preview' => $this->preview($url, $deadline),
            ];
        }

        return $results;
    }

    /**
     * @return array<string, string|null>|null
     */
    public function preview(string $url, ?float $deadline = null): ?array
    {
        $cacheKey = 'link-preview:v1:'.sha1($url);

        $cached = Cache::get($cacheKey);

        if (is_array($cached)) {
            return $cached['preview'] ?? null;
        }

        // Cached results above are still served; only a network fetch is
        // skipped once the batch budget is spent.
        if ($deadline !== null && microtime(true) >= $deadline) {
            return null;
        }

        $preview = null;

        try {
            $preview = $this->fetchPreview($url);
        } catch (Throwable) {
            $preview = null;
        }

        if ($preview === null) {
            // Remember failures briefly so a broken or hostile host cannot be
            // hit on every render.
            Cache::put(
                $cacheKey,
                ['preview' => null],
                now()->addMinutes(self::FAILURE_CACHE_TTL_MINUTES),
            );

            return null;
        }

        Cache::put(
            $cacheKey,
            ['preview' => $preview],
            now()->addHours(self::SUCCESS_CACHE_TTL_HOURS),
        );

        return $preview;
    }

    /**
     * @return array<string, string|null>|null
     */
    private function fetchPreview(string $url): ?array
    {
        $current = $url;
        $response = null;

        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            $safeUrl = $this->assertPublicUrl($current);

            if ($safeUrl === null) {
                return null;
            }

            try {
                $response = Http::withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'text/html,application/xhtml+xml,image/*;q=0.8,*/*;q=0.5',
                    'Accept-Language' => 'en;q=0.9,fr;q=0.8',
                ])->withOptions([
                    'allow_redirects' => false,
                    'http_errors' => false,
                    'timeout' => self::TIMEOUT_SECONDS,
                    'connect_timeout' => self::CONNECT_TIMEOUT_SECONDS,
                ])->get($safeUrl);
            } catch (ConnectionException) {
                return null;
            }

            $status = $response->status();

            if ($status < 300 || $status >= 400) {
                break;
            }

            $location = trim((string) $response->header('Location'));

            if ($location === '') {
                return null;
            }

            $next = $this->resolveUrl($current, $location);

            if ($next === null) {
                return null;
            }

            $current = $next;
        }

        if ($response === null || $response->status() < 200 || $response->status() >= 300) {
            return null;
        }

        $body = $response->body();

        if ($body === '' || strlen($body) > self::HARD_MAX_BYTES) {
            return null;
        }

        $contentType = strtolower((string) $response->header('Content-Type'));

        if (str_starts_with($contentType, 'image/')) {
            return [
                'resolved_url' => $current,
                'title' => $this->imageTitle($current),
                'description' => null,
                'image' => $current,
                'site_name' => parse_url($current, PHP_URL_HOST) ?: null,
                'favicon' => null,
                'type' => 'image',
            ];
        }

        $looksLikeHtml = str_contains($contentType, 'text/html')
            || str_contains($contentType, 'application/xhtml+xml')
            || ($contentType === '' && preg_match('/^\s*<(?:!doctype|html)/i', $body) === 1);

        if (! $looksLikeHtml) {
            return null;
        }

        return $this->parseHtml($body, $current);
    }

    /**
     * @return array<string, string|null>|null
     */
    private function parseHtml(string $html, string $baseUrl): ?array
    {
        $document = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        // Prefixing the encoding keeps accented titles readable without the
        // deprecated mb_convert_encoding(..., 'HTML-ENTITIES') call.
        $loaded = $document->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (! $loaded) {
            return null;
        }

        $xpath = new \DOMXPath($document);

        $meta = [];
        foreach ($xpath->query('//meta[@content]') ?: [] as $node) {
            /** @var \DOMElement $node */
            $key = $node->getAttribute('property') ?: $node->getAttribute('name');

            if ($key === '') {
                continue;
            }

            $meta[strtolower(trim($key))] ??= trim($node->getAttribute('content'));
        }

        $title = $this->cleanText(
            $meta['og:title']
                ?? $meta['twitter:title']
                ?? $this->firstNodeText($xpath, '//title')
                ?? null,
            200,
        );

        $description = $this->cleanText(
            $meta['og:description']
                ?? $meta['twitter:description']
                ?? $meta['description']
                ?? null,
            500,
        );

        $image = $this->resolveUrl(
            $baseUrl,
            $meta['og:image:secure_url']
                ?? $meta['og:image']
                ?? $meta['twitter:image']
                ?? $meta['twitter:image:src']
                ?? null,
        );

        $siteName = $this->cleanText($meta['og:site_name'] ?? null, 120)
            ?: (parse_url($baseUrl, PHP_URL_HOST) ?: null);

        $favicon = $this->resolveUrl(
            $baseUrl,
            $this->firstNodeAttribute($xpath, '//link[@rel]', 'icon')
                ?? $this->firstNodeAttribute($xpath, '//link[@rel]', 'shortcut icon')
                ?? $this->firstNodeAttribute($xpath, '//link[@rel]', 'apple-touch-icon'),
        );

        $type = $this->cleanText($meta['og:type'] ?? null, 40);

        // A page with no title, no description and no image renders as an
        // empty card, so treat it as unavailable and keep the plain link.
        if ($title === null && $description === null && $image === null) {
            return null;
        }

        return [
            'resolved_url' => $baseUrl,
            'title' => $title,
            'description' => $description,
            'image' => $image,
            'site_name' => $siteName,
            'favicon' => $favicon,
            'type' => $type,
        ];
    }

    private function firstNodeText(\DOMXPath $xpath, string $query): ?string
    {
        $nodes = $xpath->query($query);

        if ($nodes === false || $nodes->length === 0) {
            return null;
        }

        $text = trim((string) $nodes->item(0)?->textContent);

        return $text === '' ? null : $text;
    }

    private function firstNodeAttribute(\DOMXPath $xpath, string $query, string $rel): ?string
    {
        $nodes = $xpath->query($query);

        if ($nodes === false) {
            return null;
        }

        $wanted = strtolower($rel);

        foreach ($nodes as $node) {
            /** @var \DOMElement $node */
            $rels = preg_split('/\s+/', strtolower(trim($node->getAttribute('rel')))) ?: [];

            if (in_array($wanted, $rels, true)) {
                $href = trim($node->getAttribute('href'));

                if ($href !== '') {
                    return $href;
                }
            }
        }

        return null;
    }

    private function cleanText(?string $value, int $maxLength): ?string
    {
        if ($value === null) {
            return null;
        }

        $text = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        if ($text === '') {
            return null;
        }

        return mb_substr($text, 0, $maxLength);
    }

    private function imageTitle(string $url): ?string
    {
        $path = parse_url($url, PHP_URL_PATH) ?: '';
        $name = basename($path);

        return $name !== '' ? $name : null;
    }

    /**
     * Accept only absolute http(s) URLs without credentials.
     */
    private function normalizeInputUrl(string $rawUrl): ?string
    {
        $trimmed = trim($rawUrl);

        if ($trimmed === '' || strlen($trimmed) > 2048) {
            return null;
        }

        $parts = parse_url($trimmed);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        return $trimmed;
    }

    /**
     * Validate a URL and return it when it resolves only to public addresses.
     */
    private function assertPublicUrl(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $host = strtolower(trim($parts['host'], '[]'));

        if ($host === '' || $host === 'localhost' || str_ends_with($host, '.localhost')) {
            return null;
        }

        $addresses = $this->resolveHost($host);

        if ($addresses === []) {
            return null;
        }

        foreach ($addresses as $address) {
            $public = filter_var(
                $address,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            );

            if ($public === false) {
                return null;
            }
        }

        return $url;
    }

    /**
     * @return array<int, string>
     */
    private function resolveHost(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return [$host];
        }

        $addresses = [];

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);

        if (is_array($records)) {
            foreach ($records as $record) {
                $address = $record['ip'] ?? $record['ipv6'] ?? null;

                if (is_string($address) && $address !== '') {
                    $addresses[] = $address;
                }
            }
        }

        if ($addresses === []) {
            $ipv4 = gethostbynamel($host);

            if (is_array($ipv4)) {
                $addresses = $ipv4;
            }
        }

        return array_values(array_unique($addresses));
    }

    /**
     * Resolve a possibly-relative reference against the base URL.
     */
    private function resolveUrl(string $baseUrl, ?string $reference): ?string
    {
        $reference = trim((string) $reference);

        if ($reference === '') {
            return null;
        }

        if (str_starts_with($reference, '//')) {
            $scheme = parse_url($baseUrl, PHP_URL_SCHEME) ?: 'https';

            return $scheme.':'.$reference;
        }

        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $reference) === 1) {
            return in_array(strtolower((string) parse_url($reference, PHP_URL_SCHEME)), ['http', 'https'], true)
                ? $reference
                : null;
        }

        $base = parse_url($baseUrl);

        if ($base === false || ! isset($base['scheme'], $base['host'])) {
            return null;
        }

        if (str_starts_with($reference, '/')) {
            return $this->normalizePath(
                $base['scheme'].'://'.$base['host'].$reference,
            );
        }

        $path = $base['path'] ?? '/';
        $directory = substr($path, 0, (int) strrpos($path, '/') + 1);

        if ($directory === '') {
            $directory = '/';
        }

        return $this->normalizePath(
            $base['scheme'].'://'.$base['host'].$directory.$reference,
        );
    }

    /**
     * Collapse "." and ".." segments so a relative reference resolves to the
     * URL a browser would actually request.
     */
    private function normalizePath(string $url): string
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['path'])) {
            return $url;
        }

        $segments = explode('/', $parts['path']);
        $resolved = [];

        foreach ($segments as $segment) {
            if ($segment === '.' || $segment === '') {
                continue;
            }

            if ($segment === '..') {
                array_pop($resolved);

                continue;
            }

            $resolved[] = $segment;
        }

        $path = '/'.implode('/', $resolved);

        if (str_ends_with($parts['path'], '/') && $path !== '/') {
            $path .= '/';
        }

        $rebuilt = $parts['scheme'].'://'.$parts['host'].$path;

        if (isset($parts['query'])) {
            $rebuilt .= '?'.$parts['query'];
        }

        if (isset($parts['fragment'])) {
            $rebuilt .= '#'.$parts['fragment'];
        }

        return $rebuilt;
    }
}
