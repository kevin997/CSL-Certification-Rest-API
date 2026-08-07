<?php

namespace Tests\Feature\Payments;

use App\Models\Environment;
use App\Models\PaymentGatewaySetting;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Phase 3 (plan §9.8): settlement is system-owned. A client can never drive a
 * transaction into completed / refunded / partially_refunded, and only admins
 * may mutate status at all.
 */
class TransactionPolicyTest extends TestCase
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

    private function transaction(): Transaction
    {
        return Transaction::create([
            'transaction_id' => (string) Str::uuid(),
            'environment_id' => $this->environment->id,
            'payment_gateway_setting_id' => $this->gatewaySetting->id,
            'amount' => 100,
            'total_amount' => 100,
            'currency' => 'USD',
            'status' => Transaction::STATUS_PENDING,
        ]);
    }

    /** @test */
    public function a_non_admin_cannot_mutate_transaction_status()
    {
        $transaction = $this->transaction();
        Sanctum::actingAs(User::factory()->create(['role' => 'learner']));

        $response = $this->putJson("/api/transactions/{$transaction->id}/status", [
            'status' => 'cancelled',
        ]);

        $response->assertForbidden(); // 403 — policy update = admin only
        $this->assertSame(Transaction::STATUS_PENDING, $transaction->fresh()->status);
    }

    /** @test */
    public function an_admin_cannot_set_completed_status()
    {
        $transaction = $this->transaction();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->putJson("/api/transactions/{$transaction->id}/status", [
            'status' => 'completed',
        ]);

        // 410 Gone — settlement is system-owned.
        $response->assertStatus(410);
        $response->assertJson(['message' => 'settlement is system-owned']);
        $this->assertSame(Transaction::STATUS_PENDING, $transaction->fresh()->status);
    }

    /** @test */
    public function an_admin_cannot_set_refunded_status()
    {
        $transaction = $this->transaction();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->putJson("/api/transactions/{$transaction->id}/status", [
            'status' => 'refunded',
            'refund_reason' => 'customer request',
        ]);

        $response->assertStatus(410);
        $this->assertSame(Transaction::STATUS_PENDING, $transaction->fresh()->status);
    }

    /** @test */
    public function an_admin_can_perform_a_benign_transition()
    {
        $transaction = $this->transaction();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin']));

        $response = $this->putJson("/api/transactions/{$transaction->id}/status", [
            'status' => 'cancelled',
        ]);

        $response->assertOk();
        $this->assertSame(Transaction::STATUS_CANCELLED, $transaction->fresh()->status);
    }
}
