<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Branding;

class LandingPageTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    use RefreshDatabase;

    public function test_can_get_landing_page_config()
    {
        $user = User::factory()->create();
        $branding = Branding::factory()->create(['environment_id' => $user->environment_id]);

        $response = $this->actingAs($user)->getJson("/api/branding/{$branding->id}/landing-page");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'landing_page_enabled',
                'hero_title',
                'hero_subtitle',
                'hero_background_image',
                'hero_overlay_color',
                'hero_overlay_opacity',
                'hero_cta_text',
                'hero_cta_url',
                'landing_page_sections',
                'seo_title',
                'seo_description',
            ]);
    }

    public function test_can_update_landing_page_config()
    {
        $user = User::factory()->create();
        $branding = Branding::factory()->create(['environment_id' => $user->environment_id]);

        $data = [
            'landing_page_enabled' => true,
            'hero_title' => 'New Hero Title',
            'hero_subtitle' => 'New Subtitle',
            'hero_overlay_opacity' => 80,
        ];

        $response = $this->actingAs($user)->putJson("/api/branding/{$branding->id}/landing-page", $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('brandings', [
            'id' => $branding->id,
            'landing_page_enabled' => true,
            'hero_title' => 'New Hero Title',
            'hero_subtitle' => 'New Subtitle',
            'hero_overlay_opacity' => 80,
        ]);
    }

    public function test_can_toggle_landing_page()
    {
        $user = User::factory()->create();
        $branding = Branding::factory()->create(['environment_id' => $user->environment_id, 'landing_page_enabled' => false]);

        $response = $this->actingAs($user)->postJson("/api/branding/{$branding->id}/landing-page/toggle", ['enabled' => true]);

        $response->assertStatus(200)
            ->assertJson(['landing_page_enabled' => true]);

        $this->assertDatabaseHas('brandings', [
            'id' => $branding->id,
            'landing_page_enabled' => true,
        ]);
    }

    public function test_cannot_access_other_environment_branding()
    {
        $user = User::factory()->create(['environment_id' => 1]);
        $otherBranding = Branding::factory()->create(['environment_id' => 2]);

        $response = $this->actingAs($user)->getJson("/api/branding/{$otherBranding->id}/landing-page");

        $response->assertStatus(403);
    }
}
