<?php

namespace Database\Seeders;

use App\Models\PaymentGatewaySetting;
use App\Models\Environment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class TaraMoneyGatewaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Seeds TaraMoney payment gateway settings for Environment 1 (CSL).
     * Note: Due to unique constraint on 'code' column, only one TaraMoney gateway
     * can exist across all environments.
     */
    public function run(): void
    {
        // Check if TaraMoney gateway already exists (unique constraint on code)
        $exists = PaymentGatewaySetting::where('code', 'taramoney')->exists();

        if ($exists) {
            $existingGateway = PaymentGatewaySetting::where('code', 'taramoney')->first();
            $this->command->info("TaraMoney gateway already exists for Environment {$existingGateway->environment_id}");
            return;
        }

        // Get Environment 1 (CSL - the main environment)
        $environment = Environment::find(1);

        if (!$environment) {
            $this->command->warn("Environment 1 not found. Please create the CSL environment first.");
            return;
        }

        try {
            $this->createTaraMoneyGateway($environment->id);

            $this->command->info("Don't forget to update .env with your TaraMoney credentials:");
            $this->command->line("  TARAMONEY_API_KEY=your_production_api_key");
            $this->command->line("  TARAMONEY_BUSINESS_ID=your_business_id");
            $this->command->line("  TARAMONEY_WEBHOOK_SECRET=your_webhook_secret");
            $this->command->line("  TARAMONEY_TEST_API_KEY=your_sandbox_api_key");
            $this->command->line("  TARAMONEY_TEST_BUSINESS_ID=your_test_business_id");
            $this->command->line("  TARAMONEY_TEST_MODE=true");
        } catch (\Illuminate\Validation\ValidationException $e) {
            $this->command->error("Failed to create TaraMoney gateway:");
            foreach ($e->errors() as $field => $messages) {
                foreach ($messages as $message) {
                    $this->command->error("  - {$message}");
                }
            }
        } catch (\Exception $e) {
            $this->command->error("Unexpected error creating TaraMoney gateway: {$e->getMessage()}");
            Log::error('TaraMoney Gateway Seeder Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Create TaraMoney gateway for a specific environment
     */
    private function createTaraMoneyGateway(int $environmentId): void
    {

        // Create TaraMoney payment gateway settings
        $gateway = PaymentGatewaySetting::create([
            'environment_id' => $environmentId,
            'code' => 'taramoney',
            'gateway_name' => 'TaraMoney',
            'display_name' => 'TaraMoney',
            'description' => 'Pay with TaraMoney - WhatsApp, Telegram, PayPal or Mobile Money',
            'status' => true,
            'is_default' => false,
            'mode' => 'live', // 'test' or 'live'
            'sort_order' => 40,
            'icon' => 'taramoney-icon.svg',
            'webhook_url' => 'https://certification.csl-brands.com/api/payments/webhook',
            'success_url' => "https://certification.csl-brands.com/api/payments/transactions/callback/success/1",
            'failure_url' => "https://certification.csl-brands.com/api/payments/transactions/callback/failure/1",
            'settings' => json_encode([
                // Production keys (you should update these with actual values)
                'api_key' => env('TARAMONEY_API_KEY', 'lh7us1f2vfDyxmTgU0NIpcep'),
                'business_id' => env('TARAMONEY_BUSINESS_ID', 'GqLD0LhdCh'),
                'webhook_secret' => env('TARAMONEY_WEBHOOK_SECRET', '9Wb3EkeBpNJbzYXiE19P9YKY'),

                // Sandbox keys (for testing)
                'test_api_key' => env('TARAMONEY_TEST_API_KEY', 'q9foJGFWYiHy6xXoa47eR7ka'),
                'test_business_id' => env('TARAMONEY_TEST_BUSINESS_ID', 'GqLD0LhdCh'),

                // Display settings
                'display_name' => 'TaraMoney',
                'description' => 'Pay via WhatsApp, Telegram, or Mobile Money',
                'logo_url' => '/images/payment-methods/taramoney.png',
                'supported_currencies' => 'XAF,XOF',

                // Test mode
                'test_mode' => env('TARAMONEY_TEST_MODE', false),

                // Feature flags
                'supports_mobile_money' => true,
                'supports_messaging_apps' => true,
                'supports_card_payments' => false,
            ]),
        ]);

        $this->command->info("Created TaraMoney gateway for Environment {$environmentId} (Gateway ID: {$gateway->id})");
    }
}
