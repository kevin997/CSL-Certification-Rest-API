<?php

namespace Tests\Feature\Characterisation;

use App\Models\Environment;
use App\Models\PaymentGatewaySetting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CHARACTERISATION: locks in today's (buggy) behavior of the public,
 * unauthenticated GET /payments/transactions/callback/success/{environment_id}
 * route. Phase 3 of the KURSA licensing transition plan turns browser
 * callbacks into display-only redirects and moves settlement authority to
 * server-to-server webhooks (see TransactionController::callbackSuccess
 * ~line 110 and PaymentService::processSuccessCallback).
 */
class CallbackAuthorityTest extends TestCase
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

    /**
     * PHASE 3 FLIP: a forged browser callback NO LONGER settles anything.
     * Browser callbacks became display-only (plan §9.2) — settlement authority
     * moved to signed webhooks / server-to-server verification. A bare GET an
     * attacker sends against the public callback URL now only renders a status
     * page and leaves the transaction exactly as it was (pending, unpaid).
     *
     * @test
     */
    public function a_forged_get_callback_does_not_settle_a_pending_transaction()
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

        // The display-only page still renders (200) …
        $response->assertOk();

        // … but the transaction is untouched: NOT completed, NOT paid.
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => Transaction::STATUS_PENDING,
        ]);

        $this->assertNull($transaction->fresh()->paid_at);
    }
}
