<?php

namespace Tests\Feature\Payments;

use App\Models\AuditLog;
use App\Models\Environment;
use App\Models\PaymentGatewaySetting;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PaymentGateways\AuditedPaymentGateway;
use App\Services\PaymentGateways\PaymentGatewayInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

/**
 * Audit logging is observability: a broken audit row must never turn a created
 * payment link into an error response for the customer.
 */
class PaymentAuditResilienceTest extends TestCase
{
    use RefreshDatabase;

    private Environment $environment;

    private PaymentGatewaySetting $gatewaySetting;

    private Transaction $transaction;

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
            'gateway_name' => 'TaraMoney',
            'code' => 'taramoney',
            'status' => true,
            'is_default' => true,
            'mode' => 'sandbox',
        ]);

        $this->transaction = Transaction::create([
            'transaction_id' => 'TXN-audit-test',
            'environment_id' => $this->environment->id,
            'payment_gateway_setting_id' => $this->gatewaySetting->id,
            'amount' => 10.50,
            'total_amount' => 12.52,
            'currency' => 'USD',
            'status' => Transaction::STATUS_PENDING,
        ]);
    }

    public function test_audit_log_failure_does_not_fail_the_payment(): void
    {
        $this->forceAuditInsertFailure();
        Log::spy();

        $gateway = $this->fakeGateway([
            'success' => true,
            'message' => 'Payment link created successfully.',
            'transaction_id' => 'TXN-audit-test',
            'status' => 'pending',
            'redirect_url' => 'https://taramoney.com/pay/42',
        ]);

        $audited = new AuditedPaymentGateway($gateway, $this->gatewaySetting);
        $result = $audited->createPayment($this->transaction, ['payment_method' => 'taramoney']);

        $this->assertTrue($result['success']);
        $this->assertSame('https://taramoney.com/pay/42', $result['redirect_url']);

        Log::shouldHaveReceived('warning')
            ->withArgs(fn (string $message) => str_contains($message, 'audit logging failed'))
            ->once();
    }

    public function test_audit_log_failure_does_not_mask_a_gateway_error(): void
    {
        $this->forceAuditInsertFailure();

        $gateway = $this->createMock(PaymentGatewayInterface::class);
        $gateway->method('createPayment')->willThrowException(new RuntimeException('gateway down'));

        $audited = new AuditedPaymentGateway($gateway, $this->gatewaySetting);

        try {
            $audited->createPayment($this->transaction);
            $this->fail('The gateway exception should have propagated.');
        } catch (RuntimeException $e) {
            $this->assertSame('gateway down', $e->getMessage());
        }
    }

    public function test_audit_log_user_agent_is_clamped_to_the_historic_column_width(): void
    {
        request()->headers->set('User-Agent', str_repeat('a', 300));

        $log = AuditLog::logPaymentGatewayOperation('payment_gateway:taramoney', 'create_payment');

        $this->assertSame(191, strlen($log->user_agent));
        $this->assertSame(str_repeat('a', 191), $log->user_agent);
    }

    private function fakeGateway(array $response): PaymentGatewayInterface
    {
        $gateway = $this->createMock(PaymentGatewayInterface::class);
        $gateway->method('createPayment')->willReturn($response);

        return $gateway;
    }

    private function forceAuditInsertFailure(): void
    {
        $event = 'eloquent.creating: '.AuditLog::class;

        AuditLog::creating(function (): void {
            throw new RuntimeException('audit insert failed');
        });

        $this->beforeApplicationDestroyed(function () use ($event) {
            AuditLog::getEventDispatcher()?->forget($event);
        });
    }
}
