<?php

namespace Database\Factories;

use App\Models\EmailSuppression;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailSuppression>
 */
class EmailSuppressionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'reason' => EmailSuppression::REASON_HARD_BOUNCE,
            'status_code' => '5.1.1',
            'diagnostic' => '550 5.1.1 The email account that you tried to reach does not exist.',
            'source' => 'bounce_mailbox',
            'suppressed_at' => now(),
        ];
    }
}
