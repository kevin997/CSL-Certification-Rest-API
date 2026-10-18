<?php

namespace Tests\Feature\Payments;

use App\Models\Environment;
use App\Models\PaymentGatewaySetting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PaymentGateways\TaraMoneyGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TaraStatusVerificationTest extends TestCase
{
    use RefreshDatabase;

    private PaymentGatewaySetting $settings;

    private Transaction $transaction;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->create();
        $environment = Environment::create([
            'name' => 'Test Environment',
            'primary_domain' => 'tara.example.com',
            'slug' => 'tara-test',
            'owner_id' => $owner->id,
        ]);
        $this->settings = PaymentGatewaySetting::create([
            'environment_id' => $environment->id,
            'gateway_name' => 'TaraMoney',
            'code' => 'taramoney',
            'status' => true,
            'mode' => 'sandbox',
            'settings' => ['api_key' => 'test-key', 'business_id' => 'test-business'],
        ]);
        $this->transaction = Transaction::create([
            'transaction_id' => 'TXN-tara-test',
            'environment_id' => $environment->id,
            'payment_gateway_setting_id' => $this->settings->id,
            'amount' => 4.28,
            'total_amount' => 5.10,
            'currency' => 'USD',
            'expected_amount' => 4.28,
            'expected_currency' => 'USD',
            'converted_amount' => 2992.34,
            'status' => Transaction::STATUS_PENDING,
        ]);
    }

    private function verify(array $providerPayload, string $outerStatus = 'SUCCESS'): array
    {
        Http::fake(['www.dklo.co/*' => Http::response([
            'status' => $outerStatus,
            'transactionId' => 'provider-txn',
            'payload' => json_encode($providerPayload),
        ])]);

        $gateway = new TaraMoneyGateway;
        $gateway->initialize($this->settings);

        return $gateway->verifyPayment($this->transaction->transaction_id);
    }

    private function paidPayload(): array
    {
        return [
            'businessId' => 'test-business',
            'productId' => 'TXN-tara-test',
            'paymentId' => 'payment-1',
            'amount' => '2992',
            'status' => 'SUCCESS',
        ];
    }

    public function test_provider_confirmed_payment_is_normalized_after_validating_xaf_amount(): void
    {
        $result = $this->verify($this->paidPayload());

        $this->assertTrue($result['verified']);
        $this->assertSame('success', $result['status']);
        $this->assertSame(2992, $result['paid_amount_xaf']);
        $this->assertSame(4.28, $result['amount']);
        $this->assertSame('USD', $result['currency']);
    }

    public function test_wrong_business_product_or_amount_cannot_verify(): void
    {
        foreach ([
            ['businessId' => 'other-business'],
            ['productId' => 'other-product'],
            ['amount' => '2500'],
        ] as $change) {
            $result = $this->verify(array_replace($this->paidPayload(), $change));
            $this->assertFalse($result['verified']);
        }
    }

    public function test_pending_provider_status_does_not_verify_payment(): void
    {
        $result = $this->verify(array_replace($this->paidPayload(), ['status' => 'PENDING']));

        $this->assertFalse($result['verified']);
        $this->assertSame('pending', $result['status']);
    }

    public function test_pending_payment_command_reconciles_confirmed_tara_payment(): void
    {
        Http::fake(['www.dklo.co/*' => Http::response([
            'status' => 'SUCCESS',
            'transactionId' => 'provider-txn',
            'payload' => json_encode($this->paidPayload()),
        ])]);

        $this->artisan('payments:verify-pending')->assertSuccessful();

        $this->assertSame(Transaction::STATUS_COMPLETED, $this->transaction->fresh()->status);
        $this->assertDatabaseHas('provider_events', [
            'gateway' => 'taramoney',
            'provider_event_id' => 'payment-1',
            'status' => 'processed',
        ]);
    }

    public function test_unsigned_webhook_is_settled_only_after_provider_confirmation(): void
    {
        Http::fake(['www.dklo.co/*' => Http::response([
            'status' => 'SUCCESS',
            'payload' => json_encode($this->paidPayload()),
        ])]);

        $this->postJson('/api/payments/transactions/webhook/taramoney/'.$this->settings->environment_id, $this->paidPayload())
            ->assertSuccessful();

        $this->assertSame(Transaction::STATUS_COMPLETED, $this->transaction->fresh()->status);
    }

    public function test_spoofed_success_webhook_cannot_settle_without_matching_provider_payment(): void
    {
        Http::fake(['www.dklo.co/*' => Http::response([
            'status' => 'SUCCESS',
            'payload' => json_encode(array_replace($this->paidPayload(), ['amount' => '2000'])),
        ])]);

        $this->postJson('/api/payments/transactions/webhook/taramoney/'.$this->settings->environment_id, $this->paidPayload())
            ->assertStatus(409);

        $this->assertSame(Transaction::STATUS_PENDING, $this->transaction->fresh()->status);
    }
}
