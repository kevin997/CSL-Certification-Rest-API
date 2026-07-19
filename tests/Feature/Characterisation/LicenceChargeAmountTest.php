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
 * CHARACTERISATION: locks in today's incorrect licence charge amount.
 * SubscriptionManager::createSubscriptionWithPayment (~:91) always charges
 * $plan->setup_fee, never $plan->price_monthly / $plan->price_annual — so a
 * paid plan with a real monthly/annual price but setup_fee = 0 is
 * provisioned for free. Phase 4 fixes this via LicenceService's canonical
 * checkout quote ($20/$500 USD, no setup fee).
 */
class LicenceChargeAmountTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function subscription_manager_charges_only_the_plan_setup_fee_not_its_monthly_or_annual_price()
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

        // CHARACTERISATION: current behavior — Phase N will flip this expectation.
        // The payment amount charged is the plan's setup_fee (0), NOT its
        // price_annual (500) — the customer is charged nothing for a plan
        // priced at $500/year.
        $this->assertEquals(0, (float) $result['payment']->amount);

        $this->assertDatabaseHas('payments', [
            'id' => $result['payment']->id,
            'amount' => 0,
        ]);

        $this->assertDatabaseMissing('payments', [
            'id' => $result['payment']->id,
            'amount' => 500,
        ]);
    }
}
