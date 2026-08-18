<?php

namespace Tests\Feature\Payments;

use App\Models\PaymentGatewaySetting;
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

    public function test_it_is_discoverable_by_the_predicate_production_uses(): void
    {
        /*
         * PlatformPaymentGatewayResolver::resolve() also constructs a gateway
         * adapter, which needs real credentials, so the assertion stops at the
         * lookup -- that is the part the schema made impossible.
         */
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

        $found = PaymentGatewaySetting::withoutGlobalScopes()
            ->whereNull('environment_id')
            ->where('code', 'taramoney')
            ->where('status', true)
            ->orderByDesc('is_default')
            ->first();

        $this->assertNotNull($found, 'the platform fallback query must find it');
        $this->assertNull($found->environment_id);
    }
}
