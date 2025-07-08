<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\Plan;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add Standalone Plan
        Plan::firstOrCreate(
            ['type' => 'standalone'],
            [
                'name' => 'Standalone Plan',
                'description' => 'Self-service configuration for your learning environment',
                'price_monthly' => 0.00,
                'price_annual' => 0.00,
                'setup_fee' => 0.00,
                'features' => json_encode([
                    'self_configuration' => true,
                    'subdomain_option' => true,
                    'custom_domain_option' => true,
                    'automated_setup_instructions' => true,
                    'course_templates' => "unlimited",
                    'customer_support' => 'Email support',
                    'payment_gateways' => 2,
                ]),
                'limits' => json_encode([
                    'team_members' => "unlimited",
                    'courses' => "unlimited",
                ]),
                'is_active' => true,
                'sort_order' => 5,
            ]
        );

        // Add Supported Plan
        Plan::firstOrCreate(
            ['type' => 'supported'],
            [
                'name' => 'Supported Plan',
                'description' => 'Full-service configuration by CSL Brands support team',
                'price_monthly' => 0.00,
                'price_annual' => 0.00,
                'setup_fee' => 1167.00,
                'features' => json_encode([
                    'full_service_configuration' => true,
                    'custom_domain_connection' => true,
                    'branding_services' => true,
                    'certificate_templates' => true,
                    'platform_training' => true,
                    'course_development_assistance' => true,
                    'payment_integration' => true,
                    'personal_assistance' => true,
                    'course_templates' => "unlimited",
                    'customer_support' => 'Priority support',
                    'payment_gateways' => 'All available',
                ]),
                'limits' => json_encode([
                    'team_members' => "unlimited",
                    'courses' => "unlimited",
                ]),
                'is_active' => true,
                'sort_order' => 6,
            ]
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Plan::where('type', 'standalone')->delete();
        Plan::where('type', 'supported')->delete();
    }
};
