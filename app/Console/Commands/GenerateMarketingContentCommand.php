<?php

namespace App\Console\Commands;

use App\Ai\Agents\EmailCampaignAgent;
use App\Ai\Agents\MarketingTipAgent;
use App\Ai\Agents\StatusTeaserAgent;
use App\Models\ContentChunk;
use App\Models\MarketingMessage;
use App\Services\Marketing\BlogContentService;
use App\Services\Marketing\EmbeddingService;
use App\Services\Marketing\FeatureInventoryService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
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

    /** How many same-channel prior messages to compare a new candidate against for semantic dedupe. */
    private const DEDUPE_LOOKBACK = 60;

    /** Chunking parameters mirrored from IndexKnowledgeCommand, kept in sync for the lazy-index self-heal path. */
    private const MAX_CHUNK_CHARS = 1200;

    private const MAX_CHUNKS_PER_SOURCE = 4;

    /** Max chars of grounding content injected into the generation prompt. */
    private const MAX_GROUNDING_CHARS = 2500;

    private EmbeddingService $embeddings;

    public function handle(FeatureInventoryService $inventory, BlogContentService $blog, EmbeddingService $embeddings): int
    {
        if (blank(config('ai.providers.ollama.url'))) {
            $this->warn('Ollama is not configured — skipping marketing content generation.');

            return self::SUCCESS;
        }

        $this->embeddings = $embeddings;

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
                        $result = $this->generateOneFromBlog($channel, $post, $blog);
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
    private function generateOneFromBlog(string $channel, array $post, BlogContentService $blog): string
    {
        return match ($channel) {
            MarketingMessage::CHANNEL_GROUP_TIP => $this->generateBlogGroupTip($post, $blog),
            MarketingMessage::CHANNEL_STATUS => $this->generateBlogStatus($post, $blog),
            MarketingMessage::CHANNEL_EMAIL => $this->generateBlogEmailCampaign($post, $blog),
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
    private function generateBlogGroupTip(array $post, BlogContentService $blog): string
    {
        $prompt = "Blog article: {$post['title']}\nExcerpt: {$post['excerpt']}";
        $prompt .= $this->groundingSuffix($post, $blog);

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
    private function generateBlogStatus(array $post, BlogContentService $blog): string
    {
        $prompt = "Blog article: {$post['title']}\nExcerpt: {$post['excerpt']}";
        $prompt .= $this->groundingSuffix($post, $blog);

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
    private function generateBlogEmailCampaign(array $post, BlogContentService $blog): string
    {
        $prompt = "Blog article: {$post['title']}\nExcerpt: {$post['excerpt']}";
        $prompt .= $this->groundingSuffix($post, $blog);

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
     * Build the "Contenu de l'article:" grounding suffix for a blog-sourced
     * prompt from the post's indexed chunks — self-healing (lazily indexes
     * the post if it has no chunks yet) so generation never has to wait for
     * the weekly kursa:index-knowledge run. Returns '' (no-op) if grounding
     * is unavailable for any reason, leaving the excerpt-only prompt intact.
     *
     * @param  array{id:int, title:string, link:string, excerpt:string, date:string}  $post
     */
    private function groundingSuffix(array $post, BlogContentService $blog): string
    {
        $chunks = ContentChunk::query()
            ->where('source_type', 'blog')
            ->where('source_id', (string) $post['id'])
            ->orderBy('chunk_index')
            ->limit(3)
            ->get();

        if ($chunks->isEmpty()) {
            $chunks = $this->lazyIndexBlogPost($post, $blog);
        }

        if ($chunks->isEmpty()) {
            return '';
        }

        $content = mb_substr($chunks->pluck('content')->implode("\n\n"), 0, self::MAX_GROUNDING_CHARS);

        if (trim($content) === '') {
            return '';
        }

        return "\n\nContenu de l'article:\n{$content}";
    }

    /**
     * Fetch the post's full content, chunk it, embed fail-open, and store
     * the chunks — so a post that hasn't been through kursa:index-knowledge
     * yet still gets grounding on this very generation run.
     *
     * @param  array{id:int, title:string, link:string, excerpt:string, date:string}  $post
     * @return Collection<int, ContentChunk>
     */
    private function lazyIndexBlogPost(array $post, BlogContentService $blog): Collection
    {
        try {
            $full = $blog->fetchPostWithContent((int) $post['id']);

            if ($full === null || trim($full['content']) === '') {
                return collect();
            }

            $pieces = $this->chunkText($full['content']);

            if (empty($pieces)) {
                return collect();
            }

            $vectors = $this->embeddings->embed($pieces);

            return collect($pieces)->map(function (string $content, int $index) use ($post, $full, $vectors) {
                $hash = hash('sha256', "blog|{$post['id']}|{$index}|{$content}");

                return ContentChunk::query()->firstOrCreate(
                    ['hash' => $hash],
                    [
                        'source_type' => 'blog',
                        'source_id' => (string) $post['id'],
                        'url' => $full['link'],
                        'title' => $full['title'],
                        'chunk_index' => $index,
                        'content' => $content,
                        'embedding' => $vectors[$index] ?? null,
                    ]
                );
            })->values();
        } catch (\Throwable $e) {
            Log::warning("GenerateMarketingContentCommand: lazy-index failed for post {$post['id']}: {$e->getMessage()}");

            return collect();
        }
    }

    /**
     * Split text on paragraph boundaries into ~MAX_CHUNK_CHARS chunks,
     * capped at MAX_CHUNKS_PER_SOURCE — mirrors IndexKnowledgeCommand's
     * chunking so lazily-indexed chunks are consistent with the weekly job.
     *
     * @return array<int, string>
     */
    private function chunkText(string $text): array
    {
        $paragraphs = preg_split('/\n\s*\n/', trim($text)) ?: [];
        $chunks = [];
        $current = '';

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);

            if ($paragraph === '') {
                continue;
            }

            if ($current !== '' && mb_strlen($current) + mb_strlen($paragraph) + 2 > self::MAX_CHUNK_CHARS) {
                $chunks[] = $current;
                $current = '';

                if (count($chunks) >= self::MAX_CHUNKS_PER_SOURCE) {
                    return $chunks;
                }
            }

            $current = $current === '' ? $paragraph : $current."\n\n".$paragraph;

            while (mb_strlen($current) > self::MAX_CHUNK_CHARS) {
                $chunks[] = trim(mb_substr($current, 0, self::MAX_CHUNK_CHARS));
                $current = mb_substr($current, self::MAX_CHUNK_CHARS);

                if (count($chunks) >= self::MAX_CHUNKS_PER_SOURCE) {
                    return $chunks;
                }
            }
        }

        if ($current !== '' && count($chunks) < self::MAX_CHUNKS_PER_SOURCE) {
            $chunks[] = $current;
        }

        return array_slice($chunks, 0, self::MAX_CHUNKS_PER_SOURCE);
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
     * Embed the candidate's dedupe text and compare it against recent
     * same-channel messages that have embeddings. Returns the embedding to
     * store (null if embedding failed — fail-open, hash dedupe still
     * applies), or the string 'duplicate' when the candidate is a
     * near-semantic-duplicate of a recent message (cosine similarity >=
     * MARKETING_DEDUPE_THRESHOLD).
     *
     * @return array<float>|string|null
     */
    private function embeddingForDedupe(string $channel, ?string $body, ?string $emailHtml): array|string|null
    {
        $text = $body ?? strip_tags((string) $emailHtml);

        if (trim($text) === '') {
            return null;
        }

        $vectors = $this->embeddings->embed([$text]);
        $embedding = $vectors[0] ?? null;

        if ($embedding === null) {
            return null;
        }

        $threshold = (float) env('MARKETING_DEDUPE_THRESHOLD', 0.92);

        $recentEmbeddings = MarketingMessage::query()
            ->where('channel', $channel)
            ->whereNotNull('embedding')
            ->orderByDesc('id')
            ->limit(self::DEDUPE_LOOKBACK)
            ->pluck('embedding');

        foreach ($recentEmbeddings as $recent) {
            if (is_array($recent) && EmbeddingService::cosine($embedding, $recent) >= $threshold) {
                return 'duplicate';
            }
        }

        return $embedding;
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

        $embedding = $this->embeddingForDedupe($channel, $body, $extra['email_html'] ?? null);

        if ($embedding === 'duplicate') {
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
                'embedding' => $embedding,
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
