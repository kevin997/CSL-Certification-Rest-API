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
            '*/uploads/12345/complete' => Http::response(['status' => 'processing'], 200),
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

}
