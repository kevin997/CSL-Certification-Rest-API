<?php

namespace Database\Factories;

use App\Models\Environment;
use App\Models\EnvironmentPaymentConfig;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EnvironmentPaymentConfig>
 */
class EnvironmentPaymentConfigFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'environment_id' => Environment::factory(),
            'use_centralized_gateways' => false,
            // Renamed from commission_rate by
            // 2025_10_09_193400_rename_commission_to_platform_fee_in_environment_payment_configs.
            'platform_fee_rate' => 0.1500,
            'payment_terms' => fake()->randomElement(['NET_30', 'NET_60', 'Immediate']),
            'withdrawal_method' => fake()->randomElement(['bank_transfer', 'paypal', 'mobile_money', null]),
            'withdrawal_details' => fake()->boolean(50) ? [
                'account_name' => fake()->name(),
                'account_number' => fake()->numerify('##########'),
                'bank_name' => fake()->company(),
            ] : null,
            'minimum_withdrawal_amount' => fake()->randomFloat(2, 10000, 100000),
            'is_active' => true,
        ];
    }
}
