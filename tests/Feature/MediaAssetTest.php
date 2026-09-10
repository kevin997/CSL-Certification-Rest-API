<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class MediaAssetTest extends TestCase
{
    use RefreshDatabase;

    private const MEDIA = 'http://media.test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.media_service.url' => self::MEDIA,
            'services.media_service.secret' => 'webhook-secret',
            // The webhook broadcasts; the test environment has no Reverb to reach.
            'broadcasting.default' => 'null',
        ]);
    }

    private function asset(User $user, array $overrides = []): MediaAsset
    {
        return MediaAsset::create(array_merge([
            'environment_id' => 1,
            'owner_user_id' => $user->id,
            'title' => 'Lecture',
            'type' => 'video',
            'status' => 'processing',
            'meta' => ['upload_id' => (string) Str::uuid()],
        ], $overrides));
    }

    /**
     * The media service recomputes this over the exact URI and body it received.
     * Asserting it here is what keeps the two sides from drifting apart: a
     * mismatch there is a 401, and a 401 there is an upload that never starts.
     */
    private function assertSignedWith(ClientRequest $request, string $method, string $uri): bool
    {
        $timestamp = $request->header('X-Media-Service-Timestamp')[0] ?? '';
        $canonical = implode("\n", [$method, $uri, $timestamp, hash('sha256', $request->body())]);

        return hash_equals(
            hash_hmac('sha256', $canonical, 'webhook-secret'),
            $request->header('X-Media-Service-Signature')[0] ?? ''
        );
    }

    public function test_destroy_signs_and_tenant_scopes_the_media_service_call()
    {
        // The media service refuses an unsigned or unscoped delete. If this
        // contract drifts, deletion silently stops freeing storage — and the
        // only symptom is a bucket that grows forever.
        Http::fake([self::MEDIA . '/*' => Http::response([], 200)]);
        $user = User::factory()->create(); // environment_id defaults to 1
        $asset = $this->asset($user, ['status' => 'ready']);
        $uploadId = $asset->meta['upload_id'];

        $this->actingAs($user)->deleteJson("/api/media/{$asset->id}")->assertStatus(200);

        Http::assertSent(function (ClientRequest $request) use ($uploadId) {
            $expectedUri = "/api/media/{$uploadId}?environment_id=1";
            if ($request->method() !== 'DELETE' || !str_ends_with($request->url(), $expectedUri)) {
                return false;
            }

            $timestamp = $request->header('X-Media-Service-Timestamp')[0] ?? '';
            $canonical = implode("\n", ['DELETE', $expectedUri, $timestamp, hash('sha256', '')]);

            return hash_equals(
                hash_hmac('sha256', $canonical, 'webhook-secret'),
                $request->header('X-Media-Service-Signature')[0] ?? ''
            );
        });
    }

    public function test_init_upload_creates_media_asset()
    {
        Http::fake([
            '*/uploads/init' => Http::response(['upload_id' => '12345', 'upload_url' => 'http://test/upload'], 200),
        ]);

        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson('/api/media/upload/init', [
            'file_name' => 'test.mp3',
            'file_size' => 1024,
            'mime_type' => 'audio/mpeg',
            'title' => 'Test Audio',
            'type' => 'audio',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['media_asset' => ['id', 'status'], 'upload_id', 'upload_url']);

        $this->assertDatabaseHas('media_assets', [
            'title' => 'Test Audio',
            'status' => 'pending',
        ]);
    }

    public function test_complete_upload_updates_status()
    {
        Http::fake([
            '*/uploads/12345/complete*' => Http::response(['status' => 'processing'], 200),
        ]);

        $user = User::factory()->create();
        $mediaAsset = MediaAsset::create([
            'environment_id' => 1,
            'owner_user_id' => $user->id,
            'title' => 'Test Audio',
            'type' => 'audio',
            'status' => 'pending',
            'meta' => ['upload_id' => '12345'],
        ]);

        $response = $this->actingAs($user)->postJson("/api/media/upload/{$mediaAsset->id}/complete");

        $response->assertStatus(200);
        $this->assertDatabaseHas('media_assets', [
            'id' => $mediaAsset->id,
            'status' => 'processing',
        ]);
    }

    public function test_playback_session_returns_url()
    {
        Http::fake([
            '*/playback-session' => Http::response([
                'token' => 'jwt',
                'manifest_url' => self::MEDIA . '/api/media/hls/abc/master.m3u8?token=jwt',
                'type' => 'video',
            ]),
        ]);

        $user = User::factory()->create();
        $mediaAsset = $this->asset($user, ['status' => 'ready']);

        $this->actingAs($user)->getJson("/api/media/playback/{$mediaAsset->id}")
            ->assertStatus(200)
            ->assertJsonStructure(['stream_url', 'token'])
            ->assertJsonPath('type', 'video');
    }

    public function test_playback_session_reports_a_video_still_transcoding_with_a_stable_code()
    {
        Http::fake([
            '*/playback-session' => Http::response([
                'error' => 'Media not ready',
                'code' => 'media_not_ready',
                'status' => 'processing',
            ], 409),
        ]);

        $user = User::factory()->create();
        $mediaAsset = $this->asset($user);

        $this->actingAs($user)->getJson("/api/media/playback/{$mediaAsset->id}")
            ->assertStatus(409)
            ->assertJson(['code' => 'media_not_ready', 'status' => 'processing']);

        $this->assertSame('processing', $mediaAsset->fresh()->status);
    }

    public function test_playback_session_understands_an_older_media_service_that_only_sends_text()
    {
        Http::fake([
            '*/playback-session' => Http::response(['error' => 'Media not ready'], 400),
        ]);

        $user = User::factory()->create();
        $mediaAsset = $this->asset($user);

        $this->actingAs($user)->getJson("/api/media/playback/{$mediaAsset->id}")
            ->assertStatus(409)
            ->assertJson(['code' => 'media_not_ready', 'status' => 'processing']);
    }

    public function test_playback_session_catches_up_a_ready_video_whose_webhook_was_lost()
    {
        Http::fake([
            '*/playback-session' => Http::response(['token' => 'jwt', 'manifest_url' => 'http://media.test/m.m3u8']),
        ]);

        $user = User::factory()->create();
        $mediaAsset = $this->asset($user);

        $this->actingAs($user)->getJson("/api/media/playback/{$mediaAsset->id}")->assertStatus(200);

        $this->assertSame('ready', $mediaAsset->fresh()->status);
    }

    public function test_playback_session_reports_a_failed_video_without_asking_the_media_service()
    {
        Http::fake();

        $user = User::factory()->create();
        $mediaAsset = $this->asset($user, [
            'status' => 'failed',
            'meta' => ['upload_id' => (string) Str::uuid(), 'processing_meta' => ['error' => 'Broken video']],
        ]);

        $this->actingAs($user)->getJson("/api/media/playback/{$mediaAsset->id}")
            ->assertStatus(409)
            ->assertJson(['code' => 'media_failed', 'status' => 'failed', 'reason' => 'Broken video']);

        Http::assertNothingSent();
    }

    public function test_playback_session_learns_a_failure_the_webhook_never_delivered()
    {
        Http::fake([
            '*/playback-session' => Http::response([
                'error' => 'Media not ready',
                'code' => 'media_failed',
                'status' => 'failed',
                'reason' => 'Assembled size mismatch (incomplete upload)',
            ], 409),
        ]);

        $user = User::factory()->create();
        $mediaAsset = $this->asset($user);

        $this->actingAs($user)->getJson("/api/media/playback/{$mediaAsset->id}")
            ->assertStatus(409)
            ->assertJson(['code' => 'media_failed', 'status' => 'failed', 'reason' => 'Assembled size mismatch (incomplete upload)']);

        $mediaAsset->refresh();
        $this->assertSame('failed', $mediaAsset->status);
        $this->assertSame('Assembled size mismatch (incomplete upload)', $mediaAsset->meta['processing_meta']['error']);
    }

    public function test_playback_session_still_relays_unrelated_media_service_errors()
    {
        Http::fake([
            '*/playback-session' => Http::response(['error' => 'Media not found'], 404),
        ]);

        $user = User::factory()->create();
        $mediaAsset = $this->asset($user);

        $this->actingAs($user)->getJson("/api/media/playback/{$mediaAsset->id}")
            ->assertStatus(404)
            ->assertJson(['error' => 'Failed to start playback session']);
    }

    public function test_processing_webhook_records_the_duration_the_transcoder_probed()
    {
        $user = User::factory()->create();
        $uploadId = (string) Str::uuid();
        $mediaAsset = $this->asset($user, ['meta' => ['upload_id' => $uploadId]]);

        $body = json_encode([
            'upload_id' => $uploadId,
            'status' => 'ready',
            'processing_meta' => ['subatic_status' => 'DONE', 'duration' => 186],
        ]);

        $this->call('POST', '/api/webhooks/media/processing', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_MEDIA_SERVICE_SIGNATURE' => hash_hmac('sha256', $body, 'webhook-secret'),
        ], $body)->assertOk();

        $mediaAsset->refresh();
        $this->assertSame('ready', $mediaAsset->status);
        $this->assertSame(186, $mediaAsset->duration);
    }

    public function test_abort_multipart_drops_the_pending_asset_and_its_parts()
    {
        Http::fake(['*/multipart/*/abort*' => Http::response(['status' => 'failed'])]);

        $user = User::factory()->create();
        $uploadId = (string) Str::uuid();
        $mediaAsset = $this->asset($user, ['status' => 'pending', 'meta' => ['upload_id' => $uploadId, 'multipart' => true]]);

        $this->actingAs($user)->postJson("/api/media/upload/multipart/{$mediaAsset->id}/abort")
            ->assertOk()
            ->assertJson(['deleted' => true]);

        $this->assertDatabaseMissing('media_assets', ['id' => $mediaAsset->id]);
        // Signed and tenant-scoped: the media service refuses it otherwise.
        Http::assertSent(function (ClientRequest $request) use ($uploadId) {
            $uri = "/api/media/multipart/{$uploadId}/abort?environment_id=1";
            $timestamp = $request->header('X-Media-Service-Timestamp')[0] ?? '';
            $canonical = implode("\n", ['POST', $uri, $timestamp, hash('sha256', '')]);

            return $request->url() === self::MEDIA . $uri
                && hash_equals(hash_hmac('sha256', $canonical, 'webhook-secret'), $request->header('X-Media-Service-Signature')[0] ?? '');
        });
    }

    public function test_abort_multipart_refuses_an_upload_that_already_completed()
    {
        Http::fake();

        $user = User::factory()->create();
        $mediaAsset = $this->asset($user, ['status' => 'processing']);

        $this->actingAs($user)->postJson("/api/media/upload/multipart/{$mediaAsset->id}/abort")
            ->assertStatus(409)
            ->assertJson(['code' => 'upload_not_pending', 'status' => 'processing']);

        $this->assertDatabaseHas('media_assets', ['id' => $mediaAsset->id]);
        Http::assertNothingSent();
    }

    public function test_abort_multipart_does_not_see_another_environments_asset()
    {
        Http::fake();

        $user = User::factory()->create();
        $mediaAsset = $this->asset($user, ['status' => 'pending', 'environment_id' => 2]);

        $this->actingAs($user)->postJson("/api/media/upload/multipart/{$mediaAsset->id}/abort")
            ->assertStatus(404);

        $this->assertDatabaseHas('media_assets', ['id' => $mediaAsset->id]);
    }

    public function test_init_upload_signs_the_media_service_call()
    {
        // Unsigned, this route let anyone create an upload row in any tenant and
        // hand the transcoder work. The media service answers 401 now, so a
        // missing signature here means audio upload stops working entirely.
        Http::fake(['*/uploads/init' => Http::response(['upload_id' => '12345', 'upload_url' => 'http://media.test/u'], 200)]);

        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/media/upload/init', [
            'file_name' => 'test.mp3',
            'file_size' => 1024,
            'mime_type' => 'audio/mpeg',
            'title' => 'Test Audio',
            'type' => 'audio',
        ])->assertStatus(200);

        Http::assertSent(fn (ClientRequest $r) => $r->url() === self::MEDIA . '/api/media/uploads/init'
            && $this->assertSignedWith($r, 'POST', '/api/media/uploads/init'));
    }

    public function test_init_multipart_upload_signs_the_media_service_call()
    {
        Http::fake(['*/multipart/init' => Http::response([
            'upload_id' => 'abc', 'key' => 'uploads/abc', 's3_upload_id' => 'S3',
            'part_size' => 16, 'part_count' => 1, 'bucket' => 'media-raw',
        ], 200)]);

        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/media/upload/multipart/init', [
            'file_name' => 'lecture.mp4',
            'file_size' => 16,
            'mime_type' => 'video/mp4',
            'title' => 'Lecture',
            'type' => 'video',
        ])->assertStatus(200);

        Http::assertSent(fn (ClientRequest $r) => $r->url() === self::MEDIA . '/api/media/multipart/init'
            && $this->assertSignedWith($r, 'POST', '/api/media/multipart/init'));
    }

    public function test_complete_upload_signs_and_tenant_scopes_the_media_service_call()
    {
        Http::fake(['*/uploads/*/complete*' => Http::response(['status' => 'processing'], 200)]);

        $user = User::factory()->create();
        $asset = $this->asset($user, ['status' => 'pending', 'meta' => ['upload_id' => 'aud-1']]);

        $this->actingAs($user)->postJson("/api/media/upload/{$asset->id}/complete")->assertStatus(200);

        Http::assertSent(function (ClientRequest $r) {
            $uri = '/api/media/uploads/aud-1/complete?environment_id=1';

            return $r->url() === self::MEDIA . $uri && $this->assertSignedWith($r, 'POST', $uri);
        });
    }

    public function test_complete_multipart_signs_and_tenant_scopes_the_media_service_call()
    {
        Http::fake(['*/multipart/*/complete*' => Http::response(['status' => 'processing'], 200)]);

        $user = User::factory()->create();
        $asset = $this->asset($user, ['status' => 'pending', 'meta' => ['upload_id' => 'vid-1', 'multipart' => true]]);

        $this->actingAs($user)->postJson("/api/media/upload/multipart/{$asset->id}/complete", [
            'parts' => [['part_number' => 1, 'etag' => '"abc"']],
        ])->assertStatus(200);

        Http::assertSent(function (ClientRequest $r) {
            $uri = '/api/media/multipart/vid-1/complete?environment_id=1';

            return $r->url() === self::MEDIA . $uri
                && $r->body() === json_encode(['parts' => [['part_number' => 1, 'etag' => '"abc"']]])
                && $this->assertSignedWith($r, 'POST', $uri);
        });
    }

    public function test_complete_upload_does_not_see_another_environments_asset()
    {
        Http::fake();

        $user = User::factory()->create();
        $asset = $this->asset($user, ['status' => 'pending', 'environment_id' => 2]);

        $this->actingAs($user)->postJson("/api/media/upload/{$asset->id}/complete")->assertStatus(404);

        Http::assertNothingSent();
        $this->assertSame('pending', $asset->fresh()->status);
    }

    public function test_complete_multipart_does_not_see_another_environments_asset()
    {
        Http::fake();

        $user = User::factory()->create();
        $asset = $this->asset($user, ['status' => 'pending', 'environment_id' => 2, 'meta' => ['upload_id' => 'vid-2']]);

        $this->actingAs($user)->postJson("/api/media/upload/multipart/{$asset->id}/complete", [
            'parts' => [['part_number' => 1, 'etag' => '"abc"']],
        ])->assertStatus(404);

        Http::assertNothingSent();
        $this->assertSame('pending', $asset->fresh()->status);
    }
}
