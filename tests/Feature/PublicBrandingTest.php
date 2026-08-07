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

    /**
     * The application self-heals duplicates on save, but that is a convention
     * a future code path could bypass. These two assert the database itself
     * refuses, which is what actually made the KURSA-green bug possible.
     */
    public function test_the_database_rejects_a_second_active_branding_row(): void
    {
        $owner = User::factory()->create();
        $environment = Environment::factory()->create(['owner_id' => $owner->id]);

        Branding::factory()->create([
            'user_id' => $owner->id,
            'environment_id' => $environment->id,
            'company_name' => 'First',
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Branding::factory()->create([
            'user_id' => $owner->id,
            'environment_id' => $environment->id,
            'company_name' => 'Second',
        ]);
    }

    public function test_a_soft_deleted_row_does_not_block_a_new_active_one(): void
    {
        $owner = User::factory()->create();
        $environment = Environment::factory()->create(['owner_id' => $owner->id]);

        $superseded = Branding::factory()->create([
            'user_id' => $owner->id,
            'environment_id' => $environment->id,
            'company_name' => 'Old',
        ]);
        $superseded->delete();

        $fresh = Branding::factory()->create([
            'user_id' => $owner->id,
            'environment_id' => $environment->id,
            'company_name' => 'New',
        ]);

        $this->assertSoftDeleted($superseded);
        $this->assertDatabaseHas('brandings', ['id' => $fresh->id, 'deleted_at' => null]);
    }

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

    public function test_anonymous_read_ignores_superseded_rows_and_returns_the_live_one(): void
    {
        $owner = User::factory()->create();
        $environment = Environment::factory()->create([
            'owner_id' => $owner->id,
            'primary_domain' => 'dupes.example.com',
            'is_active' => true,
        ]);

        // The stale default-valued row that used to win the unordered read.
        // A second ACTIVE row is now refused by the database, so this models
        // the only shape that can still exist: superseded, i.e. soft-deleted.
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
        $stale->delete();

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

    public function test_upsert_updates_the_existing_row_in_place(): void
    {
        $owner = User::factory()->create(['role' => UserRole::INDIVIDUAL_TEACHER]);
        $environment = Environment::factory()->create([
            'owner_id' => $owner->id,
            'primary_domain' => 'healed.example.com',
            'is_active' => true,
        ]);

        // Only one active row can exist now that the database enforces it, so
        // this asserts the guarantee that still matters: upsert updates in
        // place and never adds a second row.
        $existing = Branding::factory()->create([
            'user_id' => $owner->id,
            'environment_id' => $environment->id,
            'company_name' => 'Current',
        ]);

        $response = $this->actingAs($owner)->putJson("/api/environments/{$environment->id}/branding", [
            'company_name' => 'Updated Academy',
        ]);

        $response->assertOk();

        // Updated in place, no new row created.
        $this->assertSame('Updated Academy', $existing->fresh()->company_name);

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
