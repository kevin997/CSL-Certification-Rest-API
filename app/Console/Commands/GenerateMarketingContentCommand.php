<?php

namespace App\Console\Commands;

use App\Ai\Agents\EmailCampaignAgent;
use App\Ai\Agents\MarketingTipAgent;
use App\Ai\Agents\StatusTeaserAgent;
use App\Models\MarketingMessage;
use App\Services\Marketing\BlogContentService;
use App\Services\Marketing\FeatureInventoryService;
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

    public function handle(FeatureInventoryService $inventory, BlogContentService $blog): int
    {
        if (blank(config('ai.providers.ollama.url'))) {
            $this->warn('Ollama is not configured — skipping marketing content generation.');

            return self::SUCCESS;
        }

        // The company blog is the primary source for tips/tutorials/campaigns.
        // The feature inventory only fills gaps once every recent post has
        // already been used for a given channel.
        $posts = $blog->recentPosts();

        // The inventory's first build crawls docs/ through Ollama and can take
        // a very long time on a busy box — resolve it LAZILY, only when a
        // channel has exhausted the blog posts and needs the feature fallback.
        $features = null;
        $loadFeatures = function () use (&$features, $inventory): array {
            return $features ??= $inventory->build((bool) $this->option('fresh-inventory'))['features'] ?? [];
        };

        if (empty($posts) && empty($loadFeatures())) {
            $this->warn('No blog posts or feature inventory available — nothing to generate content about.');

            return self::SUCCESS;
        }

        $channels = $this->channelsToGenerate();
        $countOverride = $this->option('count') !== null ? max(0, (int) $this->option('count')) : null;

        $rows = [];

        foreach ($channels as $channel) {
            $pending = MarketingMessage::query()->pending()->where('channel', $channel)->count();
            $target = $countOverride ?? max(0, self::TARGET_POOL - $pending);

            $usedBlogPostIds = $this->usedBlogPostIds($channel);

            $generated = 0;
            $skippedDupes = 0;
            $failures = 0;
            $blogSourced = 0;
            $featureSourced = 0;

            for ($i = 0; $i < $target; $i++) {
                $post = $this->pickUnusedPost($posts, $usedBlogPostIds);

                if ($post === null && empty($loadFeatures())) {
                    // Nothing left to source content from for this channel.
                    break;
                }

                try {
                    if ($post !== null) {
                        // Mark as used for the rest of this run regardless of
                        // outcome, so a failure doesn't retry the same post.
                        $usedBlogPostIds[] = $post['id'];
                        $result = $this->generateOneFromBlog($channel, $post);
                    } else {
                        $feature = $this->pickFeature($loadFeatures(), $channel);
                        $result = $this->generateOne($channel, $feature);
                    }

                    if ($result === 'duplicate') {
                        $skippedDupes++;
                    } elseif ($result === 'generated') {
                        $generated++;
                        $post !== null ? $blogSourced++ : $featureSourced++;
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
                "{$blogSourced} blog / {$featureSourced} feature",
            ];
        }

        $this->table(['channel', 'pending', 'generated', 'skipped-dupes', 'failures', 'source'], $rows);

        return self::SUCCESS;
    }

    /**
     * Ids of blog posts already used to generate content for this channel,
     * across all previously generated messages (any status).
     *
     * @return array<int, int>
     */
    private function usedBlogPostIds(string $channel): array
    {
        return MarketingMessage::query()
            ->where('channel', $channel)
            ->whereNotNull('source')
            ->pluck('source')
            ->map(function ($source) {
                $decoded = is_string($source) ? json_decode($source, true) : $source;

                return is_array($decoded) ? ($decoded['blog_post_id'] ?? null) : null;
            })
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /**
     * The newest blog post not yet used for this channel, or null when every
     * fetched post has already been used (or there are no posts at all).
     *
     * @param  array<int, array{id:int, title:string, link:string, excerpt:string, date:string}>  $posts
     * @param  array<int, int>  $usedIds
     * @return array{id:int, title:string, link:string, excerpt:string, date:string}|null
     */
    private function pickUnusedPost(array $posts, array $usedIds): ?array
    {
        foreach ($posts as $post) {
            if (! in_array($post['id'], $usedIds, true)) {
                return $post;
            }
        }

        return null;
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
    private function generateOne(string $channel, array $feature): string
    {
        return match ($channel) {
            MarketingMessage::CHANNEL_GROUP_TIP => $this->generateGroupTip($feature),
            MarketingMessage::CHANNEL_STATUS => $this->generateStatus($feature),
            MarketingMessage::CHANNEL_EMAIL => $this->generateEmailCampaign($feature),
            default => 'failed',
        };
    }

    /**
     * @param  array{id:int, title:string, link:string, excerpt:string, date:string}  $post
     * @return string 'generated'|'duplicate'|'failed'
     */
    private function generateOneFromBlog(string $channel, array $post): string
    {
        return match ($channel) {
            MarketingMessage::CHANNEL_GROUP_TIP => $this->generateBlogGroupTip($post),
            MarketingMessage::CHANNEL_STATUS => $this->generateBlogStatus($post),
            MarketingMessage::CHANNEL_EMAIL => $this->generateBlogEmailCampaign($post),
            default => 'failed',
        };
    }

    private function generateGroupTip(array $feature): string
    {
        $prompt = "Feature: {$feature['name']}\nWhat it does: {$feature['summary']}";

        $result = (new MarketingTipAgent)->promptWithFailover($prompt);

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
            'source' => ['kind' => 'feature', 'feature' => $feature],
        ]);
    }

    private function generateStatus(array $feature): string
    {
        $prompt = "Feature: {$feature['name']}\nWhat it does: {$feature['summary']}";

        $result = (new StatusTeaserAgent)->promptWithFailover($prompt);

        $topic = trim((string) ($result['topic'] ?? $feature['name']));
        $fr = trim((string) ($result['fr'] ?? ''));
        $en = trim((string) ($result['en'] ?? ''));

        if ($fr === '' || $en === '' || $fr === $en) {
            return 'failed';
        }

        $body = $fr."\n\n".$en;

        return $this->store(MarketingMessage::CHANNEL_STATUS, $topic, $body, [
            'title' => $topic,
            'source' => ['kind' => 'feature', 'feature' => $feature],
        ]);
    }

    private function generateEmailCampaign(array $feature): string
    {
        $prompt = "Feature: {$feature['name']}\nWhat it does: {$feature['summary']}";

        $result = (new EmailCampaignAgent)->promptWithFailover($prompt);

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
            'source' => ['kind' => 'feature', 'feature' => $feature],
        ]);
    }

    /**
     * @param  array{id:int, title:string, link:string, excerpt:string, date:string}  $post
     * @return string 'generated'|'duplicate'|'failed'
     */
    private function generateBlogGroupTip(array $post): string
    {
        $prompt = "Blog article: {$post['title']}\nExcerpt: {$post['excerpt']}";

        $result = (new MarketingTipAgent)->promptWithFailover($prompt);

        $topic = mb_substr(trim((string) ($result['topic'] ?? $post['title'])), 0, 160);
        $fr = trim((string) ($result['fr'] ?? ''));
        $en = trim((string) ($result['en'] ?? ''));

        if ($fr === '' || $en === '' || $fr === $en) {
            return 'failed';
        }

        $body = $fr."\n\n".$en."\n\n👉 ".$post['link'];

        return $this->store(MarketingMessage::CHANNEL_GROUP_TIP, $topic, $body, [
            'title' => $topic,
            'source' => $this->blogSource($post),
        ]);
    }

    /**
     * @param  array{id:int, title:string, link:string, excerpt:string, date:string}  $post
     * @return string 'generated'|'duplicate'|'failed'
     */
    private function generateBlogStatus(array $post): string
    {
        $prompt = "Blog article: {$post['title']}\nExcerpt: {$post['excerpt']}";

        $result = (new StatusTeaserAgent)->promptWithFailover($prompt);

        $topic = mb_substr(trim((string) ($result['topic'] ?? $post['title'])), 0, 160);
        $fr = trim((string) ($result['fr'] ?? ''));
        $en = trim((string) ($result['en'] ?? ''));

        if ($fr === '' || $en === '' || $fr === $en) {
            return 'failed';
        }

        $body = $fr."\n\n".$en."\n\n".$post['link'];

        return $this->store(MarketingMessage::CHANNEL_STATUS, $topic, $body, [
            'title' => $topic,
            'source' => $this->blogSource($post),
        ]);
    }

    /**
     * @param  array{id:int, title:string, link:string, excerpt:string, date:string}  $post
     * @return string 'generated'|'duplicate'|'failed'
     */
    private function generateBlogEmailCampaign(array $post): string
    {
        $prompt = "Blog article: {$post['title']}\nExcerpt: {$post['excerpt']}";

        $result = (new EmailCampaignAgent)->promptWithFailover($prompt);

        $topic = mb_substr(trim((string) ($result['topic'] ?? $post['title'])), 0, 160);
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

        // Blog-sourced campaigns link to the article itself, not the panel —
        // resolve {{cta_url}} now so SendEmailCampaignCommand's later
        // str_replace (which targets the panel link) is a harmless no-op.
        $emailHtml = str_replace('{{cta_url}}', $post['link'], $emailHtml);

        return $this->store(MarketingMessage::CHANNEL_EMAIL, $topic, null, [
            'title' => $topic,
            'email_subject' => $subject,
            'email_html' => $emailHtml,
            'source' => $this->blogSource($post),
        ]);
    }

    /**
     * @param  array{id:int, title:string, link:string, excerpt:string, date:string}  $post
     * @return array{kind: string, blog_post_id: int, blog_link: string, model: string}
     */
    private function blogSource(array $post): array
    {
        return [
            'kind' => 'blog',
            'blog_post_id' => $post['id'],
            'blog_link' => $post['link'],
            'model' => (string) config('services.ollama.model'),
        ];
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
