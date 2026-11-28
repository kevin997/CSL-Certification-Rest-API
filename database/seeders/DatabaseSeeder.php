<?php

namespace Database\Seeders;

use App\Models\User;
use Database\Seeders\PlanSeeder;
use Database\Seeders\DemoPlanSeeder;
use Database\Seeders\EnvironmentUsersSeeder;
use Database\Seeders\ThirdPartyServiceSeeder;
use Database\Seeders\AfricanTaxZoneSeeder;
use Database\Seeders\TaxZoneSeeder;
use Database\Seeders\EnvironmentPaymentConfigSeeder;
use Database\Seeders\TaraMoneyGatewaySeeder;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Startup and restores seed reference data, never production accounts.
        $this->call([
            PlanSeeder::class,
            DemoPlanSeeder::class,
            ThirdPartyServiceSeeder::class,
            TaxZoneSeeder::class,
            AfricanTaxZoneSeeder::class,
            EnvironmentPaymentConfigSeeder::class,
            TaraMoneyGatewaySeeder::class
        ]);

        // Development test user
        if (app()->environment('local', 'development')) {
            $this->call(EnvironmentUsersSeeder::class);

            User::firstOrCreate(
                ['email' => 'test@example.com'],
                ['name' => 'Test User', 'password' => 'password']
            );
        }
    }
}
