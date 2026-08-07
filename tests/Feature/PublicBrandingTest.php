<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Branding;
use App\Models\Environment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicBrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_anonymous_read_returns_environment_branding_by_domain(): void
    {
        $owner = User::factory()->create();
        $environment = Environment::factory()->create([
            'owner_id' => $owner->id,
            'primary_domain' => 'academy.example.com',
            'is_active' => true,
        ]);

        Branding::factory()->create([
            'user_id' => $owner->id,
            'environment_id' => $environment->id,
            'company_name' => 'My Academy',
            'primary_color' => '#e41b1c',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/branding/public?domain=academy.example.com');

        $response->assertOk()
            ->assertJsonPath('data.company_name', 'My Academy')
            ->assertJsonPath('data.primary_color', '#e41b1c')
            ->assertJsonPath('data.environment_id', $environment->id)
            ->assertJsonPath('environment.id', $environment->id);
    }

    public function test_anonymous_read_returns_latest_saved_row_when_duplicates_exist(): void
    {
        $owner = User::factory()->create();
        $environment = Environment::factory()->create([
            'owner_id' => $owner->id,
            'primary_domain' => 'dupes.example.com',
            'is_active' => true,
        ]);

        // Older stale default-valued row (the bug: this one used to win).
        $stale = Branding::factory()->create([
            'user_id' => $owner->id,
            'environment_id' => $environment->id,
            'company_name' => 'CSL',
            'primary_color' => '#0db002',
            'is_active' => true,
        ]);
        $stale->forceFill([
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ])->saveQuietly();

        // The row the owner actually saved most recently.
        Branding::factory()->create([
            'user_id' => $owner->id,
            'environment_id' => $environment->id,
            'company_name' => 'Real Academy',
            'primary_color' => '#e41b1c',
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/branding/public?domain=dupes.example.com');

        $response->assertOk()
            ->assertJsonPath('data.company_name', 'Real Academy')
            ->assertJsonPath('data.primary_color', '#e41b1c');
    }

    public function test_unknown_domain_returns_default_branding_not_error(): void
    {
        $response = $this->getJson('/api/branding/public?domain=nobody.example.com');

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.company_name', 'CSL')
            ->assertJsonPath('data.primary_color', '#0db002')
            ->assertJsonPath('data.environment_id', null);
    }

    public function test_owner_can_upsert_environment_branding(): void
    {
        $owner = User::factory()->create(['role' => UserRole::INDIVIDUAL_TEACHER]);
        $environment = Environment::factory()->create([
            'owner_id' => $owner->id,
            'primary_domain' => 'owned.example.com',
            'is_active' => true,
        ]);

        $response = $this->actingAs($owner)->putJson("/api/environments/{$environment->id}/branding", [
            'company_name' => 'Owned Academy',
            'primary_color' => '#123abc',
        ]);

        $response->assertOk()->assertJsonPath('data.company_name', 'Owned Academy');

        $this->assertDatabaseHas('brandings', [
            'environment_id' => $environment->id,
            'company_name' => 'Owned Academy',
            'primary_color' => '#123abc',
        ]);
    }

    public function test_non_owner_teacher_cannot_upsert_another_environments_branding(): void
    {
        $owner = User::factory()->create(['role' => UserRole::INDIVIDUAL_TEACHER]);
        $environment = Environment::factory()->create([
            'owner_id' => $owner->id,
            'primary_domain' => 'victim.example.com',
            'is_active' => true,
        ]);

        $intruder = User::factory()->create(['role' => UserRole::INDIVIDUAL_TEACHER]);

        $response = $this->actingAs($intruder)->putJson("/api/environments/{$environment->id}/branding", [
            'company_name' => 'Hijacked',
        ]);

        $response->assertForbidden();

        $this->assertDatabaseMissing('brandings', [
            'environment_id' => $environment->id,
            'company_name' => 'Hijacked',
        ]);
    }

    public function test_upsert_updates_existing_row_and_soft_deletes_duplicates(): void
    {
        $owner = User::factory()->create(['role' => UserRole::INDIVIDUAL_TEACHER]);
        $environment = Environment::factory()->create([
            'owner_id' => $owner->id,
            'primary_domain' => 'healed.example.com',
            'is_active' => true,
        ]);

        $older = Branding::factory()->create([
            'user_id' => $owner->id,
            'environment_id' => $environment->id,
            'company_name' => 'Stale Default',
        ]);
        $older->forceFill([
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ])->saveQuietly();

        $newer = Branding::factory()->create([
            'user_id' => $owner->id,
            'environment_id' => $environment->id,
            'company_name' => 'Current',
        ]);

        $response = $this->actingAs($owner)->putJson("/api/environments/{$environment->id}/branding", [
            'company_name' => 'Updated Academy',
        ]);

        $response->assertOk();

        // The most recent row was updated in place, no new row created.
        $this->assertSame('Updated Academy', $newer->fresh()->company_name);

        // The stale duplicate was soft-deleted.
        $this->assertSoftDeleted('brandings', ['id' => $older->id]);

        // Exactly one live row remains for the environment.
        $this->assertSame(1, Branding::withoutGlobalScopes()
            ->where('environment_id', $environment->id)
            ->whereNull('deleted_at')
            ->count());

        // And the public read now serves the updated branding.
        $this->getJson('/api/branding/public?domain=healed.example.com')
            ->assertOk()
            ->assertJsonPath('data.company_name', 'Updated Academy');
    }

    public function test_upsert_rejects_invalid_colors_and_urls(): void
    {
        $owner = User::factory()->create(['role' => UserRole::INDIVIDUAL_TEACHER]);
        $environment = Environment::factory()->create([
            'owner_id' => $owner->id,
            'is_active' => true,
        ]);

        $response = $this->actingAs($owner)->putJson("/api/environments/{$environment->id}/branding", [
            'company_name' => 'Bad Input Academy',
            'primary_color' => 'nothex1',
            'logo_url' => 'not-a-url',
        ]);

        $response->assertUnprocessable()
            ->assertJsonStructure(['errors' => ['primary_color', 'logo_url']]);
    }
}
