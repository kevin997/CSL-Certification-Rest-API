<?php

namespace App\Console\Commands;

use App\Models\MarketingMessage;
use App\Services\Marketing\FeatureInventoryService;
use App\Services\OllamaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Tops up the marketing content pool (group tips, WhatsApp statuses, email
 * campaigns) so senders always have pending, previously-generated content to
 * ship. Generates one message at a time per channel — small local models do
 * noticeably better one-shot than when asked for a batch.
 */
class GenerateMarketingContentCommand extends Command
{
    protected $signature = 'kursa:generate-marketing-content {--channel=all} {--count=} {--fresh-inventory}';

    protected $description = 'Generate pending marketing content (group tips, statuses, email campaigns) to keep the pool topped up';

    private const TARGET_POOL = 10;

    private const LOOKBACK_ROWS = 20;

    private const SYSTEM_PROMPT = <<<'PROMPT'
You are KURSA's marketing copywriter. KURSA is a multi-tenant course and
certification platform: instructors build branded academies — courses,
quizzes, certificates, storefronts, sales funnels, payments (including
African mobile money), live sessions, and chat — and learners enroll, learn,
and earn certificates. Your audience is mostly Cameroonian and African
instructors and learners. Write warm, concrete copy — no hype, no more than
2 emojis total. ALWAYS produce BOTH a French and an English version, French
first. Respond with ONLY the requested JSON object, no commentary.
PROMPT;

    public function handle(FeatureInventoryService $inventory, OllamaService $ollama): int
    {
        if (! OllamaService::isConfigured()) {
            $this->warn('Ollama is not configured — skipping marketing content generation.');

            return self::SUCCESS;
        }

        $features = $inventory->build((bool) $this->option('fresh-inventory'))['features'] ?? [];

        if (empty($features)) {
            $this->warn('Feature inventory is empty — nothing to generate content about.');

            return self::SUCCESS;
        }

        $channels = $this->channelsToGenerate();
        $countOverride = $this->option('count') !== null ? max(0, (int) $this->option('count')) : null;

        $rows = [];

        foreach ($channels as $channel) {
            $pending = MarketingMessage::query()->pending()->where('channel', $channel)->count();
            $target = $countOverride ?? max(0, self::TARGET_POOL - $pending);

            $generated = 0;
            $skippedDupes = 0;
            $failures = 0;

            for ($i = 0; $i < $target; $i++) {
                $feature = $this->pickFeature($features, $channel);

                try {
                    $result = $this->generateOne($channel, $feature, $ollama);

                    if ($result === 'duplicate') {
                        $skippedDupes++;
                    } elseif ($result === 'generated') {
                        $generated++;
                    } else {
                        $failures++;
                    }
                } catch (\Throwable $e) {
                    $failures++;
                    Log::error("GenerateMarketingContentCommand: failed generating {$channel} content: {$e->getMessage()}");
                }
            }

            $rows[] = [
                $channel,
                MarketingMessage::query()->pending()->where('channel', $channel)->count(),
                $generated,
                $skippedDupes,
                $failures,
            ];
        }

        $this->table(['channel', 'pending', 'generated', 'skipped-dupes', 'failures'], $rows);

        return self::SUCCESS;
    }

    /**
     * @return array<int, string>
     */
    private function channelsToGenerate(): array
    {
        $channel = (string) $this->option('channel');

        if ($channel === 'all' || $channel === '') {
            return [
                MarketingMessage::CHANNEL_GROUP_TIP,
                MarketingMessage::CHANNEL_STATUS,
                MarketingMessage::CHANNEL_EMAIL,
            ];
        }

        return [$channel];
    }

    /**
     * Pick a feature, preferring ones whose name hasn't appeared in the last
     * N generated topics for this channel (rotation / least-recently-used).
     *
     * @param  array<int, array{name: string, summary: string, audience: string, doc: string}>  $features
     * @return array{name: string, summary: string, audience: string, doc: string}
     */
    private function pickFeature(array $features, string $channel): array
    {
        $recentTopics = MarketingMessage::query()
            ->where('channel', $channel)
            ->orderByDesc('id')
            ->limit(self::LOOKBACK_ROWS)
            ->pluck('topic')
            ->filter()
            ->map(fn ($topic) => mb_strtolower($topic))
            ->all();

        $unused = array_values(array_filter(
            $features,
            fn (array $feature) => ! in_array(mb_strtolower($feature['name']), $recentTopics, true)
        ));

        if (! empty($unused)) {
            return $unused[array_rand($unused)];
        }

        return $features[array_rand($features)];
    }

    /**
     * @param  array{name: string, summary: string, audience: string, doc: string}  $feature
     * @return string 'generated'|'duplicate'|'failed'
     */
    private function generateOne(string $channel, array $feature, OllamaService $ollama): string
    {
        return match ($channel) {
            MarketingMessage::CHANNEL_GROUP_TIP => $this->generateGroupTip($feature, $ollama),
            MarketingMessage::CHANNEL_STATUS => $this->generateStatus($feature, $ollama),
            MarketingMessage::CHANNEL_EMAIL => $this->generateEmailCampaign($feature, $ollama),
            default => 'failed',
        };
    }

