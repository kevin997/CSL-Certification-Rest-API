<?php

namespace Database\Factories;

use App\Models\Branding;
use App\Models\Environment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Branding>
 */
class BrandingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => \App\Models\User::factory(),
            'environment_id' => Environment::factory(),
            'company_name' => $this->faker->company(),
            'logo_path' => 'logos/test-logo.png', // Default for testing URL generation
            'primary_color' => $this->faker->hexColor(),
            'secondary_color' => $this->faker->hexColor(),
            'is_active' => true,
            'font_family' => 'Inter',
        ];
    }
}
