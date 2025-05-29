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
        // Personal Free Plan
        Plan::firstOrCreate(
            ['type' => 'personal_free'],
            [
                'name' => 'Personal Free',
                'description' => 'Start your teaching journey with our free plan',
                'price_monthly' => 0.00,
                'price_annual' => 0.00,
                'setup_fee' => 0.00,
                'features' => json_encode([
                    'course_templates' => 3,
                    'customer_support' => '3 days per week',
                    'temporary_domain' => true,
                    'payment_gateways' => 1,
                    'look_and_feel' => false,
                    'marketing_features' => false,
                    'messaging_features' => false,
                    'multiple_enrollments' => false,
                ]),
                'limits' => json_encode([
                    'team_members' => 1,
                    'courses' => 3,
                ]),
                'is_active' => true,
                'sort_order' => 10,
            ]
        );

        // Personal Plus Plan
        Plan::firstOrCreate(
            ['type' => 'personal_plus'],
            [
                'name' => 'Personal Plus',
                'description' => 'Enhanced features for growing educators',
                'price_monthly' => 83.33, // 50,000 FCFA converted to USD
                'price_annual' => 833.33, // 10% discount for annual
                'setup_fee' => 0.00,
                'features' => json_encode([
                    'course_templates' => 10,
                    'customer_support' => '5 days per week',
                    'custom_domain' => true,
                    'payment_gateways' => 3,
                    'look_and_feel' => true,
                    'marketing_features' => true,
                    'messaging_features' => true,
                    'multiple_enrollments' => true,
                ]),
                'limits' => json_encode([
                    'team_members' => 10,
                    'extra_member_cost' => 15.00, // 9,000 FCFA converted to USD
                    'courses' => 20,
                ]),
                'is_active' => true,
                'sort_order' => 20,
            ]
        );

        // Personal Pro Plan
        Plan::firstOrCreate(
            ['type' => 'personal_pro'],
            [
                'name' => 'Personal Pro',
                'description' => 'Professional tools for serious educators',
                'price_monthly' => 250.00, // 150,000 FCFA converted to USD
                'price_annual' => 2500.00, // 10% discount for annual
                'setup_fee' => 0.00,
                'features' => json_encode([
                    'course_templates' => 'Unlimited',
                    'customer_support' => '7 days per week',
                    'custom_domain' => true,
                    'payment_gateways' => 'Unlimited',
                    'look_and_feel' => true,
                    'marketing_features' => true,
                    'messaging_features' => true,
                    'multiple_enrollments' => true,
                    'priority_support' => true,
                    'advanced_analytics' => true,
                ]),
                'limits' => json_encode([
                    'team_members' => 25,
                    'extra_member_cost' => 10.00,
                    'courses' => 50,
                ]),
                'is_active' => true,
                'sort_order' => 30,
            ]
        );

        // Business Plan
        Plan::firstOrCreate(
            ['type' => 'business'],
            [
                'name' => 'Business',
                'description' => 'Enterprise-grade solution for organizations',
                'price_monthly' => 0.00, // Annual only
                'price_annual' => 2500.00, // 1,500,000 FCFA converted to USD
                'setup_fee' => 0.00,
                'features' => json_encode([
                    'course_templates' => 'Unlimited',
                    'customer_support' => '24/7 dedicated',
                    'custom_domain' => true,
                    'payment_gateways' => 'Unlimited',
                    'look_and_feel' => true,
                    'marketing_features' => true,
                    'messaging_features' => true,
                    'multiple_enrollments' => true,
                    'priority_support' => true,
                    'advanced_analytics' => true,
                    'dedicated_support_team' => true,
                    'api_access' => true,
                    'white_labeling' => true,
                    'sso_integration' => true,
                ]),
                'limits' => json_encode([
                    'team_members' => 'Unlimited',
                    'courses' => 'Unlimited',
                ]),
                'is_active' => true,
                'sort_order' => 40,
            ]
        );

        // Legacy Plans (kept but inactive)
        Plan::firstOrCreate(
            ['type' => 'individual_teacher'],
            [
                'name' => 'Look And Feel ILT',
                'description' => 'Standard plan for individual teachers',
                'price_monthly' => 0.00,
                'price_annual' => 0.00,
                'setup_fee' => 1000.00,
                'features' => json_encode([
                    'max_students' => 100,
                    'max_courses' => 10,
                    'custom_domain' => true,
                    'white_labeling' => true,
                    'priority_support' => false,
                ]),
                'is_active' => false,
                'sort_order' => 1,
            ]
        );

        // Legacy Business Teacher Plan
        Plan::firstOrCreate(
            ['type' => 'business_legacy'],
            [
                'name' => 'Look And Feel BST',
                'description' => 'Standard plan for business customers',
                'price_monthly' => 0.00,
                'price_annual' => 0.00,
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
                'is_active' => false,
                'sort_order' => 0,
            ]
        );
    }
}
