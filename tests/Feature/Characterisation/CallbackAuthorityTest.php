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

    /** @test */
    public function a_forged_get_callback_with_no_actual_gateway_confirmation_settles_a_pending_transaction()
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

        // CHARACTERISATION: current behavior — Phase N will flip this expectation.
        // Nothing here proves the gateway actually confirmed payment: this is a
        // bare GET request an attacker could send directly against the public
        // callback URL, guessing/knowing only the transaction_id.
        $response = $this->get(
            "/api/payments/transactions/callback/success/{$this->environment->id}"
            . "?status=success&transaction_id={$transactionId}"
        );

        $response->assertOk();

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => Transaction::STATUS_COMPLETED,
        ]);

        $this->assertNotNull($transaction->fresh()->paid_at);
    }
}
