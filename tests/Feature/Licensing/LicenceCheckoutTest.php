<?php

namespace Tests\Feature\Licensing;

use App\Models\Environment;
use App\Models\EnvironmentLicence;
use App\Models\LicenceCheckout;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Licensing\LicenceService;
use App\Services\Payments\WebhookProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * KURSA licensing (Phase 4) — checkout quoting + verified paid-event activation
 * (doc §9.4, §9.5, §11, §14).
 */
class LicenceCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private function makeEnvironment(): Environment
    {
        $user = User::factory()->create();

        return Environment::create([
            'name' => 'Checkout Env',
            'primary_domain' => 'checkout-' . uniqid() . '.example.com',
            'owner_id' => $user->id,
        ]);
    }

    private function service(): LicenceService
    {
        return app(LicenceService::class);
    }

    /** @test */
    public function creator_checkout_quotes_exactly_20_usd(): void
    {
        $env = $this->makeEnvironment();

        $checkout = $this->service()->createCheckout([
            'plan_type' => EnvironmentLicence::PLAN_CREATOR,
            'environment' => $env,
        ]);

        $this->assertEquals(20.00, (float) $checkout->quoted_amount);
        $this->assertSame('USD', $checkout->quoted_currency);
        $this->assertEquals(0.0, $checkout->taxAmount()); // no tax zone configured
        $this->assertEquals(20.00, $checkout->totalAmount());
        $this->assertSame(LicenceCheckout::STATUS_PENDING_PAYMENT, $checkout->status);
    }

    /** @test */
    public function white_label_checkout_quotes_exactly_500_usd(): void
    {
        $env = $this->makeEnvironment();

        $checkout = $this->service()->createCheckout([
            'plan_type' => EnvironmentLicence::PLAN_WHITE_LABEL,
            'environment' => $env,
        ]);

        $this->assertEquals(500.00, (float) $checkout->quoted_amount);
        $this->assertEquals(500.00, $checkout->totalAmount());
    }

    /** @test */
    public function verified_paid_event_activates_the_licence_via_webhook_processor(): void
    {
        Mail::fake();
        $env = $this->makeEnvironment();
        $this->service()->startFreeForever($env);

        $checkout = $this->service()->createCheckout([
            'plan_type' => EnvironmentLicence::PLAN_CREATOR,
            'environment' => $env,
        ]);

        $transaction = $this->makeLicenceTransaction($env, $checkout, 20.00);

        $result = app(WebhookProcessor::class)->settle(
            $transaction,
            'completed',
            'succeeded',
            ['amount' => 20.00, 'currency' => 'USD'],
            null,
            ['gateway' => 'stripe', 'provider_event_id' => 'evt_' . uniqid(), 'signature_valid' => true]
        );

        $this->assertSame('processed', $result);

        $checkout->refresh();
        $this->assertSame(LicenceCheckout::STATUS_PAID, $checkout->status);

        $licence = $env->licence()->first();
        $this->assertSame(EnvironmentLicence::STATUS_CREATOR_ACTIVE, $licence->status);
        $this->assertSame(EnvironmentLicence::PLAN_CREATOR, $licence->plan_type);
        // Creator paid event grants one month (doc §14).
        $this->assertEqualsWithDelta(now()->addMonth()->timestamp, $licence->ends_at->timestamp, 5);
    }

    /** @test */
    public function paid_event_with_wrong_amount_does_not_activate(): void
    {
        Mail::fake();
        $env = $this->makeEnvironment();
        $this->service()->startFreeForever($env);

        $checkout = $this->service()->createCheckout([
            'plan_type' => EnvironmentLicence::PLAN_CREATOR,
            'environment' => $env,
        ]);

        $transaction = $this->makeLicenceTransaction($env, $checkout, 20.00);

        // Gateway reports a different amount than the immutable expected snapshot.
        $result = app(WebhookProcessor::class)->settle(
            $transaction,
            'completed',
            'succeeded',
            ['amount' => 999.00, 'currency' => 'USD'],
            null,
            ['gateway' => 'stripe', 'provider_event_id' => 'evt_' . uniqid()]
        );

        $this->assertSame('failed', $result);

        $checkout->refresh();
        $this->assertSame(LicenceCheckout::STATUS_PENDING_PAYMENT, $checkout->status);

        // Licence untouched — still free.
        $licence = $env->licence()->first();
        $this->assertSame(EnvironmentLicence::STATUS_FREE_ACTIVE, $licence->status);
    }

    /** @test */
    public function free_onboarding_creates_environment_and_licence_with_no_payment(): void
    {
        Mail::fake();

        $env = $this->service()->provisionEnvironmentFromPayload([
            'name' => 'Ada Founder',
            'email' => 'ada-' . uniqid() . '@example.com',
            'whatsapp_number' => '+237600000000',
            'environment_name' => 'Ada Academy',
            'domain_type' => 'subdomain',
            'domain' => 'ada' . substr(uniqid(), -6),
            'country_code' => 'CM',
            'organization_type' => 'independent',
            'niche' => 'tech',
        ]);

        $licence = $this->service()->startFreeForever($env);

        $this->assertStringEndsWith('.csl-brands.com', $env->primary_domain);
        $this->assertDatabaseHas('environment_licences', [
            'environment_id' => $env->id,
            'plan_type' => EnvironmentLicence::PLAN_FREE,
            'status' => EnvironmentLicence::STATUS_FREE_ACTIVE,
        ]);
        $this->assertNull($licence->ends_at);

        // No money moved.
        $this->assertSame(0, Transaction::withoutGlobalScopes()->count());
        $this->assertDatabaseCount('payments', 0);
    }

    /**
     * Build a pending environment-licence transaction wired to a checkout, as
     * the platform payment flow would create it.
     */
    private function makeLicenceTransaction(Environment $env, LicenceCheckout $checkout, float $amount): Transaction
    {
        return Transaction::create([
            'environment_id' => $env->id,
            'merchant_environment_id' => $env->id,
            'customer_email' => 'buyer@example.com',
            'amount' => $amount,
            'total_amount' => $amount,
            'currency' => 'USD',
            'status' => Transaction::STATUS_PENDING,
            'purpose' => Transaction::PURPOSE_ENVIRONMENT_CREATOR_LICENSE,
            'source_type' => 'licence_checkout',
            'source_id' => $checkout->id,
            'expected_amount' => $amount,
            'expected_currency' => 'USD',
        ]);
    }
}
