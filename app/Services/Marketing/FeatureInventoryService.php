<?php

namespace App\Services\Marketing;

use App\Ai\Agents\DocFeatureExtractorAgent;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Builds and caches a compact knowledge base of KURSA's actual features by
 * crawling the platform's own docs with the DocFeatureExtractorAgent, then
 * grounds every generated marketing message against it. Falls back to a
 * hardcoded seed list so the content engine still works if doc extraction
 * fails entirely.
 */
class FeatureInventoryService
{
    private const CACHE_PATH = 'marketing/feature-inventory.json';

    private const CACHE_TTL_DAYS = 7;

    private const MAX_DOC_CHARS = 3000;

    private const MAX_DOC_BYTES = 60 * 1024;

    private const MAX_FEATURES = 60;

    /**
     * @return array{generated_at: string, features: array<int, array{name: string, summary: string, audience: string, doc: string}>}
     */
    public function build(bool $fresh = false): array
    {
        if (! $fresh) {
            $cached = $this->readCache();

            if ($cached !== null) {
                return $cached;
            }
        }

        $features = [];

        foreach ($this->corpus() as $path) {
            try {
                $extracted = $this->extractFromDoc($path);
                foreach ($extracted as $feature) {
                    $features[] = $feature;
                }
            } catch (\Throwable $e) {
                Log::warning("FeatureInventoryService: failed to extract features from {$path}: {$e->getMessage()}");
            }
        }

        foreach ($this->seedFeatures() as $feature) {
            $features[] = $feature;
        }

        $features = $this->dedupe($features);
        $features = array_slice($features, 0, self::MAX_FEATURES);

        $inventory = [
            'generated_at' => now()->toIso8601String(),
            'features' => $features,
        ];

        $this->writeCache($inventory);

        return $inventory;
    }

    /**
     * @return array<int, string> absolute file paths
     */
    private function corpus(): array
    {
        $paths = [];

        $architectureGlob = base_path('docs/architecture/*.md');
        foreach (glob($architectureGlob) ?: [] as $file) {
            $paths[] = $file;
        }

        $docsGlob = base_path('docs/*.md');
        foreach (glob($docsGlob) ?: [] as $file) {
            $paths[] = $file;
        }

        $readme = base_path('README.md');
        if (File::exists($readme)) {
            $paths[] = $readme;
        }

        return array_values(array_filter($paths, function (string $path) {
            if (strtoupper(basename($path)) === 'AGENTS.MD') {
                return false;
            }

            if (! File::exists($path)) {
                return false;
            }

            return File::size($path) <= self::MAX_DOC_BYTES;
        }));
    }

    /**
     * @return array<int, array{name: string, summary: string, audience: string, doc: string}>
     */
    private function extractFromDoc(string $path): array
    {
        $contents = File::get($path);
        $excerpt = mb_substr($contents, 0, self::MAX_DOC_CHARS);

        if (trim($excerpt) === '') {
            return [];
        }

        $result = (new DocFeatureExtractorAgent)->promptWithFailover($excerpt);

        $features = [];

        foreach ((array) ($result['features'] ?? []) as $feature) {
            if (! is_array($feature)) {
                continue;
            }

            $name = trim((string) ($feature['name'] ?? ''));
            $summary = trim((string) ($feature['summary'] ?? ''));

            if ($name === '' || $summary === '') {
                continue;
            }

            $audience = (string) ($feature['audience'] ?? 'both');
            if (! in_array($audience, ['instructor', 'learner', 'both'], true)) {
                $audience = 'both';
            }

            $features[] = [
                'name' => $name,
                'summary' => $summary,
                'audience' => $audience,
                'doc' => basename($path),
            ];
        }

        return $features;
    }

    /**
     * Hardcoded seed features so the engine always has grounding material,
     * even if every doc extraction call fails.
     *
     * @return array<int, array{name: string, summary: string, audience: string, doc: string}>
     */
    private function seedFeatures(): array
    {
        $seeds = [
            ['name' => 'Course builder', 'summary' => 'Build structured courses with lessons, quizzes, assignments, and live webinars.', 'audience' => 'instructor'],
            ['name' => 'Certificates with verification', 'summary' => 'Issue certificates learners can share, each with a public verification link.', 'audience' => 'both'],
            ['name' => 'Branded white-label academy', 'summary' => 'Run your academy on your own custom domain with your own branding.', 'audience' => 'instructor'],
            ['name' => 'Storefront & product bundles', 'summary' => 'Sell courses individually or bundled together in a branded storefront.', 'audience' => 'instructor'],
            ['name' => 'Sales forms & funnels', 'summary' => 'Build sales pages and funnels that convert visitors into paying students.', 'audience' => 'instructor'],
            ['name' => 'Enrollment codes', 'summary' => 'Generate enrollment codes to grant access without a public storefront purchase.', 'audience' => 'instructor'],
            ['name' => 'Referrals & commissions', 'summary' => 'Reward affiliates and partners with tracked referral commissions.', 'audience' => 'instructor'],
            ['name' => 'Live sessions', 'summary' => 'Host live classes and webinars directly inside the academy.', 'audience' => 'both'],
            ['name' => 'Learner analytics & engagement scores', 'summary' => 'See which learners are engaged, at risk, or ready to be upsold.', 'audience' => 'instructor'],
            ['name' => 'Mobile-money payments', 'summary' => 'Accept payments via Monetbil, TaraMoney, Stripe, and other mobile-money rails.', 'audience' => 'instructor'],
            ['name' => 'WhatsApp notifications', 'summary' => 'Keep learners and instructors informed with automated WhatsApp messages.', 'audience' => 'both'],
            ['name' => 'Email branding', 'summary' => 'Send transactional and marketing emails that match your academy branding.', 'audience' => 'instructor'],
            ['name' => 'Subscriptions', 'summary' => 'Offer recurring subscription access to courses and content.', 'audience' => 'instructor'],
            ['name' => 'Chat & discussions', 'summary' => 'Let learners and instructors discuss course content in built-in chat.', 'audience' => 'both'],
            ['name' => 'Multi-language support', 'summary' => 'Reach French- and English-speaking audiences with multilingual content.', 'audience' => 'both'],
        ];

        return array_map(fn (array $seed) => $seed + ['doc' => 'seed'], $seeds);
    }

    /**
     * @param  array<int, array{name: string, summary: string, audience: string, doc: string}>  $features
     * @return array<int, array{name: string, summary: string, audience: string, doc: string}>
     */
    private function dedupe(array $features): array
    {
        $seen = [];
        $result = [];

        foreach ($features as $feature) {
            $key = mb_strtolower(trim($feature['name']));

            if ($key === '' || isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $result[] = $feature;
        }

        return $result;
    }

    private function readCache(): ?array
    {
        $disk = $this->disk();

        if (! $disk->exists(self::CACHE_PATH)) {
            return null;
        }

        $lastModified = $disk->lastModified(self::CACHE_PATH);

        if ($lastModified === false || $lastModified < now()->subDays(self::CACHE_TTL_DAYS)->timestamp) {
            return null;
        }

        $contents = $disk->get(self::CACHE_PATH);
        $decoded = json_decode((string) $contents, true);

        if (! is_array($decoded) || ! isset($decoded['features'])) {
            return null;
        }

        return $decoded;
    }

    private function writeCache(array $inventory): void
    {
        $this->disk()->put(self::CACHE_PATH, json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    private function disk()
    {
        return Storage::disk('local');
    }
}
