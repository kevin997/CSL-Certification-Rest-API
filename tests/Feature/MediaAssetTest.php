<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\MediaAsset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MediaAssetTest extends TestCase
{
    use RefreshDatabase;

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
            ->assertJsonStructure(['id', 'upload_payload']);

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
        $user = User::factory()->create();
        $mediaAsset = MediaAsset::create([
            'environment_id' => 1,
            'owner_user_id' => $user->id,
            'title' => 'Test Audio',
            'type' => 'audio',
            'status' => 'ready',
            'meta' => ['upload_id' => '12345'],
        ]);

        $response = $this->actingAs($user)->getJson("/api/media/playback/{$mediaAsset->id}");

        $response->assertStatus(200)
            ->assertJsonStructure(['stream_url', 'token']);
    }
}
