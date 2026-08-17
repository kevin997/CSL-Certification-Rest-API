<?php

namespace Tests\Feature\Storefront;

use App\Models\Environment;
use App\Models\PaymentGatewaySetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The storefront gateway routes carry no auth middleware, so anything they
 * return is world-readable. They used to serialize the whole model, which put
 * live gateway credentials in a public response.
 */
class PublicGatewayPayloadTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET_KEYS = [
        'api_key',
        'webhook_secret',
        'secret_key',
        'service_key',
        'service_secret',
        'test_api_key',
        'test_service_key',
        'test_service_secret',
        'business_id',
        'test_business_id',
    ];

    private function environmentWithGateway(string $code = 'stripe'): Environment
    {
        $environment = Environment::factory()->create(['is_active' => true]);

        PaymentGatewaySetting::withoutGlobalScopes()->forceCreate([
            'environment_id' => $environment->id,
            'gateway_name' => 'Stripe',
            'code' => $code,
            'display_name' => 'Card',
            'description' => 'Pay by card',
            'status' => true,
            'is_default' => true,
            'mode' => 'live',
            'sort_order' => 0,
            'settings' => [
                'api_key' => 'sk_live_must_never_be_public',
                'publishable_key' => 'pk_live_safe_but_not_needed_here',
                'webhook_secret' => 'whsec_must_never_be_public',
            ],
        ]);

        return $environment;
    }

    public function test_the_public_gateway_list_never_exposes_settings(): void
    {
        $environment = $this->environmentWithGateway();

        $response = $this->getJson("/api/storefront/{$environment->id}/payment-gateways")
            ->assertOk();

        $gateway = $response->json('data.0');

        $this->assertIsArray($gateway);
        $this->assertArrayNotHasKey('settings', $gateway);
    }

    public function test_no_credential_value_appears_anywhere_in_the_response_body(): void
    {
        $environment = $this->environmentWithGateway();

        $body = $this->getJson("/api/storefront/{$environment->id}/payment-gateways")
            ->assertOk()
            ->getContent();

        // Values, not just keys: a nested or renamed blob would still leak.
        $this->assertStringNotContainsString('sk_live_must_never_be_public', $body);
        $this->assertStringNotContainsString('whsec_must_never_be_public', $body);

        foreach (self::SECRET_KEYS as $key) {
            $this->assertStringNotContainsString($key, $body, "Response exposes [{$key}].");
        }
    }

    public function test_it_still_returns_the_fields_checkout_depends_on(): void
    {
        $environment = $this->environmentWithGateway();

        $gateway = $this->getJson("/api/storefront/{$environment->id}/payment-gateways")
            ->assertOk()
            ->json('data.0');

        // checkout-client selects by code, matches the default, and posts the id.
        foreach (['id', 'code', 'gateway_name', 'is_default'] as $field) {
            $this->assertArrayHasKey($field, $gateway);
        }

        $this->assertSame('stripe', $gateway['code']);
        $this->assertTrue($gateway['is_default']);
    }

    public function test_the_payment_methods_endpoint_responds_instead_of_throwing(): void
    {
        $environment = $this->environmentWithGateway('taramoney');

        $method = $this->getJson("/api/storefront/{$environment->id}/payment-methods")
            ->assertOk()
            ->json('data.0');

        // It eager-loaded a relation that does not exist, so this 500'd.
        $this->assertSame('Card', $method['name']);
        $this->assertSame('taramoney', $method['type']);
        $this->assertArrayNotHasKey('settings', $method);
    }
}
