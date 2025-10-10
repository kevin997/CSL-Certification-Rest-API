<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EnvironmentPaymentConfig>
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
            'environment_id' => \App\Models\Environment::factory(),
            'use_centralized_gateways' => false,
            'commission_rate' => 0.1500,
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
