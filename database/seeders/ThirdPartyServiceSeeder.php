<?php

namespace Database\Seeders;

use App\Models\ThirdPartyService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class ThirdPartyServiceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Add certificate generation service
        ThirdPartyService::firstOrCreate(
            ['service_type' => 'certificate_generation'],
            [
                'name' => 'Certificate Generation Service',
                'description' => 'Service for generating and managing certificates',
                'base_url' => env('CERTIFICATE_SERVICE_URL', 'https://gen-certificate.csl-brands.com'),
                'api_key' => env('CERTIFICATE_SERVICE_API_KEY', ''),
                'bearer_token' => env('CERTIFICATE_SERVICE_TOKEN', ''),
                'username' => env('CERTIFICATE_SERVICE_USERNAME', 'admin@cslcertificates.com'),
                'password' => env(key: 'CERTIFICATE_SERVICE_PASSWORD', default: 'kwbiCamn@1990'),
                'is_active' => true,
                'config' => json_encode([
                    'verify_ssl' => env('CERTIFICATE_SERVICE_VERIFY_SSL', false),
                    'timeout' => env('CERTIFICATE_SERVICE_TIMEOUT', 30),
                ]),
            ]
        );
        
        Log::info('Certificate Generation Service seeding completed');
    }
}
