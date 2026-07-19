<?php

namespace Tests\Feature\Characterisation;

use App\Models\Environment;
use App\Models\PaymentGatewaySetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;
use App\Http\Controllers\Api\TransactionController;

/**
 * CHARACTERISATION: locks in today's fail-open webhook signature behavior.
 * Phase 3 of the KURSA licensing transition plan makes Stripe/Moneroo/TaraMoney
 * webhooks fail-closed (reject when no secret is configured, remove the
 * businessId bypass) — see TransactionController::handleStripeWebhook
 * (~:889-916), verifyMonerooWebhookSignature (~:1999) and
 * verifyTaraMoneyWebhookSignature (~:2025/:2046).
 */
class WebhookSignatureTest extends TestCase
{
    use RefreshDatabase;

    private Environment $environment;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->create();

        $this->environment = Environment::create([
            'name' => 'Test Environment',
            'primary_domain' => 'test.example.com',
            'slug' => 'test-env',
            'owner_id' => $owner->id,
        ]);
    }

    /**
     * CHARACTERISATION: current behavior — Phase N will flip this expectation.
     *
     * A Stripe webhook with NO webhook_secret configured on the gateway
     * settings is accepted (payload just json_decode'd, no signature check)
     * instead of being rejected outright.
     *
     * @test
     */
    public function stripe_webhook_with_no_secret_configured_accepts_unsigned_json()
    {
        PaymentGatewaySetting::create([
            'environment_id' => $this->environment->id,
            'gateway_name' => 'Stripe',
            'code' => 'stripe',
            'status' => true,
            'is_default' => true,
            'mode' => 'sandbox',
            // api_key present (required to reach signature verification),
            // webhook_secret intentionally absent.
            'settings' => ['api_key' => 'sk_test_fake'],
        ]);

        $payload = json_encode([
            'type' => 'some.unhandled.event.type',
            'data' => ['object' => ['id' => 'evt_fake']],
        ]);

        $response = $this->call(
            'POST',
            "/api/payments/transactions/webhook/stripe/{$this->environment->id}",
            [],
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            $payload
        );

        // No 'stripe-signature' header was sent and no webhook_secret is
        // configured, yet the webhook is still processed successfully.
        $response->assertOk();
        $response->assertJson(['status' => 'success']);
    }

    /**
     * CHARACTERISATION: current behavior — Phase N will flip this expectation.
     *
     * verifyMonerooWebhookSignature() returns true (accepts the webhook)
     * whenever the gateway has no webhook_secret configured, regardless of
     * headers or payload.
     *
     * @test
     */
    public function moneroo_signature_verification_returns_true_when_secret_is_empty()
    {
        $gatewaySetting = PaymentGatewaySetting::create([
            'environment_id' => $this->environment->id,
            'gateway_name' => 'Moneroo',
            'code' => 'moneroo',
            'status' => true,
            'is_default' => true,
            'mode' => 'sandbox',
            'settings' => [], // no webhook_secret
        ]);

        $controller = new TransactionController();
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('verifyMonerooWebhookSignature');
        $method->setAccessible(true);

        $result = $method->invoke(
            $controller,
            '{"id":"evt_fake"}',
            [], // no signature headers at all
            $gatewaySetting
        );

        $this->assertTrue($result);
    }

    /**
     * CHARACTERISATION: current behavior — Phase N will flip this expectation.
     *
     * verifyTaraMoneyWebhookSignature() returns true (accepts the webhook)
     * whenever the gateway has no webhook_secret configured.
     *
     * @test
     */
    public function taramoney_signature_verification_returns_true_when_secret_is_empty()
    {
        $gatewaySetting = PaymentGatewaySetting::create([
            'environment_id' => $this->environment->id,
            'gateway_name' => 'TaraMoney',
            'code' => 'taramoney',
            'status' => true,
            'is_default' => true,
            'mode' => 'sandbox',
            'settings' => [], // no webhook_secret
        ]);

        $controller = new TransactionController();
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('verifyTaraMoneyWebhookSignature');
        $method->setAccessible(true);

        $result = $method->invoke(
            $controller,
            '{"paymentId":"pay_fake"}',
            [], // no signature headers at all
            $gatewaySetting,
            ['paymentId' => 'pay_fake']
        );

        $this->assertTrue($result);
    }

    /**
     * CHARACTERISATION: current behavior — Phase N will flip this expectation.
     *
     * When a webhook_secret IS configured but no signature is present,
     * verifyTaraMoneyWebhookSignature() still accepts the webhook purely on
     * the strength of the payload's businessId matching the gateway's
     * configured business_id — a bypass with no cryptographic verification.
     *
     * @test
     */
    public function taramoney_signature_verification_bypasses_via_matching_business_id()
    {
        $gatewaySetting = PaymentGatewaySetting::create([
            'environment_id' => $this->environment->id,
            'gateway_name' => 'TaraMoney',
            'code' => 'taramoney',
            'status' => true,
            'is_default' => true,
            'mode' => 'sandbox',
            'settings' => [
                'webhook_secret' => 'a-real-secret',
                'business_id' => 'biz_123',
            ],
        ]);

        $controller = new TransactionController();
        $reflection = new ReflectionClass($controller);
        $method = $reflection->getMethod('verifyTaraMoneyWebhookSignature');
        $method->setAccessible(true);

        $result = $method->invoke(
            $controller,
            '{"paymentId":"pay_fake","businessId":"biz_123"}',
            [], // no signature headers — only the businessId is checked
            $gatewaySetting,
            ['paymentId' => 'pay_fake', 'businessId' => 'biz_123']
        );

        $this->assertTrue($result);
    }
}
