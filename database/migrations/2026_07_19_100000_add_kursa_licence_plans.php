<?php

use App\Models\Plan;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Data migration for the KURSA licence catalogue (doc §4.1-4.4, §13.1).
     *
     * IMPORTANT: uses updateOrCreate (NOT firstOrCreate) — these plans may
     * already exist from a prior partial run or a re-deploy, and firstOrCreate
     * would silently skip updating price/feature changes on existing rows.
     */
    public function up(): void
    {
        // Free Forever — $0 forever (doc §4.1)
        Plan::updateOrCreate(
            ['type' => 'free_forever'],
            [
                'name' => 'Free Forever',
                'description' => 'Allow a new creator to validate an academy and complete the first-sale loop without financial risk.',
                'price_monthly' => 0,
                'price_annual' => 0,
                'setup_fee' => 0,
                'limits' => [
                    'published_courses' => 1,
                    'active_learners' => 100,
                    'admin_seats' => 1,
                    'payment_gateways' => 1,
                    'certificate_templates' => 1,
                ],
                'features' => [
                    'custom_domain' => false,
                    'kursa_branding' => 'visible',
                    'branding_control' => 'basic',
                    'course_product_subscriptions' => false,
                    'sales_forms' => false,
                    'marketing_automations' => false,
                    'coupons_referrals' => 'basic',
                    'quizzes' => 'basic',
                    'assignments' => 'basic',
                    'messaging' => 'manual',
                    'communities' => 'basic',
                    'live_sessions' => 'limited',
                    'analytics' => 'basic',
                    'financial_reports' => 'basic',
                    'data_exports' => false,
                    'api_webhooks' => 'none',
                    'transactional_email_identity' => 'kursa',
                    'support' => 'self_service',
                    'setup_assistance' => 'self_service',
                    'migration_assistance' => false,
                    'launch_review' => false,
                ],
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        // Creator Licence — $20/month (doc §4.2)
        Plan::updateOrCreate(
            ['type' => 'creator_monthly'],
            [
                'name' => 'Creator',
                'description' => 'Serve independent educators who have validated their academy and need more courses, learners, automation, and reporting.',
                'price_monthly' => 20.00,
                'price_annual' => 0,
                'setup_fee' => 0,
                'limits' => [
                    'published_courses' => 10,
                    'active_learners' => 1000,
                    'admin_seats' => 3,
                    'payment_gateways' => null,
                    'certificate_templates' => null,
                ],
                'features' => [
                    'custom_domain' => false,
                    'kursa_branding' => 'small',
                    'branding_control' => 'advanced',
                    'course_product_subscriptions' => true,
                    'sales_forms' => true,
                    'marketing_automations' => true,
                    'coupons_referrals' => 'full',
                    'quizzes' => 'advanced',
                    'assignments' => 'advanced',
                    'messaging' => 'automated',
                    'communities' => 'full',
                    'live_sessions' => 'full',
                    'analytics' => 'advanced',
                    'financial_reports' => 'full',
                    'data_exports' => true,
                    'api_webhooks' => 'limited',
                    'transactional_email_identity' => 'academy_logo',
                    'support' => 'email',
                    'setup_assistance' => 'self_service',
                    'migration_assistance' => false,
                    'launch_review' => false,
                ],
                'is_active' => true,
                'sort_order' => 2,
            ]
        );

        // White Label Licence — $500/year (doc §4.3)
        Plan::updateOrCreate(
            ['type' => 'white_label_annual'],
            [
                'name' => 'White Label',
                'description' => 'Provide complete brand ownership, higher scale, implementation assistance, and priority service.',
                'price_monthly' => 0,
                'price_annual' => 500.00,
                'setup_fee' => 0,
                'limits' => [
                    'published_courses' => null,
                    'active_learners' => 5000,
                    'admin_seats' => 15,
                    'payment_gateways' => null,
                    'certificate_templates' => null,
                ],
                'features' => [
                    'custom_domain' => true,
                    'kursa_branding' => 'removed',
                    'branding_control' => 'complete',
                    'course_product_subscriptions' => true,
                    'sales_forms' => true,
                    'marketing_automations' => true,
                    'coupons_referrals' => 'full',
                    'quizzes' => 'advanced',
                    'assignments' => 'advanced',
                    'messaging' => 'automated_branded',
                    'communities' => 'full',
                    'live_sessions' => 'full',
                    'analytics' => 'advanced_exports',
                    'financial_reports' => 'full',
                    'data_exports' => true,
                    'api_webhooks' => 'full',
                    'transactional_email_identity' => 'fully_branded',
                    'support' => 'priority',
                    'setup_assistance' => 'managed_onboarding',
                    'migration_assistance' => true,
                    'launch_review' => true,
                    'trial_days' => 14,
                ],
                'is_active' => true,
                'sort_order' => 3,
            ]
        );
    }

    /**
     * Reverse the migrations.
     *
     * Never delete these plans — they may have live subscriptions attached.
     * Deactivate instead so historical/subscription data stays intact.
     */
    public function down(): void
    {
        Plan::whereIn('type', ['free_forever', 'creator_monthly', 'white_label_annual'])
            ->update(['is_active' => false]);
    }
};
