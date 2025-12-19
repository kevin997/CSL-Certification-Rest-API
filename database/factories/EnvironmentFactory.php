<?php

namespace Database\Factories;

use App\Models\Action;
use App\Models\Environment;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Environment>
 */
class EnvironmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->company(),
            'primary_domain' => $this->faker->domainName(),
            'theme_color' => $this->faker->hexColor(),
            'is_active' => true,
            'is_demo' => false,
            'owner_id' => User::factory(),
            'description' => $this->faker->sentence(),
            'country_code' => $this->faker->countryCode(),
            'niche' => 'Education',
        ];
    }
}
