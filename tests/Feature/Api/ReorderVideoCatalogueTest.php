<?php

namespace Tests\Feature\Api;

use App\Models\Environment;
use App\Models\User;
use App\Models\VideoContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReorderVideoCatalogueTest extends TestCase
{
    use RefreshDatabase;

    private function catalogue(User $owner, ?Environment $environment = null): array
    {
        $template = DB::table('templates')->insertGetId([
            'title' => 'Video course', 'status' => 'published', 'is_public' => true,
            'created_by' => $owner->id, 'environment_id' => $environment?->id,
        ]);
        $block = DB::table('blocks')->insertGetId([
            'title' => 'Block', 'template_id' => $template, 'order' => 0,
            'status' => 'active', 'created_by' => $owner->id,
        ]);
        $activity = DB::table('activities')->insertGetId([
            'title' => 'Catalogue', 'block_id' => $block, 'type' => 'video',
            'order' => 0, 'status' => 'active', 'created_by' => $owner->id,
        ]);
        $videos = collect(['First', 'Second', 'Third'])->map(fn ($title, $index) => VideoContent::create([
            'activity_id' => $activity, 'title' => $title, 'video_type' => 'youtube',
            'video_url' => 'https://www.youtube.com/watch?v=EfIdGVxnQ78',
            'sort_order' => $index, 'created_by' => $owner->id,
        ]));

        return [$activity, new \Illuminate\Database\Eloquent\Collection($videos->all())];
    }

    public function test_owner_can_save_order_without_changing_video_metadata(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        [$activity, $items] = $this->catalogue($owner);
        $order = [$items[2]->id, $items[0]->id, $items[1]->id];

        $this->putJson("/api/activities/$activity/video/order", [
            'video_ids' => $order,
            'previous_video_ids' => $items->modelKeys(),
        ])->assertOk()->assertJsonPath('video_ids.0', $order[0]);

        $this->getJson("/api/activities/$activity/video")
            ->assertOk()->assertJsonPath('videos.0.title', 'Third')
            ->assertJsonPath('videos.1.title', 'First')
            ->assertJsonPath('videos.2.title', 'Second');
        $this->assertSame(0, $items[2]->fresh()->sort_order);
    }

    public function test_invalid_or_stale_order_is_rejected_without_writes(): void
    {
        $owner = User::factory()->create();
        Sanctum::actingAs($owner);
        [$activity, $items] = $this->catalogue($owner);
        [, $otherItems] = $this->catalogue($owner);
        $current = $items->modelKeys();
        $uri = "/api/activities/$activity/video/order";

        $this->putJson($uri, ['video_ids' => [$items[2]->id, $items[1]->id, $otherItems[0]->id], 'previous_video_ids' => $current])->assertConflict();
        $this->putJson($uri, ['video_ids' => array_reverse($current), 'previous_video_ids' => array_reverse($current)])->assertConflict();
        $this->putJson($uri, ['video_ids' => [$items[2]->id, $items[2]->id, $items[1]->id], 'previous_video_ids' => $current])->assertUnprocessable();
        $this->assertSame([0, 1, 2], $items->fresh()->pluck('sort_order')->all());
        $this->assertSame([0, 1, 2], $otherItems->fresh()->pluck('sort_order')->all());
    }

    public function test_owner_must_reorder_in_the_academy_that_owns_the_template(): void
    {
        $owner = User::factory()->create();
        $rightAcademy = Environment::factory()->create(['primary_domain' => 'right-academy.example']);
        Environment::factory()->create(['primary_domain' => 'other-academy.example']);
        [$activity, $items] = $this->catalogue($owner, $rightAcademy);
        Sanctum::actingAs($owner);
        $request = ['video_ids' => array_reverse($items->modelKeys()), 'previous_video_ids' => $items->modelKeys()];

        $this->putJson("/api/activities/$activity/video/order", $request, ['X-Frontend-Domain' => 'other-academy.example'])
            ->assertForbidden();
        $this->putJson("/api/activities/$activity/video/order", $request, ['X-Frontend-Domain' => 'right-academy.example'])
            ->assertOk();
    }

    public function test_public_catalogue_cannot_be_reordered_by_another_user(): void
    {
        $owner = User::factory()->create();
        [$activity, $items] = $this->catalogue($owner);
        Sanctum::actingAs(User::factory()->create());

        $this->putJson("/api/activities/$activity/video/order", [
            'video_ids' => $items->modelKeys(),
            'previous_video_ids' => $items->modelKeys(),
        ])->assertForbidden();
    }
}
