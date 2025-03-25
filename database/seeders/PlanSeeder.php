<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    /**
     * Seed the subscription plans.
     */
    public function run(): void
    {
        // Individual Teacher Plan
        Plan::create([
            'name' => 'Look And Feel ILT',
            'type' => 'individual_teacher',
            'description' => 'Standard plan for individual teachers',
            'price_monthly' => 0.00, // Free for now, can be updated later
            'price_annual' => 0.00,  // Free for now, can be updated later
            'setup_fee' => 1000.00,
            'features' => json_encode([
                'max_students' => 100,
                'max_courses' => 10,
                'custom_domain' => true,
                'white_labeling' => true,
                'priority_support' => false,
            ]),
            'is_active' => true,
            'sort_order' => 1,
        ]);

        // Business Teacher Plan
        Plan::create([
            'name' => 'Look And Feel BST',
            'type' => 'business',
            'description' => 'Standard plan for business customers',
            'price_monthly' => 0.00, // Free for now, can be updated later
            'price_annual' => 0.00,  // Free for now, can be updated later
            'setup_fee' => 1500.00,
            'features' => json_encode([
                'max_students' => 500,
                'max_courses' => 50,
                'custom_domain' => true,
                'white_labeling' => true,
                'priority_support' => true,
                'dedicated_account_manager' => true,
                'api_access' => true,
            ]),
            'is_active' => true,
            'sort_order' => 0,
        ]);
    }
}
