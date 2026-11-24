<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class LinkPreviewTest extends TestCase
{
    use RefreshDatabase;

    private const PUBLIC_IP = '93.184.216.34';

    private User $user;

    private string $domain = 'academy-links.test';

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        Http::preventStrayRequests();

        $this->user = User::factory()->create([
            'name' => 'Instructor Test',
            'role' => UserRole::INDIVIDUAL_TEACHER->value,
        ]);

        $environment = Environment::factory()->create([
            'owner_id' => $this->user->id,
            'primary_domain' => $this->domain,
            'domain_verified_at' => now(),
        ]);

        EnvironmentUser::create([
            'environment_id' => $environment->id,
            'user_id' => $this->user->id,
            'role' => UserRole::INDIVIDUAL_TEACHER->value,
            'is_account_setup' => true,
        ]);
    }

    public function test_it_returns_open_graph_metadata_for_a_pasted_link(): void
    {
        Http::fake([
            "https://{$this->publicIp()}/guide" => Http::response($this->htmlPage(), 200, [
                'Content-Type' => 'text/html; charset=utf-8',
            ]),
        ]);

        $response = $this->postPreview(["https://{$this->publicIp()}/guide"]);

        $response->assertOk();
        $response->assertJsonPath('data.0.url', "https://{$this->publicIp()}/guide");
        $response->assertJsonPath('data.0.preview.title', 'The Complete Media Buying Guide');
        $response->assertJsonPath('data.0.preview.description', 'How to buy media profitably.');
        $response->assertJsonPath('data.0.preview.image', "https://{$this->publicIp()}/images/cover.png");
        $response->assertJsonPath('data.0.preview.site_name', 'Kursa Insights');
        $response->assertJsonPath('data.0.preview.favicon', "https://{$this->publicIp()}/favicon.ico");
        $response->assertJsonPath('data.0.preview.type', 'article');
        $response->assertJsonPath('data.0.preview.resolved_url', "https://{$this->publicIp()}/guide");
    }

    public function test_it_resolves_relative_image_and_favicon_urls(): void
    {
        Http::fake([
            "https://{$this->publicIp()}/blog/post" => Http::response($this->htmlPage([
                'og:image' => '/media/cover.jpg',
                'favicon' => '../assets/icon.png',
            ]), 200, ['Content-Type' => 'text/html']),
        ]);

        $response = $this->postPreview(["https://{$this->publicIp()}/blog/post"]);

        $response->assertOk();
        $response->assertJsonPath('data.0.preview.image', "https://{$this->publicIp()}/media/cover.jpg");
        $response->assertJsonPath('data.0.preview.favicon', "https://{$this->publicIp()}/assets/icon.png");
    }

    public function test_it_falls_back_to_the_title_tag_when_open_graph_is_missing(): void
    {
        Http::fake([
            "https://{$this->publicIp()}/plain" => Http::response(
                '<html><head><title>Plain page</title><meta name="description" content="A plain description"></head><body></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $response = $this->postPreview(["https://{$this->publicIp()}/plain"]);

        $response->assertOk();
        $response->assertJsonPath('data.0.preview.title', 'Plain page');
        $response->assertJsonPath('data.0.preview.description', 'A plain description');
    }

    public function test_it_returns_a_null_preview_when_the_page_has_no_metadata(): void
    {
        Http::fake([
            "https://{$this->publicIp()}/empty" => Http::response(
                '<html><head></head><body>Nothing</body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);

        $response = $this->postPreview(["https://{$this->publicIp()}/empty"]);

        $response->assertOk();
        $response->assertJsonPath('data.0.url', "https://{$this->publicIp()}/empty");
        $response->assertJsonPath('data.0.preview', null);
    }

    public function test_it_returns_a_null_preview_for_non_html_responses(): void
    {
        Http::fake([
            "https://{$this->publicIp()}/file.zip" => Http::response('PK', 200, [
                'Content-Type' => 'application/zip',
            ]),
        ]);

        $response = $this->postPreview(["https://{$this->publicIp()}/file.zip"]);

        $response->assertOk();
        $response->assertJsonPath('data.0.preview', null);
    }

    public function test_it_previews_a_direct_image_url(): void
    {
        Http::fake([
            "https://{$this->publicIp()}/images/photo.png" => Http::response('PNG', 200, [
                'Content-Type' => 'image/png',
            ]),
        ]);

        $response = $this->postPreview(["https://{$this->publicIp()}/images/photo.png"]);

        $response->assertOk();
        $response->assertJsonPath('data.0.preview.image', "https://{$this->publicIp()}/images/photo.png");
        $response->assertJsonPath('data.0.preview.title', 'photo.png');
        $response->assertJsonPath('data.0.preview.type', 'image');
    }

    public function test_it_rejects_private_loopback_and_metadata_hosts(): void
    {
        $response = $this->postPreview([
            'http://127.0.0.1/admin',
            'http://localhost:8000/',
            'http://169.254.169.254/latest/meta-data/',
            'http://10.0.0.5/internal',
            'http://[::1]/',
            'http://192.168.1.20/router',
        ]);

        $response->assertOk();
        $response->assertJsonCount(6, 'data');
        $response->assertJsonPath('data.0.preview', null);
        $response->assertJsonPath('data.5.preview', null);

        Http::assertNothingSent();
    }

    public function test_it_rejects_non_http_schemes_and_relative_urls(): void
    {
        $response = $this->postPreview([
            'javascript:alert(1)',
            'mailto:hello@example.com',
            '/relative/path',
            'ftp://example.com/file',
        ]);

        $response->assertOk();
        $response->assertJsonCount(0, 'data');

        Http::assertNothingSent();
    }

    public function test_it_does_not_follow_redirects_to_private_addresses(): void
    {
        Http::fake([
            "https://{$this->publicIp()}/redirect" => Http::response('', 302, [
                'Location' => 'http://127.0.0.1:9000/secret',
            ]),
        ]);

        $response = $this->postPreview(["https://{$this->publicIp()}/redirect"]);

        $response->assertOk();
        $response->assertJsonPath('data.0.preview', null);

        Http::assertSentCount(1);
    }

    public function test_it_follows_a_public_redirect_and_parses_the_destination(): void
    {
        Http::fake([
            "https://{$this->publicIp()}/old" => Http::response('', 301, [
                'Location' => "https://{$this->publicIp()}/new",
            ]),
            "https://{$this->publicIp()}/new" => Http::response($this->htmlPage(), 200, [
                'Content-Type' => 'text/html',
            ]),
        ]);

        $response = $this->postPreview(["https://{$this->publicIp()}/old"]);

        $response->assertOk();
        $response->assertJsonPath('data.0.url', "https://{$this->publicIp()}/old");
        $response->assertJsonPath('data.0.preview.title', 'The Complete Media Buying Guide');
        $response->assertJsonPath('data.0.preview.resolved_url', "https://{$this->publicIp()}/new");

        Http::assertSentCount(2);
    }

    public function test_it_caches_previews_so_the_same_link_is_fetched_once(): void
    {
        Http::fake([
            "https://{$this->publicIp()}/guide" => Http::response($this->htmlPage(), 200, [
                'Content-Type' => 'text/html',
            ]),
        ]);

        $this->postPreview(["https://{$this->publicIp()}/guide"])->assertOk();
        $this->postPreview(["https://{$this->publicIp()}/guide"])->assertOk();

        Http::assertSentCount(1);
    }

    public function test_it_deduplicates_urls_within_a_single_request(): void
    {
        Http::fake([
            "https://{$this->publicIp()}/guide" => Http::response($this->htmlPage(), 200, [
                'Content-Type' => 'text/html',
            ]),
        ]);

        $response = $this->postPreview([
            "https://{$this->publicIp()}/guide",
            "https://{$this->publicIp()}/guide",
        ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'data');

        Http::assertSentCount(1);
    }

    public function test_it_requires_an_authenticated_user(): void
    {
        $this->postJson('/api/link-previews', [
            'urls' => ["https://{$this->publicIp()}/guide"],
        ])->assertUnauthorized();
    }

    public function test_it_validates_the_urls_payload(): void
    {
        $this->postPreview([])->assertUnprocessable();

        $this->postPreview(array_map(
            fn (int $index) => "https://{$this->publicIp()}/page-{$index}",
            range(1, 9),
        ))->assertUnprocessable();
    }

    public function test_it_reads_the_head_of_a_page_larger_than_the_parse_limit(): void
    {
        // Marketing pages ship hundreds of KB of scripts after the metadata.
        $largePage = $this->htmlPage().str_repeat('<div>spacer</div>', 60000);

        $this->assertGreaterThan(512 * 1024, strlen($largePage));

        Http::fake([
            "https://{$this->publicIp()}/large" => Http::response($largePage, 200, [
                'Content-Type' => 'text/html',
            ]),
        ]);

        $response = $this->postPreview(["https://{$this->publicIp()}/large"]);

        $response->assertOk();
        $response->assertJsonPath('data.0.preview.title', 'The Complete Media Buying Guide');
        $response->assertJsonPath('data.0.preview.description', 'How to buy media profitably.');
    }

    public function test_it_abandons_bodies_larger_than_the_hard_limit(): void
    {
        $hugePage = $this->htmlPage().str_repeat('x', 2 * 1024 * 1024 + 1);

        Http::fake([
            "https://{$this->publicIp()}/huge" => Http::response($hugePage, 200, [
                'Content-Type' => 'text/html',
            ]),
        ]);

        $response = $this->postPreview(["https://{$this->publicIp()}/huge"]);

        $response->assertOk();
        $response->assertJsonPath('data.0.preview', null);
    }

    private function postPreview(array $urls): TestResponse
    {
        return $this->actingAs($this->user, 'sanctum')
            ->withHeaders([
                'X-Frontend-Domain' => $this->domain,
                'X-Device-Id' => Str::uuid(),
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0',
            ])
            ->postJson('/api/link-previews', ['urls' => $urls]);
    }

    private function publicIp(): string
    {
        return self::PUBLIC_IP;
    }

    /**
     * @param  array<string, string>  $overrides
     */
    private function htmlPage(array $overrides = []): string
    {
        $tags = array_merge([
            'og:title' => 'The Complete Media Buying Guide',
            'og:description' => 'How to buy media profitably.',
            'og:image' => "https://{$this->publicIp()}/images/cover.png",
            'og:site_name' => 'Kursa Insights',
            'og:type' => 'article',
        ], array_diff_key($overrides, ['favicon' => true]));

        $meta = '';

        foreach ($tags as $property => $content) {
            $meta .= '<meta property="'.e($property).'" content="'.e($content).'">';
        }

        $favicon = $overrides['favicon'] ?? '/favicon.ico';

        return '<!doctype html><html><head>'.$meta
            .'<link rel="icon" href="'.e($favicon).'">'
            .'<title>Fallback title</title>'
            .'</head><body><h1>Guide</h1></body></html>';
    }
}
