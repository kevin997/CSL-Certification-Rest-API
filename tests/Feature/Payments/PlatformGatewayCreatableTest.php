<?php

namespace Tests\Feature\Payments;

use App\Models\PaymentGatewaySetting;
use App\Services\PlatformPaymentGatewayResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Platform-scoped gateways must be constructible under test.
 *
 * PlatformPaymentGatewayResolver reads them with whereNull('environment_id'),
 * but 2026_04_30_120000 only ALTERed the column for mysql and pgsql -- so on the
 * SQLite test driver environment_id stayed NOT NULL and no test could build one.
 * The entire platform payment path was therefore untestable, which is why the
 * webhook fall-through it guards was only ever asserted indirectly.
 */
class PlatformGatewayCreatableTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_platform_gateway_can_be_created(): void
    {
        $gateway = PaymentGatewaySetting::withoutGlobalScopes()->forceCreate([
            'environment_id' => null,
            'gateway_name' => 'TaraMoney',
            'code' => 'taramoney',
            'display_name' => 'TaraMoney',
            'status' => true,
            'is_default' => true,
            'mode' => 'live',
            'sort_order' => 0,
            'settings' => ['api_key' => 'platform'],
        ]);

        $this->assertNull(
            $gateway->fresh()->environment_id,
            'a platform gateway is defined by having no environment'
        );
    }

    public function test_the_platform_resolver_finds_it(): void
    {
        PaymentGatewaySetting::withoutGlobalScopes()->forceCreate([
            'environment_id' => null,
            'gateway_name' => 'TaraMoney',
            'code' => 'taramoney',
            'display_name' => 'TaraMoney',
            'status' => true,
            'is_default' => true,
            'mode' => 'live',
            'sort_order' => 0,
            'settings' => ['api_key' => 'platform'],
        ]);

        $resolved = app(PlatformPaymentGatewayResolver::class)->resolve('taramoney');

        $this->assertTrue(
            ($resolved['success'] ?? false) || ($resolved['settings'] ?? null) !== null,
            'the platform resolver must find a platform gateway: '.json_encode($resolved)
        );
    }
}
