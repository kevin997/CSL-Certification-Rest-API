<?php

namespace Database\Seeders;

use App\Models\Environment;
use App\Models\EnvironmentPaymentConfig;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class EnvironmentPaymentConfigSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Seeds default EnvironmentPaymentConfig for all existing environments.
     */
    public function run(): void
    {
        // Get all environments
        $environments = Environment::all();

        foreach ($environments as $environment) {
            // Check if config already exists
            $exists = EnvironmentPaymentConfig::where('environment_id', $environment->id)->exists();

            if (!$exists) {
                EnvironmentPaymentConfig::create([
                    'environment_id' => $environment->id,
                    'use_centralized_gateways' => false,
                    'platform_fee_rate' => 0.1700, // Platform fee: 17% (instructor receives 83%)
                    'payment_terms' => 'NET_30',
                    'withdrawal_method' => null,
                    'withdrawal_details' => null,
                    'minimum_withdrawal_amount' => 82.00, // $82 USD (≈50,000 XAF)
                    'is_active' => true,
                ]);

                $this->command->info("Created payment config for environment: {$environment->name} (ID: {$environment->id})");
            } else {
                $this->command->info("Payment config already exists for environment: {$environment->name} (ID: {$environment->id})");
            }
        }

        $this->command->info('Environment payment config seeding completed.');
    }
}
