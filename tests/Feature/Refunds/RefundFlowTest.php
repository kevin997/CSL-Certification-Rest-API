<?php

namespace Tests\Feature\Refunds;

use App\Enums\UserRole;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Environment;
use App\Models\InstructorCommission;
use App\Models\Order;
use App\Models\PaymentGatewaySetting;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Template;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PaymentGateways\PaymentGatewayFactory;
use App\Services\PaymentGateways\PaymentGatewayInterface;
use App\Services\Payments\RefundService;
use App\Services\Payments\WebhookProcessor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * KURSA licensing transition — Phase 5 (doc §9.9 refund flow + chargebacks,
 * §14 "Refunds and disputes" acceptance tests).
 *
 * Gateways are never hit for real: unsupported gateways short-circuit before any
 * network call, and the one supported-gateway test registers an in-memory fake
 * via PaymentGatewayFactory::fake().
 */
class RefundFlowTest extends TestCase
{
    use RefreshDatabase;

    private Environment $environment;
    private User $admin;
    private User $learner;
    private PaymentGatewaySetting $gateway;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create(['role' => UserRole::ADMIN]);
        $this->learner = User::factory()->create(['role' => UserRole::LEARNER]);

        $this->environment = Environment::create([
            'name' => 'Refund Env',
            'primary_domain' => 'refund.example.com',
            'slug' => 'refund-env',
            'owner_id' => $this->admin->id,
        ]);

