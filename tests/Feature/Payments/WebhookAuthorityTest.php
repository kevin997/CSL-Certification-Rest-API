<?php

namespace Tests\Feature\Payments;

use App\Models\Environment;
use App\Models\PaymentGatewaySetting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Payments\WebhookProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Phase 3: proves the WebhookProcessor authority guarantees (plan §11):
 * durable provider-event ledger + de-duplication, expected-amount validation,
 * and idempotent + monotonic settlement.
 */
class WebhookAuthorityTest extends TestCase
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

    private function pendingTransaction(array $overrides = []): Transaction
    {
        return Transaction::create(array_merge([
            'transaction_id' => (string) Str::uuid(),
            'environment_id' => $this->environment->id,
            'payment_gateway_setting_id' => $this->gatewaySetting->id,
            'amount' => 100,
            'total_amount' => 100,
            'currency' => 'USD',
            'status' => Transaction::STATUS_PENDING,
        ], $overrides));
    }

    private function processor(): WebhookProcessor
    {
        return app(WebhookProcessor::class);
    }

    /** @test */
    public function a_duplicate_provider_event_is_a_no_op()
    {
        $transaction = $this->pendingTransaction();

        $payload = ['id' => 'evt_dup_1', 'amount' => 100, 'currency' => 'USD'];

        $first = $this->processor()->settle(
            $transaction, 'completed', 'success', $payload, null, ['gateway' => 'monetbil']
        );
        $second = $this->processor()->settle(
            $transaction, 'completed', 'success', $payload, null, ['gateway' => 'monetbil']
        );

        $this->assertSame('processed', $first);
        $this->assertSame('duplicate', $second);

        $this->assertSame(Transaction::STATUS_COMPLETED, $transaction->fresh()->status);

        // Exactly one ledger row for this event — the second delivery is ignored.
        $this->assertDatabaseCount('provider_events', 1);
    }

    /** @test */
    public function an_amount_mismatch_does_not_settle()
    {
        // Expected snapshot says $100; the provider event claims $50.
        $transaction = $this->pendingTransaction([
            'expected_amount' => 100,
            'expected_currency' => 'USD',
        ]);

        $result = $this->processor()->settle(
            $transaction,
            'completed',
            'success',
            ['id' => 'evt_mismatch', 'amount' => 50, 'currency' => 'USD'],
            null,
            ['gateway' => 'monetbil']
        );

        $this->assertSame('failed', $result);
        $this->assertSame(Transaction::STATUS_PENDING, $transaction->fresh()->status);
        $this->assertNull($transaction->fresh()->paid_at);
    }

    /** @test */
    public function a_stale_pending_event_cannot_regress_a_failed_transaction()
    {
        $transaction = $this->pendingTransaction(['status' => Transaction::STATUS_FAILED]);

        $result = $this->processor()->settle(
            $transaction,
            'completed',
            'success',
            ['id' => 'evt_stale', 'amount' => 100, 'currency' => 'USD'],
            null,
            ['gateway' => 'monetbil']
        );

        $this->assertSame('skipped', $result);
        $this->assertSame(Transaction::STATUS_FAILED, $transaction->fresh()->status);
        $this->assertNull($transaction->fresh()->paid_at);
    }
}
