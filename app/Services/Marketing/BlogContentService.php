<?php

namespace App\Services\Marketing;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pulls recent posts from the company blog (WordPress REST API) so the
 * marketing content engine can source tips/tutorials/campaigns from real
 * published articles instead of only from the feature inventory.
 */
class BlogContentService
{
    /**
     * Cached for the process lifetime — the generator command calls this
     * once per channel in a loop and shouldn't re-fetch every iteration.
     *
     * @var array<int, array{id:int, title:string, link:string, excerpt:string, date:string}>|null
     */
    private static ?array $cache = null;

    /**
     * @return array<int, array{id:int, title:string, link:string, excerpt:string, date:string}>
     */
    public function recentPosts(int $limit = 40): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $url = rtrim((string) config('services.blog.url'), '/');
        $username = (string) config('services.blog.username');
        $appPassword = (string) config('services.blog.app_password');

        try {
            $request = Http::timeout(20)->acceptJson();

            if ($username !== '') {
                $request = $request->withBasicAuth($username, $appPassword);
            }

            $response = $request->get($url.'/wp-json/wp/v2/posts', [
                'per_page' => $limit,
                '_fields' => 'id,title,link,excerpt,date',
            ])->throw();

            $posts = $response->json();

            if (! is_array($posts)) {
                Log::warning('BlogContentService: unexpected response shape from blog REST API.');

                return self::$cache = [];
            }

            return self::$cache = array_map(fn (array $post) => $this->mapPost($post), $posts);
        } catch (\Throwable $e) {
            Log::warning("BlogContentService: failed fetching recent posts: {$e->getMessage()}");

            return self::$cache = [];
        }
    }

    /**
     * @param  array<string, mixed>  $post
     * @return array{id:int, title:string, link:string, excerpt:string, date:string}
     */
    private function mapPost(array $post): array
    {
        $title = html_entity_decode(strip_tags((string) ($post['title']['rendered'] ?? '')), ENT_QUOTES);

        $excerpt = strip_tags((string) ($post['excerpt']['rendered'] ?? ''));
        $excerpt = trim(preg_replace('/\s+/', ' ', $excerpt) ?? $excerpt);
        $excerpt = mb_substr($excerpt, 0, 400);

        return [
            'id' => (int) ($post['id'] ?? 0),
            'title' => trim($title),
            'link' => (string) ($post['link'] ?? ''),
            'excerpt' => $excerpt,
            'date' => (string) ($post['date'] ?? ''),
        ];
    }
}
