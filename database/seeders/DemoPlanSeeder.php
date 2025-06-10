<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class DemoPlanSeeder extends Seeder
{
    /**
     * Seed the demo plan.
     */
    public function run(): void
    {
        // Demo Plan
        Plan::firstOrCreate(
            ['type' => 'demo'],
            [
                'name' => 'Demo Plan',
                'description' => 'Try our platform with full features for 14 days',
                'price_monthly' => 0.00,
                'price_annual' => 0.00,
                'setup_fee' => 0.00,
                'features' => json_encode([
                    'course_templates' => 1,
                    'customer_support' => '7 days per week',
                    'custom_domain' => true,
                    'payment_gateways' => 'None',
                    'look_and_feel' => true,
                    'marketing_features' => true,
                    'messaging_features' => true,
                    'multiple_enrollments' => true,
                    'priority_support' => true,
                    'advanced_analytics' => true,
                    'expires_after_days' => 14,
                ]),
                'limits' => json_encode([
                    'team_members' => 0,
                    'courses' => 1,
                ]),
                'is_active' => true,
                'sort_order' => 5, // Position it before the Personal Free plan
            ]
        );
    }
}
