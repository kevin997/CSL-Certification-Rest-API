<?php

namespace Tests\Feature\Commission;

use App\Models\Commission;
use App\Models\Course;
use App\Models\EnrollmentCode;
use App\Models\Environment;
use App\Models\EnvironmentPaymentConfig;
use App\Models\InstructorCommission;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Template;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Commission\CommissionService;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * PHASE 2 — KURSA takes 0% of course sales on every plan.
 *
 * These tests lock in the new behaviour introduced by the licensing transition:
 *  (a) CommissionService::extractCommissionFromProductPrice returns fee 0 and
 *      the price unchanged, whether or not an active Commission record exists.
 *  (b) A completed course transaction carries fee_amount 0 and creates NO
 *      InstructorCommission (payout liability) row, even under the centralized-
 *      gateway conditions that previously created one.
 *  (c) An enrollment-code redemption of a PRICED product creates no
 *      InstructorCommission row.
 */
class ZeroCommissionTest extends TestCase
{
    use RefreshDatabase;

    // ---------------------------------------------------------------------
    // (a) extractCommissionFromProductPrice — fee 0, price unchanged
    // ---------------------------------------------------------------------

    /** @test */
    public function extract_commission_returns_zero_fee_and_unchanged_price_without_any_commission_record()
    {
        // No Commission rows exist at all.
        $service = app(CommissionService::class);

        $result = $service->extractCommissionFromProductPrice(117.0, null);

        $this->assertEquals(0.0, $result['commission_rate']);
        $this->assertEqualsWithDelta(117.0, $result['original_price'], 0.001);
        $this->assertEqualsWithDelta(0.0, $result['commission_amount'], 0.001);
    }

    /** @test */
    public function extract_commission_returns_zero_fee_and_unchanged_price_even_with_active_commission_record()
    {
        $environment = Environment::create([
            'name' => 'Env A',
            'primary_domain' => 'a.example.com',
            'slug' => 'env-a',
            'owner_id' => User::factory()->create()->id,
        ]);

        // A live 17% commission record — the OLD flow would have extracted it.
        Commission::create([
            'environment_id' => $environment->id,
            'name' => 'Legacy 17% commission',
            'rate' => 17.0,
            'is_active' => true,
        ]);

        $service = app(CommissionService::class);

        $result = $service->extractCommissionFromProductPrice(117.0, $environment->id);

        // Phase 2: the active record is ignored for course sales — still 0.
        $this->assertEquals(0.0, $result['commission_rate']);
        $this->assertEqualsWithDelta(117.0, $result['original_price'], 0.001);
        $this->assertEqualsWithDelta(0.0, $result['commission_amount'], 0.001);
    }

    /** @test */
    public function calculate_transaction_amounts_with_commission_included_yields_zero_fee_even_with_active_commission()
    {
        $environment = Environment::create([
            'name' => 'Env B',
            'primary_domain' => 'b.example.com',
            'slug' => 'env-b',
            'owner_id' => User::factory()->create()->id,
        ]);

        Commission::create([
            'environment_id' => $environment->id,
            'name' => 'Legacy 17% commission',
            'rate' => 17.0,
            'is_active' => true,
        ]);

        $service = app(CommissionService::class);

        $amounts = $service->calculateTransactionAmountsWithCommissionIncluded(100.0, $environment->id);

        // Platform fee is always 0; the price is the base as-is (no reverse calc).
        $this->assertEqualsWithDelta(0.0, $amounts['fee_amount'], 0.001);
        $this->assertEqualsWithDelta(100.0, $amounts['base_amount'], 0.001);
        $this->assertEquals(0.0, $amounts['commission_rate']);
        // Total = price + tax; with no tax zone configured tax is 0 → total 100.
        $this->assertEqualsWithDelta(100.0 + $amounts['tax_amount'], $amounts['total_amount'], 0.001);
    }

    // ---------------------------------------------------------------------
    // (b) completed course transaction → fee 0 + no InstructorCommission
    // ---------------------------------------------------------------------

