<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\WithdrawalRequest>
 */
class WithdrawalRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $withdrawalMethod = fake()->randomElement(['bank_transfer', 'paypal', 'mobile_money']);
        $withdrawalDetails = match ($withdrawalMethod) {
            'bank_transfer' => [
                'bank_name' => fake()->company() . ' Bank',
                'account_number' => fake()->numerify('##########'),
                'account_name' => fake()->name(),
                'swift_code' => fake()->bothify('????##???'),
            ],
            'paypal' => [
                'email' => fake()->safeEmail(),
            ],
            'mobile_money' => [
                'phone_number' => fake()->phoneNumber(),
                'provider' => fake()->randomElement(['MTN', 'Orange', 'Airtel']),
            ],
        };

        return [
            'environment_id' => \App\Models\Environment::factory(),
            'requested_by' => \App\Models\User::factory(),
            'amount' => fake()->randomFloat(2, 50000, 500000),
            'currency' => 'XAF',
            'status' => fake()->randomElement(['pending', 'approved', 'processing', 'completed', 'rejected']),
            'withdrawal_method' => $withdrawalMethod,
            'withdrawal_details' => $withdrawalDetails,
            'commission_ids' => fake()->boolean(70) ? [1, 2, 3] : null,
            'approved_by' => fake()->boolean(50) ? \App\Models\User::factory() : null,
            'approved_at' => fake()->boolean(50) ? fake()->dateTimeBetween('-15 days', 'now') : null,
            'processed_by' => fake()->boolean(30) ? \App\Models\User::factory() : null,
            'processed_at' => fake()->boolean(30) ? fake()->dateTimeBetween('-10 days', 'now') : null,
            'payment_reference' => fake()->boolean(30) ? fake()->uuid() : null,
            'rejection_reason' => fake()->boolean(10) ? fake()->sentence() : null,
            'notes' => fake()->boolean(20) ? fake()->sentence() : null,
        ];
    }
}
