<?php

namespace App\Console\Commands;

use App\Models\ContentChunk;
use App\Services\Marketing\EmbeddingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Builds/refreshes the retrieval-grounding knowledge base: chunks the
 * company blog's FULL post content (not just an excerpt) and platform docs,
 * embeds each new chunk via Ollama (nomic-embed-text/EmbeddingService), and
 * upserts them into content_chunks. GenerateMarketingContentCommand reads
 * this table to ground generation in the real article/doc text and to
 * semantically dedupe generated content.
 */
class IndexKnowledgeCommand extends Command
{
    protected $signature = 'kursa:index-knowledge {--fresh : Truncate and rebuild the knowledge base from scratch} {--limit= : Max blog posts to scan}';

    protected $description = 'Build/refresh the retrieval knowledge base (blog posts + docs) used to ground marketing content generation';

    private const BLOG_PER_PAGE = 20;

    private const MAX_CHUNK_CHARS = 1200;

    private const MAX_CHUNKS_PER_SOURCE = 4;

    private const EMBED_BATCH_SIZE = 16;

    private const MAX_DOC_BYTES = 60 * 1024;

    private const MAX_DOC_CHARS = 6000;

    public function handle(EmbeddingService $embeddings): int
    {
        if ($this->option('fresh')) {
            ContentChunk::query()->truncate();
        }

        $blogPosts = $this->fetchBlogPosts();
        $docPaths = $this->docPaths();

        $sourcesScanned = count($blogPosts) + count($docPaths);

        $candidates = [];

        foreach ($blogPosts as $post) {
            foreach ($this->chunksForBlogPost($post) as $chunk) {
                $candidates[] = $chunk;
            }
        }

        foreach ($docPaths as $path) {
            foreach ($this->chunksForDoc($path) as $chunk) {
                $candidates[] = $chunk;
            }
        }

        $existingHashes = empty($candidates) ? [] : ContentChunk::query()
            ->whereIn('hash', array_column($candidates, 'hash'))
            ->pluck('hash')
            ->all();

        $newChunks = array_values(array_filter(
            $candidates,
            fn (array $chunk) => ! in_array($chunk['hash'], $existingHashes, true)
        ));

        $embedded = 0;
        $failures = 0;

        foreach (array_chunk($newChunks, self::EMBED_BATCH_SIZE) as $batch) {
            $vectors = $embeddings->embed(array_map(fn (array $chunk) => $chunk['content'], $batch));

            foreach ($batch as $index => $chunk) {
                $embedding = $vectors[$index] ?? null;

                $embedding === null ? $failures++ : $embedded++;

                try {
                    ContentChunk::query()->create([
                        'source_type' => $chunk['source_type'],
                        'source_id' => $chunk['source_id'],
                        'url' => $chunk['url'],
                        'title' => $chunk['title'],
                        'chunk_index' => $chunk['chunk_index'],
                        'content' => $chunk['content'],
                        'embedding' => $embedding,
                        'hash' => $chunk['hash'],
                    ]);
                } catch (\Illuminate\Database\QueryException $e) {
                    // Unique hash race — another process indexed it first.
                    continue;
                }
            }
        }

        $this->table(
            ['sources scanned', 'chunks new', 'chunks embedded', 'failures'],
            [[$sourcesScanned, count($newChunks), $embedded, $failures]]
        );

        return self::SUCCESS;
    }