    /** @test */
    public function completed_course_transaction_has_zero_fee_and_creates_no_instructor_commission()
    {
        $user = User::factory()->create();

        $environment = Environment::create([
            'name' => 'Env C',
            'primary_domain' => 'c.example.com',
            'slug' => 'env-c',
            'owner_id' => $user->id,
        ]);

        // Centralized gateways + an active 17% commission record: this is the
        // exact configuration that PREVIOUSLY produced an InstructorCommission.
        EnvironmentPaymentConfig::create([
            'environment_id' => $environment->id,
            'use_centralized_gateways' => true,
            'platform_fee_rate' => 0,
            'payment_terms' => 'NET_30',
            'is_active' => true,
        ]);

        Commission::create([
            'environment_id' => $environment->id,
            'name' => 'Legacy 17% commission',
            'rate' => 17.0,
            'is_active' => true,
        ]);

        // The money path stamps the fee onto the transaction — must be 0.
        $amounts = app(CommissionService::class)
            ->calculateTransactionAmountsWithCommissionIncluded(200.0, $environment->id);

        $order = Order::create([
            'user_id' => $user->id,
            'environment_id' => $environment->id,
            'order_number' => 'ORD-ZEROCOMM-1',
            'status' => Order::STATUS_COMPLETED,
            'total_amount' => 200.0,
            'currency' => 'USD',
            'payment_method' => 'stripe',
            'billing_name' => $user->name,
            'billing_email' => $user->email,
        ]);

        $transaction = Transaction::create([
            'order_id' => $order->id,
            'environment_id' => $environment->id,
            'customer_id' => $user->id,
            'customer_email' => $user->email,
            'customer_name' => $user->name,
            'amount' => 200.0,
            'fee_amount' => $amounts['fee_amount'],
            'tax_amount' => $amounts['tax_amount'],
            'total_amount' => $amounts['total_amount'],
            'currency' => 'USD',
            'status' => Transaction::STATUS_COMPLETED,
            'payment_method' => 'stripe',
            'description' => 'Course sale',
            'paid_at' => now(),
        ]);

        $this->assertEqualsWithDelta(0.0, (float) $transaction->fee_amount, 0.001);

        // Drive the (now no-op) commission-record creator that the paid-payment
        // completion path invokes. It must create nothing.
        $paymentService = app(PaymentService::class);
        $method = new \ReflectionMethod(PaymentService::class, 'createCommissionRecordIfNeeded');
        $method->setAccessible(true);
        $method->invoke($paymentService, $transaction);

        $this->assertSame(
            0,
            InstructorCommission::withoutGlobalScopes()->count(),
            'A course sale must not create an InstructorCommission payout liability.'
        );
    }

    // ---------------------------------------------------------------------
    // (c) enrollment-code redemption → no InstructorCommission
    // ---------------------------------------------------------------------

    /** @test */
    public function enrollment_code_redemption_of_a_priced_product_creates_no_instructor_commission()
    {
        $user = User::factory()->create();

        $environment = Environment::create([
            'name' => 'Env D',
            'primary_domain' => 'd.example.com',
            'slug' => 'env-d',
            'owner_id' => $user->id,
        ]);

        $category = ProductCategory::create([
            'name' => 'Cat',
            'slug' => 'cat-d',
            'environment_id' => $environment->id,
            'created_by' => $user->id,
        ]);

        $template = Template::create([
            'title' => 'Paid Course Template',
            'environment_id' => $environment->id,
            'created_by' => $user->id,
        ]);

        $course = Course::create([
            'title' => 'Paid Course',
            'slug' => 'paid-course-d',
            'description' => 'A paid course',
            'environment_id' => $environment->id,
            'created_by' => $user->id,
            'template_id' => $template->id,
            'status' => 'published',
        ]);

        // A PRICED product — the OLD flow created a commission when price > 0.
        $product = Product::create([
            'name' => 'Paid Course Product',
            'description' => 'Paid',
            'price' => 50.0,
            'currency' => 'USD',
            'is_subscription' => false,
            'is_free' => false,
            'status' => 'active',
            'category_id' => $category->id,
            'sku' => 'PAID-D-001',
            'environment_id' => $environment->id,
            'created_by' => $user->id,
        ]);

        DB::table('product_courses')->insert([
            'product_id' => $product->id,
            'course_id' => $course->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $code = EnrollmentCode::create([
            'product_id' => $product->id,
            'code' => 'ABCD',
            'status' => 'active',
            'created_by' => $user->id,
        ]);

        $response = $this->withSession(['current_environment_id' => $environment->id])
            ->actingAs($user)
            ->postJson('/api/enrollment-codes/redeem', [
                'code' => $code->code,
                'product_id' => $product->id,
            ]);

        $response->assertStatus(201);

        // A completed enrollment-code transaction exists and carries fee 0...
        $this->assertDatabaseHas('transactions', [
            'order_id' => Order::where('order_number', 'like', 'ORD-%')->latest('id')->first()->id,
            'payment_method' => 'enrollment_code',
            'status' => Transaction::STATUS_COMPLETED,
            'fee_amount' => 0,
        ]);

        // ...and no payout liability was created.
        $this->assertSame(
            0,
            InstructorCommission::withoutGlobalScopes()->count(),
            'Enrollment-code redemption must not create an InstructorCommission payout liability.'
        );
    }
}
