<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Plan;
use App\Models\User;
use Database\Seeders\EnvironmentUsersSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionDatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_production_seeding_does_not_provision_users_and_keeps_reference_data(): void
    {
        $existing = User::factory()->create(['role' => UserRole::SUPER_ADMIN]);
        $original = $existing->fresh()->getAttributes();
        $this->app->instance('env', 'production');

        $this->artisan('db:seed', ['--force' => true])->assertSuccessful();

        $this->assertSame(1, User::query()->count());
        $this->assertSame($original, $existing->fresh()->getAttributes());
        $this->assertTrue(Plan::query()->where('type', 'personal_free')->exists());
    }

    public function test_demo_user_seeder_is_inert_when_called_directly_in_production(): void
    {
        $this->app->instance('env', 'production');

        $this->artisan('db:seed', ['--class' => EnvironmentUsersSeeder::class, '--force' => true])->assertSuccessful();

        $this->assertSame(0, User::query()->count());
    }
}