    /**
     * Paginate the WP REST posts endpoint pulling FULL content (not the
     * excerpt), until pages run out or --limit is reached.
     *
     * @return array<int, array<string, mixed>> raw WP post payloads
     */
    private function fetchBlogPosts(): array
    {
        $url = rtrim((string) config('services.blog.url'), '/');
        $username = (string) config('services.blog.username');
        $appPassword = (string) config('services.blog.app_password');
        $limit = $this->option('limit') !== null ? max(0, (int) $this->option('limit')) : null;

        $posts = [];
        $page = 1;

        while (true) {
            try {
                $request = Http::timeout(20)->acceptJson();

                if ($username !== '') {
                    $request = $request->withBasicAuth($username, $appPassword);
                }

                $response = $request->get("{$url}/wp-json/wp/v2/posts", [
                    'per_page' => self::BLOG_PER_PAGE,
                    'page' => $page,
                    '_fields' => 'id,title,link,content,date',
                ]);

                // WP returns 400 for "rest_post_invalid_page_number" once past
                // the last page — treat it the same as an empty page.
                if ($response->status() === 400) {
                    break;
                }

                $response->throw();

                $pagePosts = $response->json();
            } catch (\Throwable $e) {
                Log::warning("IndexKnowledgeCommand: failed fetching blog posts page {$page}: {$e->getMessage()}");

                break;
            }

            if (! is_array($pagePosts) || empty($pagePosts)) {
                break;
            }

            foreach ($pagePosts as $post) {
                $posts[] = $post;

                if ($limit !== null && count($posts) >= $limit) {
                    return $posts;
                }
            }

            if (count($pagePosts) < self::BLOG_PER_PAGE) {
                break;
            }

            $page++;
        }

        return $posts;
    }

    /**
     * @param  array<string, mixed>  $post
     * @return array<int, array{source_type:string, source_id:string, url:?string, title:?string, chunk_index:int, content:string, hash:string}>
     */
    private function chunksForBlogPost(array $post): array
    {
        $id = (int) ($post['id'] ?? 0);

        if ($id <= 0) {
            return [];
        }

        $title = html_entity_decode(strip_tags((string) ($post['title']['rendered'] ?? '')), ENT_QUOTES);
        $content = $this->htmlToPlainText((string) ($post['content']['rendered'] ?? ''));
        $link = (string) ($post['link'] ?? '');

        if (trim($content) === '') {
            return [];
        }

        return $this->buildChunks('blog', (string) $id, $link, trim($title), $content);
    }

    /**
     * @return array<int, array{source_type:string, source_id:string, url:?string, title:?string, chunk_index:int, content:string, hash:string}>
     */
    private function chunksForDoc(string $path): array
    {
        if (! File::exists($path) || File::size($path) > self::MAX_DOC_BYTES) {
            return [];
        }

        $contents = mb_substr(File::get($path), 0, self::MAX_DOC_CHARS);

        if (trim($contents) === '') {
            return [];
        }

        $relative = ltrim(str_replace(base_path(), '', $path), '/\\');

        return $this->buildChunks('doc', $relative, null, basename($path), $contents);
    }

    /**
     * @return array<int, string> absolute file paths
     */
    private function docPaths(): array
    {
        $paths = [];

        foreach (glob(base_path('docs/architecture/*.md')) ?: [] as $file) {
            $paths[] = $file;
        }

        foreach (glob(base_path('docs/*.md')) ?: [] as $file) {
            $paths[] = $file;
        }

        return array_values(array_unique(array_filter(
            $paths,
            fn (string $path) => strtoupper(basename($path)) !== 'AGENTS.MD'
        )));
    }

    /**
     * Split text on paragraph boundaries into ~MAX_CHUNK_CHARS chunks,
     * capped at MAX_CHUNKS_PER_SOURCE chunks per source.
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
     * @return array<int, array{source_type:string, source_id:string, url:?string, title:?string, chunk_index:int, content:string, hash:string}>
     */
    private function buildChunks(string $sourceType, string $sourceId, ?string $url, ?string $title, string $text): array
    {
        $chunks = [];

        foreach ($this->chunkText($text) as $index => $content) {
            $chunks[] = [
                'source_type' => $sourceType,
                'source_id' => $sourceId,
                'url' => $url,
                'title' => $title,
                'chunk_index' => $index,
                'content' => $content,
                'hash' => hash('sha256', "{$sourceType}|{$sourceId}|{$index}|{$content}"),
            ];
        }

        return $chunks;
    }

    /**
     * Strip HTML down to plain text while preserving paragraph boundaries
     * (needed so chunkText() can split on paragraph breaks).
     */
    private function htmlToPlainText(string $html): string
    {
        $html = preg_replace('/<\/(p|div|h[1-6]|li|br)\s*\/?>/i', "\n\n", $html) ?? $html;
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES);
        $text = preg_replace('/[ \t]+/', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/', "\n\n", $text) ?? $text;

        return trim($text);
    }
}
