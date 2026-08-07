<?php

namespace Tests\Feature\Payments;

use App\Models\Environment;
use App\Models\PaymentGatewaySetting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 3: browser callbacks are display-only (plan §9.2), and a public,
 * minimal status endpoint lets checkouts poll asynchronous settlement.
 */
class CallbackDisplayOnlyTest extends TestCase
{
    use RefreshDatabase;

    private Environment $environment;
    private PaymentGatewaySetting $gatewaySetting;

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

        $this->gatewaySetting = PaymentGatewaySetting::create([
            'environment_id' => $this->environment->id,
            'gateway_name' => 'Monetbil',
            'code' => 'monetbil',
            'status' => true,
            'is_default' => true,
            'mode' => 'sandbox',
        ]);
    }

    /** @test */
    public function a_success_callback_leaves_transaction_state_untouched()
    {
        $transactionId = (string) Str::uuid();

        $transaction = Transaction::create([
            'transaction_id' => $transactionId,
            'environment_id' => $this->environment->id,
            'payment_gateway_setting_id' => $this->gatewaySetting->id,
            'amount' => 100,
            'total_amount' => 100,
            'currency' => 'USD',
            'status' => Transaction::STATUS_PENDING,
        ]);

        $response = $this->get(
            "/api/payments/transactions/callback/success/{$this->environment->id}"
            . "?status=success&transaction_id={$transactionId}"
        );

        $response->assertOk();

        $fresh = $transaction->fresh();
        $this->assertSame(Transaction::STATUS_PENDING, $fresh->status);
        $this->assertNull($fresh->paid_at);
    }

    /** @test */
    public function the_public_status_endpoint_returns_only_status_purpose_order()
    {
        $transactionId = (string) Str::uuid();

        Transaction::create([
            'transaction_id' => $transactionId,
            'environment_id' => $this->environment->id,
            'payment_gateway_setting_id' => $this->gatewaySetting->id,
            'amount' => 100,
            'total_amount' => 100,
            'currency' => 'USD',
            'status' => Transaction::STATUS_PENDING,
            'purpose' => Transaction::PURPOSE_COURSE_SALE,
        ]);

        $response = $this->getJson("/api/payments/transactions/{$transactionId}/status");

        $response->assertOk();
        $response->assertJson([
            'status' => Transaction::STATUS_PENDING,
            'purpose' => Transaction::PURPOSE_COURSE_SALE,
        ]);
        // No financial detail is exposed.
        $response->assertJsonMissing(['amount' => 100]);
        $response->assertJsonStructure(['status', 'purpose', 'order_id']);
    }
}
