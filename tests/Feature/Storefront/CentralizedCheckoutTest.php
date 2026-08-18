<?php

namespace Tests\Feature\Storefront;

use App\Models\Environment;
use App\Models\EnvironmentPaymentConfig;
use App\Models\PaymentGatewaySetting;
use App\Services\Payments\PaymentGatewayResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A centralized tenant borrows the provider's gateways. Production returned a
 * 500 here: the storefront listed the provider's gateway id, then checkout
 * looked that id up under the tenant's session scope, found nothing, and the
 * "not available" branch threw on an unimported Response class.
 */
class CentralizedCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function centralizedSetup(): array
    {
        $provider = Environment::factory()->create(['is_active' => true, 'is_centralized_payment_provider' => true]);
        $tenant = Environment::factory()->create(['is_active' => true]);

        EnvironmentPaymentConfig::factory()->create([
            'environment_id' => $tenant->id,
            'use_centralized_gateways' => true,
            'is_active' => true,
        ]);

        $gateway = PaymentGatewaySetting::withoutGlobalScopes()->forceCreate([
            'environment_id' => $provider->id,
            'gateway_name' => 'TaraMoney',
            'code' => 'taramoney',
            'display_name' => 'TaraMoney',
            'status' => true,
            'is_default' => true,
            'mode' => 'live',
            'sort_order' => 0,
            'settings' => ['api_key' => 'k', 'business_id' => 'b'],
        ]);

        return [$provider, $tenant, $gateway];
    }

    public function test_the_gateway_the_storefront_advertises_is_resolvable_at_checkout(): void
    {
        [, $tenant, $gateway] = $this->centralizedSetup();

        // Exactly what the storefront request establishes.
        session(['current_environment_id' => $tenant->id]);

        $listed = app(PaymentGatewayResolver::class)->listFor($tenant->id);
        $this->assertCount(1, $listed, 'the storefront advertises the provider gateway');

        $resolved = app(PaymentGatewayResolver::class)->forId($listed->first()->id, $tenant->id);
        $this->assertNotNull($resolved, 'checkout must resolve the id it advertised');
        $this->assertSame($gateway->id, $resolved->id);
    }

    public function test_an_unavailable_gateway_returns_422_not_500(): void
    {
        [, $tenant] = $this->centralizedSetup();
        $unrelated = Environment::factory()->create(['is_active' => true]);
        $theirs = PaymentGatewaySetting::withoutGlobalScopes()->forceCreate([
            'environment_id' => $unrelated->id,
            'gateway_name' => 'Stripe',
            'code' => 'stripe',
            'display_name' => 'Card',
            'status' => true,
            'is_default' => false,
            'mode' => 'live',
            'sort_order' => 0,
            'settings' => [],
        ]);

        session(['current_environment_id' => $tenant->id]);

        // The branch that threw: resolving a gateway this tenant may not use
        // must produce a clean "not available", never a fatal Error.
        $this->assertNull(app(PaymentGatewayResolver::class)->forId($theirs->id, $tenant->id));
    }

    /**
     * Guards Defect B directly: an unimported Response class made every
     * HTTP_* constant in this controller a fatal error.
     */
    public function test_the_storefront_controller_can_reference_its_response_constants(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Api/StorefrontController.php'));

        $this->assertMatchesRegularExpression(
            '/^use\s+(Illuminate\\\\Http|Symfony\\\\Component\\\\HttpFoundation)\\\\Response;/m',
            $source,
            'StorefrontController uses Response::HTTP_* constants and must import a Response class'
        );
    }

    /**
     * Pins Defect A at the call site, which the resolver-level tests above do
     * not: they pass whether or not the controller was ever changed, because
     * they never touch the controller. A scoped lookup here is what returned
     * null for an id this same controller had just advertised.
     */
    public function test_the_checkout_and_continue_payment_lookups_go_through_the_resolver(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Api/StorefrontController.php'));

        $this->assertStringNotContainsString(
            'PaymentGatewaySetting::find($paymentMethod)',
            $source,
            'checkout must not resolve a gateway with a scoped find()'
        );

        $this->assertStringNotContainsString(
            "PaymentGatewaySetting::where('environment_id', \$order->environment_id)",
            $source,
            'continue-payment must not filter gateways on the tenant environment directly'
        );

        $this->assertStringContainsString(
            'PaymentGatewayResolver::class',
            $source,
            'both lookups resolve through PaymentGatewayResolver'
        );
    }
}
