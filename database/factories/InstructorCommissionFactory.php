<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\InstructorCommission>
 */
class InstructorCommissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $grossAmount = fake()->randomFloat(2, 10000, 500000);
        $commissionRate = 0.1500; // 15% platform commission
        $commissionAmount = round($grossAmount * $commissionRate, 2);
        $netAmount = round($grossAmount - $commissionAmount, 2);

        return [
            'environment_id' => \App\Models\Environment::factory(),
            'transaction_id' => \App\Models\Transaction::factory(),
            'order_id' => \App\Models\Order::factory(),
            'gross_amount' => $grossAmount,
            'commission_rate' => $commissionRate,
            'commission_amount' => $commissionAmount,
            'net_amount' => $netAmount,
            'currency' => 'XAF',
            'status' => fake()->randomElement(['pending', 'approved', 'paid', 'disputed']),
            'paid_at' => fake()->boolean(30) ? fake()->dateTimeBetween('-30 days', 'now') : null,
            'payment_reference' => fake()->boolean(30) ? fake()->uuid() : null,
            'withdrawal_request_id' => null,
            'notes' => fake()->boolean(20) ? fake()->sentence() : null,
        ];
    }
}