    private function generateGroupTip(array $feature, OllamaService $ollama): string
    {
        $prompt = "Feature: {$feature['name']}\nWhat it does: {$feature['summary']}\n\n".
            'Write a practical tip / mini-guide (2-4 sentences per language) teaching how and why to use this '.
            'feature, ending with one actionable step. Respond with JSON: {"topic","fr","en"}.';

        $result = $ollama->chatJson(self::SYSTEM_PROMPT, $prompt);

        $topic = trim((string) ($result['topic'] ?? $feature['name']));
        $fr = trim((string) ($result['fr'] ?? ''));
        $en = trim((string) ($result['en'] ?? ''));

        if ($fr === '' || $en === '' || $fr === $en) {
            return 'failed';
        }

        $link = config('services.retention.links.panel');
        $body = $fr."\n\n".$en."\n\n👉 ".$link;

        return $this->store(MarketingMessage::CHANNEL_GROUP_TIP, $topic, $body, [
            'title' => $topic,
            'source' => ['feature' => $feature],
        ]);
    }

    private function generateStatus(array $feature, OllamaService $ollama): string
    {
        $prompt = "Feature: {$feature['name']}\nWhat it does: {$feature['summary']}\n\n".
            'Write ONE punchy WhatsApp-Status line per language (max ~140 characters each) that makes '.
            'instructors/learners curious about this feature. Respond with JSON: {"topic","fr","en"}.';

        $result = $ollama->chatJson(self::SYSTEM_PROMPT, $prompt);

        $topic = trim((string) ($result['topic'] ?? $feature['name']));
        $fr = trim((string) ($result['fr'] ?? ''));
        $en = trim((string) ($result['en'] ?? ''));

        if ($fr === '' || $en === '' || $fr === $en) {
            return 'failed';
        }

        $body = $fr."\n\n".$en;

        return $this->store(MarketingMessage::CHANNEL_STATUS, $topic, $body, [
            'title' => $topic,
            'source' => ['feature' => $feature],
        ]);
    }

    private function generateEmailCampaign(array $feature, OllamaService $ollama): string
    {
        $prompt = "Feature: {$feature['name']}\nWhat it does: {$feature['summary']}\n\n".
            'Write a marketing email campaign for this feature. For each language provide 2-4 short paragraphs '.
            'of HTML (no <html>/<head> tags, inline styles only) plus one button-styled <a href="{{cta_url}}"> '.
            'link — use the literal placeholder {{cta_url}} for the link target. Respond with JSON: '.
            '{"topic","subject_fr","subject_en","html_fr","html_en"}.';

        $result = $ollama->chatJson(self::SYSTEM_PROMPT, $prompt);

        $topic = trim((string) ($result['topic'] ?? $feature['name']));
        $subjectFr = trim((string) ($result['subject_fr'] ?? ''));
        $subjectEn = trim((string) ($result['subject_en'] ?? ''));
        $htmlFr = trim((string) ($result['html_fr'] ?? ''));
        $htmlEn = trim((string) ($result['html_en'] ?? ''));

        if ($subjectFr === '' || $subjectEn === '' || $htmlFr === '' || $htmlEn === '' || $htmlFr === $htmlEn) {
            return 'failed';
        }

        $subject = mb_substr($subjectFr.' / '.$subjectEn, 0, 150);
        $emailHtml = $this->sanitizeEmailHtml(
            $htmlFr.'<hr style="border:none;border-top:1px solid #eee;margin:24px 0">'.$htmlEn
        );

        return $this->store(MarketingMessage::CHANNEL_EMAIL, $topic, null, [
            'title' => $topic,
            'email_subject' => $subject,
            'email_html' => $emailHtml,
            'source' => ['feature' => $feature],
        ]);
    }

    private function sanitizeEmailHtml(string $html): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $html) ?? $html;
        $html = str_ireplace('<script', '&lt;script', $html);
        $html = preg_replace('/on\w+\s*=\s*(["\']).*?\1/i', '', $html) ?? $html;
        $html = str_ireplace('javascript:', '', $html);

        return $html;
    }

    /**
     * @param  array{title: string, source: array<string, mixed>, email_subject?: string, email_html?: string}  $extra
     * @return string 'generated'|'duplicate'
     */
    private function store(string $channel, string $topic, ?string $body, array $extra): string
    {
        $hashSource = $body ?? ($extra['email_html'] ?? '');
        $normalized = mb_strtolower(preg_replace('/\s+/', ' ', $hashSource) ?? $hashSource);
        $hash = hash('sha256', $channel.'|'.$normalized);

        if (MarketingMessage::query()->where('hash', $hash)->exists()) {
            return 'duplicate';
        }

        try {
            MarketingMessage::query()->create([
                'channel' => $channel,
                'topic' => $topic,
                'title' => $extra['title'],
                'body' => $body,
                'email_subject' => $extra['email_subject'] ?? null,
                'email_html' => $extra['email_html'] ?? null,
                'source' => $extra['source'],
                'model' => config('services.ollama.model'),
                'hash' => $hash,
                'status' => MarketingMessage::STATUS_PENDING,
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            // Unique constraint race — treat as a duplicate skip rather than a failure.
            return 'duplicate';
        }

        return 'generated';
    }
}
