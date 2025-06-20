<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\PlanSeeder;
use Database\Seeders\DemoPlanSeeder;
use Database\Seeders\EnvironmentUsersSeeder;
use Database\Seeders\ThirdPartyServiceSeeder;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Call the admin seeder
        $this->call([
            AdminSeeder::class,
            PlanSeeder::class,
            DemoPlanSeeder::class,
            EnvironmentUsersSeeder::class,
            ThirdPartyServiceSeeder::class,
            TaxZoneSeeder::class,
        ]);

        // Development test user
        if (app()->environment('local', 'development')) {
            User::firstOrCreate(
                ['email' => 'test@example.com'],
                ['name' => 'Test User', 'password' => 'password']
            );
        }
    }
}
