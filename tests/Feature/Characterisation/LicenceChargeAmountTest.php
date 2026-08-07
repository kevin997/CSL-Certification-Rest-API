<?php

namespace Tests\Feature\Characterisation;

use App\Models\Environment;
use App\Models\Plan;
use App\Models\User;
use App\Services\PaymentGateways\PaymentGatewayFactory;
use App\Services\PlatformPaymentGatewayResolver;
use App\Services\SubscriptionManager;
use App\Services\Tax\TaxZoneService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PHASE 4 FLIP: SubscriptionManager::createSubscriptionWithPayment now charges
 * the PLAN PRICE for the billing cycle (price_monthly / price_annual), not the
 * setup fee (doc §9.4). The White Label shape (price_annual = 500, setup_fee = 0)
 * is therefore charged $500, not $0. This test previously characterised the bug
 * (charged the setup fee only); it now asserts the corrected behaviour.
 */
class LicenceChargeAmountTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function subscription_manager_charges_the_plan_price_for_the_billing_cycle()
    {
        $user = User::factory()->create();

        $environment = Environment::create([
            'name' => 'Test Environment',
            'primary_domain' => 'test.example.com',
            'slug' => 'test-env',
            'owner_id' => $user->id,
        ]);

        // A plan with a real recurring price but a token setup fee — this is
        // the White Label plan shape from the KURSA catalogue (§4.4):
        // price_annual = 500, setup_fee = 0.
        $plan = Plan::create([
            'name' => 'White Label Annual',
            'type' => 'white_label_annual',
            'price_monthly' => 0,
            'price_annual' => 500,
            'setup_fee' => 0,
            'is_active' => true,
        ]);

        // Partial mock: stub out the actual gateway call (processPayment)
        // since we only care what Payment record gets created before it,
        // and creating the payment happens inside the same DB transaction
        // as the (mocked) gateway call.
        $manager = $this->getMockBuilder(SubscriptionManager::class)
            ->setConstructorArgs([
                app(PaymentGatewayFactory::class),
                app(TaxZoneService::class),
                app(PlatformPaymentGatewayResolver::class),
            ])
            ->onlyMethods(['processPayment'])
            ->getMock();

        $manager->method('processPayment')->willReturn([
            'success' => true,
            'message' => 'ok',
        ]);

        $result = $manager->createSubscriptionWithPayment(
            [
                'user_id' => $user->id,
                'environment_id' => $environment->id,
                'plan_id' => $plan->id,
                'billing_cycle' => 'annual',
                'status' => \App\Models\Subscription::STATUS_ACTIVE,
            ],
            [
                'payment_method' => 'stripe',
                'currency' => 'USD',
            ]
        );

        $this->assertTrue($result['success']);

        // PHASE 4 FLIP: the annual plan price ($500) is now charged, not the
        // setup fee ($0).
        $this->assertEquals(500, (float) $result['payment']->amount);

        $this->assertDatabaseHas('payments', [
            'id' => $result['payment']->id,
            'amount' => 500,
        ]);

        $this->assertDatabaseMissing('payments', [
            'id' => $result['payment']->id,
            'amount' => 0,
        ]);
    }
}
