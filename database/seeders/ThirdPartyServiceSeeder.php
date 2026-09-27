<?php

namespace Database\Seeders;

use App\Models\ThirdPartyService;
use App\Scopes\EnvironmentScope;
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
        ThirdPartyService::withoutGlobalScope(EnvironmentScope::class)
            ->whereNull('environment_id')
            ->firstOrCreate(
                ['service_type' => 'certificate_generation'],
                [
                    'name' => 'Certificate Generation Service',
                    'description' => 'Service for generating and managing certificates',
                    'base_url' => config('services.certificate_generation_seed.base_url'),
                    'api_key' => config('services.certificate_generation_seed.api_key'),
                    'bearer_token' => config('services.certificate_generation_seed.bearer_token'),
                    'username' => config('services.certificate_generation_seed.username'),
                    'password' => config('services.certificate_generation_seed.password'),
                    'is_active' => filled(config('services.certificate_generation_seed.bearer_token'))
                        || (filled(config('services.certificate_generation_seed.username'))
                            && filled(config('services.certificate_generation_seed.password'))),
                    'config' => [
                        'verify_ssl' => config('services.certificate_generation_seed.verify_ssl'),
                        'timeout' => config('services.certificate_generation_seed.timeout'),
                    ],
                ]
            );

        Log::info('Certificate Generation Service seeding completed');
    }
}