        $this->gateway = PaymentGatewaySetting::create([
            'environment_id' => $this->environment->id,
            'gateway_name' => 'Lygos',
            'code' => 'lygos',
            'status' => true,
            'is_default' => true,
            'mode' => 'sandbox',
        ]);
    }

    protected function tearDown(): void
    {
        PaymentGatewayFactory::clearFakes();
        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function completedParent(array $overrides = []): Transaction
    {
        return Transaction::create(array_merge([
            'transaction_id' => (string) Str::uuid(),
            'environment_id' => $this->environment->id,
            'payment_gateway_setting_id' => $this->gateway->id,
            'customer_id' => $this->learner->id,
            'customer_email' => $this->learner->email,
            'customer_name' => $this->learner->name,
            'amount' => 100,
            'total_amount' => 100,
            'currency' => 'USD',
            'status' => Transaction::STATUS_COMPLETED,
            'purpose' => Transaction::PURPOSE_COURSE_SALE,
            'payment_method' => 'lygos',
            'gateway_transaction_id' => 'pi_' . Str::random(12),
            'paid_at' => now(),
            'verified_at' => now(),
        ], $overrides));
    }

    /**
     * Builds a purchased course: course + product + product_courses + order +
     * order_item + a completed parent transaction + an active enrollment.
     *
     * @return array{0:Order,1:Course,2:Enrollment,3:Transaction}
     */
    private function purchasedCourse(): array
    {
        $category = ProductCategory::create([
            'name' => 'Cat', 'slug' => 'cat-' . Str::random(5),
            'environment_id' => $this->environment->id, 'created_by' => $this->admin->id,
        ]);

        $template = Template::create([
            'title' => 'Tpl', 'environment_id' => $this->environment->id,
            'created_by' => $this->admin->id,
        ]);

        $course = Course::create([
            'title' => 'Course', 'slug' => 'course-' . Str::random(5),
            'description' => 'x', 'environment_id' => $this->environment->id,
            'created_by' => $this->admin->id, 'status' => 'published',
            'template_id' => $template->id,
        ]);

        $product = Product::create([
            'name' => 'Product', 'description' => 'x', 'price' => 100.0, 'currency' => 'USD',
            'is_subscription' => false, 'is_free' => false, 'status' => 'active',
            'category_id' => $category->id, 'sku' => 'SKU-' . Str::random(5),
            'environment_id' => $this->environment->id, 'created_by' => $this->admin->id,
        ]);

        \DB::table('product_courses')->insert([
            'product_id' => $product->id, 'course_id' => $course->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $order = Order::create([
            'user_id' => $this->learner->id,
            'environment_id' => $this->environment->id,
            'order_number' => 'ORD-' . Str::random(6),
            'status' => Order::STATUS_COMPLETED,
            'total_amount' => 100.0,
            'currency' => 'USD',
            'payment_method' => 'lygos',
            'billing_name' => $this->learner->name,
            'billing_email' => $this->learner->email,
        ]);

        \DB::table('order_items')->insert([
            'order_id' => $order->id, 'product_id' => $product->id,
            'quantity' => 1, 'price' => 100.0, 'total' => 100.0,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $transaction = $this->completedParent(['order_id' => $order->id]);

        $enrollment = Enrollment::create([
            'user_id' => $this->learner->id,
            'course_id' => $course->id,
            'environment_id' => $this->environment->id,
            'status' => Enrollment::STATUS_ENROLLED,
        ]);

        return [$order, $course, $enrollment, $transaction];
    }

    private function fakeSupportedGateway(bool $confirmed = true): PaymentGatewayInterface
    {
        return new class($confirmed) implements PaymentGatewayInterface {
            public function __construct(private bool $confirmed) {}
            public function initialize(PaymentGatewaySetting $settings): void {}
            public function createPayment(Transaction $t, array $d = []): array { return []; }
            public function processPayment(Transaction $t, array $d = []): array { return []; }
            public function verifyPayment(string $id): array { return []; }
            public function supportsRefunds(): bool { return true; }
            public function processRefund(Transaction $t, ?float $a = null, string $r = ''): array
            {
                return [
                    'success' => true,
                    'refund_id' => 're_' . substr(md5((string) microtime(true)), 0, 12),
                    'confirmed' => $this->confirmed,
                    'status' => $this->confirmed ? 'succeeded' : 'pending',
                    'response' => ['object' => 'refund'],
                ];
            }
            public function getConfig(): array { return []; }
            public function verifyWebhookSignature($p, string $s, string $sec): bool { return true; }
            public function createInvoicePaymentLink(\App\Models\Invoice $i) { return ''; }
        };
    }

    // ------------------------------------------------------------------
    // Tests
    // ------------------------------------------------------------------

    /** @test */
    public function partial_refunds_accumulate_and_only_cumulative_full_flips_parent_to_refunded()
    {
        $parent = $this->completedParent();
        $service = app(RefundService::class);

        // First $30 → partial.
        $service->recordManualRefund($parent, 30, 'first', 'note');
        $parent->refresh();
        $this->assertSame(Transaction::STATUS_PARTIALLY_REFUNDED, $parent->status);
        $this->assertEqualsWithDelta(30.0, $parent->refundedAmount([Transaction::STATUS_COMPLETED]), 0.001);

        // Second $30 → still partial ($60 of $100).
        $service->recordManualRefund($parent, 30, 'second', 'note');
        $parent->refresh();
        $this->assertSame(Transaction::STATUS_PARTIALLY_REFUNDED, $parent->status);

        // Final $40 → cumulative $100 → refunded.
        $service->recordManualRefund($parent, 40, 'final', 'note');
        $parent->refresh();
        $this->assertSame(Transaction::STATUS_REFUNDED, $parent->status);
        $this->assertEqualsWithDelta(100.0, $parent->refundedAmount([Transaction::STATUS_COMPLETED]), 0.001);

        // Three immutable child refund rows.
        $this->assertSame(3, Transaction::withoutGlobalScopes()
            ->where('parent_transaction_id', $parent->transaction_id)
            ->where('purpose', Transaction::PURPOSE_REFUND)
            ->count());
    }

    /** @test */
    public function refund_cannot_exceed_the_original_amount()
    {
        $parent = $this->completedParent();
        $service = app(RefundService::class);

        // Over-refund in one shot.
        $result = $service->recordManualRefund($parent, 150, 'too much', 'note');
        $this->assertSame('invalid', $result['status']);

        // Partial then an over-the-remainder second refund.
        $service->recordManualRefund($parent, 60, 'ok', 'note');
        $result = $service->recordManualRefund($parent, 50, 'exceeds remainder', 'note');
        $this->assertSame('invalid', $result['status']);

        $parent->refresh();
        $this->assertSame(Transaction::STATUS_PARTIALLY_REFUNDED, $parent->status);
    }

    /** @test */
    public function child_refund_transaction_carries_purpose_refund_and_parent_transaction_id()
    {
        $parent = $this->completedParent();

        $result = app(RefundService::class)->recordManualRefund($parent, 40, 'reason', 'note');

        $child = $result['transaction'];
        $this->assertSame(Transaction::PURPOSE_REFUND, $child->purpose);
        $this->assertSame($parent->transaction_id, $child->parent_transaction_id);
        $this->assertEqualsWithDelta(-40.0, (float) $child->amount, 0.001);
        $this->assertNotNull($child->verified_at);
    }

    /** @test */
    public function supported_gateway_refund_creates_a_confirmed_child_via_the_http_route()
    {
        PaymentGatewayFactory::fake('lygos', $this->fakeSupportedGateway(confirmed: true));

        $parent = $this->completedParent();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/transactions/{$parent->id}/refund", ['amount' => 100, 'reason' => 'full']);

        $response->assertOk();
        $response->assertJson(['confirmed' => true]);

        $parent->refresh();
        $this->assertSame(Transaction::STATUS_REFUNDED, $parent->status);
    }

    /** @test */
    public function unsupported_gateway_returns_409_and_the_manual_path_works()
    {
        // Real Lygos gateway → supportsRefunds() === false (no fake registered).
        $parent = $this->completedParent();

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/transactions/{$parent->id}/refund", ['amount' => 100, 'reason' => 'x']);

        $response->assertStatus(409);
        $response->assertJson(['unsupported' => true]);
        $this->assertNotNull($response->json('manual_instructions'));

        // Parent untouched — no money moved.
        $this->assertSame(Transaction::STATUS_COMPLETED, $parent->fresh()->status);

        // Manual path records the out-of-band refund.
        $manual = $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/transactions/{$parent->id}/refund/manual", [
                'amount' => 100, 'reason' => 'x', 'notes' => 'refunded in the Lygos dashboard',
            ]);

        $manual->assertOk();
        $this->assertSame(Transaction::STATUS_REFUNDED, $parent->fresh()->status);

        $child = Transaction::withoutGlobalScopes()
            ->where('parent_transaction_id', $parent->transaction_id)
            ->where('purpose', Transaction::PURPOSE_REFUND)
            ->first();
        $this->assertSame('manual', $child->payment_method);
    }

    /** @test */
    public function manual_refund_requires_notes()
    {
        $parent = $this->completedParent();

        $this->actingAs($this->admin, 'sanctum')
            ->postJson("/api/transactions/{$parent->id}/refund/manual", ['amount' => 50, 'reason' => 'x'])
            ->assertStatus(422);
    }

    /** @test */
    public function a_non_admin_cannot_issue_a_refund()
    {
        $parent = $this->completedParent();

        $this->actingAs($this->learner, 'sanctum')
            ->postJson("/api/transactions/{$parent->id}/refund", ['amount' => 10])
            ->assertStatus(403);
    }

    /** @test */
    public function full_course_refund_unenrolls_the_learner()
    {
        [$order, $course, $enrollment, $parent] = $this->purchasedCourse();

        app(RefundService::class)->recordManualRefund($parent, 100, 'full', 'note');

        $this->assertSame(Enrollment::STATUS_DROPPED, $enrollment->fresh()->status);
        $this->assertSame(Order::STATUS_REFUNDED, $order->fresh()->status);
    }

    /** @test */
    public function partial_course_refund_keeps_enrollment_and_order_completed()
    {
        [$order, $course, $enrollment, $parent] = $this->purchasedCourse();

        app(RefundService::class)->recordManualRefund($parent, 40, 'partial', 'note');

        $this->assertSame(Enrollment::STATUS_ENROLLED, $enrollment->fresh()->status);
        $this->assertSame(Order::STATUS_PARTIALLY_REFUNDED, $order->fresh()->status);
    }

    /** @test */
    public function commission_is_reversed_on_refund_confirmation()
    {
        $parent = $this->completedParent();

        $commission = InstructorCommission::create([
            'environment_id' => $this->environment->id,
            'transaction_id' => $parent->id,
            'gross_amount' => 100,
            'platform_fee_rate' => 0,
            'platform_fee_amount' => 0,
            'instructor_payout_amount' => 100,
            'currency' => 'USD',
            'status' => InstructorCommission::STATUS_PENDING,
        ]);

        app(RefundService::class)->recordManualRefund($parent, 100, 'full', 'note');

        $this->assertSame(InstructorCommission::STATUS_REVERSED, $commission->fresh()->status);
    }

    /** @test */
    public function an_already_paid_commission_is_not_reversed_but_flagged()
    {
        $parent = $this->completedParent();

        $commission = InstructorCommission::create([
            'environment_id' => $this->environment->id,
            'transaction_id' => $parent->id,
            'gross_amount' => 100,
            'platform_fee_rate' => 0,
            'platform_fee_amount' => 0,
            'instructor_payout_amount' => 100,
            'currency' => 'USD',
            'status' => InstructorCommission::STATUS_PAID,
        ]);

        $result = app(RefundService::class)->recordManualRefund($parent, 100, 'full', 'note');

        // Not clawed back...
        $this->assertSame(InstructorCommission::STATUS_PAID, $commission->fresh()->status);
        // ...but flagged for manual reconciliation.
        $this->assertNotEmpty($result['effects']['commissions']['flagged_paid']);
        $this->assertStringContainsString('NEGATIVE ADJUSTMENT', $commission->fresh()->notes);
    }

    /** @test */
    public function a_processing_refund_is_confirmed_by_the_gateway_webhook_event()
    {
        // Simulate initiateRefund's gateway step producing an unconfirmed child.
        $parent = $this->completedParent();
        $child = Transaction::create([
            'transaction_id' => (string) Str::uuid(),
            'environment_id' => $this->environment->id,
            'payment_gateway_setting_id' => $this->gateway->id,
            'purpose' => Transaction::PURPOSE_REFUND,
            'parent_transaction_id' => $parent->transaction_id,
            'amount' => -100,
            'total_amount' => -100,
            'currency' => 'USD',
            'status' => Transaction::STATUS_PROCESSING,
            'payment_method' => 'lygos',
        ]);

        app(WebhookProcessor::class)->confirmRefundEvent(
            $parent,
            100,
            ['id' => 'evt_refund_1', 'object' => 'refund', 'amount' => 10000],
            ['gateway' => 'lygos']
        );

        $this->assertSame(Transaction::STATUS_COMPLETED, $child->fresh()->status);
        $this->assertNotNull($child->fresh()->verified_at);
        $this->assertSame(Transaction::STATUS_REFUNDED, $parent->fresh()->status);
    }

    /** @test */
    public function a_dispute_event_sets_disputed_and_cannot_be_regressed_by_a_stale_success()
    {
        $parent = $this->completedParent();

        $commission = InstructorCommission::create([
            'environment_id' => $this->environment->id,
            'transaction_id' => $parent->id,
            'gross_amount' => 100,
            'platform_fee_rate' => 0,
            'platform_fee_amount' => 0,
            'instructor_payout_amount' => 100,
            'currency' => 'USD',
            'status' => InstructorCommission::STATUS_PENDING,
        ]);

        $processor = app(WebhookProcessor::class);

        // Dispute opened → disputed + settlement hold.
        $processor->dispute($parent, 'opened', ['id' => 'dp_1', 'charge' => $parent->gateway_transaction_id], ['gateway' => 'lygos']);
        $this->assertSame(Transaction::STATUS_DISPUTED, $parent->fresh()->status);
        $this->assertSame(InstructorCommission::STATUS_ON_HOLD, $commission->fresh()->status);

        // A stale success event MUST NOT regress the disputed transaction.
        $result = $processor->settle(
            $parent,
            'completed',
            'success',
            ['id' => 'evt_stale_success', 'amount' => 100, 'currency' => 'USD'],
            null,
            ['gateway' => 'lygos']
        );

        $this->assertSame('skipped', $result);
        $this->assertSame(Transaction::STATUS_DISPUTED, $parent->fresh()->status);
    }

    /** @test */
    public function a_dispute_won_restores_completed_and_releases_the_hold()
    {
        $parent = $this->completedParent();
        $commission = InstructorCommission::create([
            'environment_id' => $this->environment->id,
            'transaction_id' => $parent->id,
            'gross_amount' => 100, 'platform_fee_rate' => 0, 'platform_fee_amount' => 0,
            'instructor_payout_amount' => 100, 'currency' => 'USD',
            'status' => InstructorCommission::STATUS_PENDING,
        ]);

        $processor = app(WebhookProcessor::class);
        $processor->dispute($parent, 'opened', ['id' => 'dp_2'], ['gateway' => 'lygos']);
        $processor->dispute($parent, 'won', ['id' => 'dp_2_won'], ['gateway' => 'lygos']);

        $this->assertSame(Transaction::STATUS_COMPLETED, $parent->fresh()->status);
        $this->assertSame(InstructorCommission::STATUS_PENDING, $commission->fresh()->status);
    }

    /** @test */
    public function a_dispute_lost_reverses_settlement_as_a_full_refund()
    {
        [$order, $course, $enrollment, $parent] = $this->purchasedCourse();

        $processor = app(WebhookProcessor::class);
        $processor->dispute($parent, 'opened', ['id' => 'dp_3'], ['gateway' => 'lygos']);
        $processor->dispute($parent, 'lost', ['id' => 'dp_3_lost'], ['gateway' => 'lygos']);

        $this->assertSame(Transaction::STATUS_REFUNDED, $parent->fresh()->status);
        $this->assertSame(Enrollment::STATUS_DROPPED, $enrollment->fresh()->status);
    }
}
