<?php

namespace Tests\Feature\Payments;

use App\Events\OrderCompleted;
use App\Listeners\ProcessOrderItems;
use App\Models\Environment;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SalesForm;
use App\Models\SalesFormSubmission;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A learner who abandons a mobile-money prompt and retries creates a second
 * sales-form submission and order. Only the paid attempt may be fulfilled and
 * reported to Meta; the abandoned attempt must be cancelled before the
 * provider can settle its stale prompt.
 */
class SupersedeAbandonedOrdersTest extends TestCase
{
    use RefreshDatabase;

    private Environment $environment;

    private User $owner;

    private User $learner;

    private Product $course;

    private Product $otherCourse;

    protected function setUp(): void
    {
        parent::setUp();

        // ProcessOrderItems and SendMetaPurchase are queue listeners; this
        // suite asserts the synchronous supersession behaviour only.
        Queue::fake();

        $this->owner = User::factory()->create();
        $this->environment = Environment::factory()->create(['owner_id' => $this->owner->id]);
        $this->learner = User::factory()->create(['email' => 'assani1rdc@gmail.com']);

        $this->course = $this->product('Media Buying Bootcamp', 6);
        $this->otherCourse = $this->product('Other Bootcamp', 9);
    }

    public function test_a_paid_retry_cancels_the_older_pending_attempt_for_the_same_cart(): void
    {
        $abandoned = $this->order($this->submission(), $this->course, Order::STATUS_PENDING);
        $abandonedTransaction = $this->transaction($abandoned, Transaction::STATUS_PENDING);
        $abandonedPayment = $this->payment($abandonedTransaction, Payment::STATUS_PENDING);

        $paid = $this->order($this->submission(), $this->course, Order::STATUS_COMPLETED);

        event(new OrderCompleted($paid));

        $abandoned->refresh();
        $this->assertSame(Order::STATUS_CANCELLED, $abandoned->status);
        $this->assertStringContainsString($paid->order_number, (string) $abandoned->notes);

        $this->assertSame(Transaction::STATUS_FAILED, $abandonedTransaction->fresh()->status);
        $this->assertSame(Payment::STATUS_FAILED, $abandonedPayment->fresh()->status);

        // The retry that was paid is left for the fulfilment listener.
        $this->assertSame(Order::STATUS_COMPLETED, $paid->fresh()->status);
    }

    public function test_the_abandoned_sales_form_submission_is_closed(): void
    {
        $abandonedSubmission = $this->submission();
        $abandoned = $this->order($abandonedSubmission, $this->course, Order::STATUS_PENDING);
        $this->transaction($abandoned, Transaction::STATUS_PENDING);

        $paid = $this->order($this->submission(), $this->course, Order::STATUS_COMPLETED);

        event(new OrderCompleted($paid));

        $this->assertSame(SalesFormSubmission::STATUS_CANCELLED, $abandonedSubmission->fresh()->status);
    }

    public function test_an_older_pending_attempt_for_a_different_cart_is_kept(): void
    {
        $otherCart = $this->order($this->submission(), $this->otherCourse, Order::STATUS_PENDING);
        $otherTransaction = $this->transaction($otherCart, Transaction::STATUS_PENDING);

        $paid = $this->order($this->submission(), $this->course, Order::STATUS_COMPLETED);

        event(new OrderCompleted($paid));

        $this->assertSame(Order::STATUS_PENDING, $otherCart->fresh()->status);
        $this->assertSame(Transaction::STATUS_PENDING, $otherTransaction->fresh()->status);
    }

    public function test_a_newer_pending_attempt_is_not_superseded(): void
    {
        $paid = $this->order($this->submission(), $this->course, Order::STATUS_COMPLETED);
        $newer = $this->order($this->submission(), $this->course, Order::STATUS_PENDING);

        event(new OrderCompleted($paid));

        $this->assertSame(Order::STATUS_PENDING, $newer->fresh()->status);
    }

    public function test_another_learners_pending_attempt_is_kept(): void
    {
        $otherLearner = User::factory()->create();
        $otherOrder = $this->order($this->submission(), $this->course, Order::STATUS_PENDING, $otherLearner);
        $otherTransaction = $this->transaction($otherOrder, Transaction::STATUS_PENDING);

        $paid = $this->order($this->submission(), $this->course, Order::STATUS_COMPLETED);

        event(new OrderCompleted($paid));

        $this->assertSame(Order::STATUS_PENDING, $otherOrder->fresh()->status);
        $this->assertSame(Transaction::STATUS_PENDING, $otherTransaction->fresh()->status);
    }

    public function test_storefront_pending_orders_are_not_touched(): void
    {
        $storefrontOrder = $this->order($this->submission(), $this->course, Order::STATUS_PENDING);
        $storefrontOrder->forceFill(['type' => Order::TYPE_STOREFRONT])->save();
        $storefrontTransaction = $this->transaction($storefrontOrder, Transaction::STATUS_PENDING);

        $paid = $this->order($this->submission(), $this->course, Order::STATUS_COMPLETED);

        event(new OrderCompleted($paid));

        $this->assertSame(Order::STATUS_PENDING, $storefrontOrder->fresh()->status);
        $this->assertSame(Transaction::STATUS_PENDING, $storefrontTransaction->fresh()->status);
    }

    public function test_a_superseded_order_is_never_fulfilled_by_a_late_completion_event(): void
    {
        $submission = $this->submission();
        $order = $this->order($submission, $this->course, Order::STATUS_CANCELLED);

        (new ProcessOrderItems)->handle(new OrderCompleted($order));

        $this->assertSame(Order::STATUS_CANCELLED, $order->fresh()->status);
        $this->assertSame(SalesFormSubmission::STATUS_PENDING, $submission->fresh()->status);
    }

    public function test_the_backfill_command_cancels_an_existing_abandoned_attempt(): void
    {
        $abandoned = $this->order($this->submission(), $this->course, Order::STATUS_PENDING);
        $abandonedTransaction = $this->transaction($abandoned, Transaction::STATUS_PENDING);

        $paid = $this->order($this->submission(), $this->course, Order::STATUS_COMPLETED);

        $this->artisan('orders:supersede-abandoned', ['--order' => $paid->id])
            ->assertSuccessful();

        $this->assertSame(Order::STATUS_CANCELLED, $abandoned->fresh()->status);
        $this->assertSame(Transaction::STATUS_FAILED, $abandonedTransaction->fresh()->status);
    }

    public function test_the_backfill_command_scans_recent_completed_orders_by_default(): void
    {
        $abandoned = $this->order($this->submission(), $this->course, Order::STATUS_PENDING);
        $this->transaction($abandoned, Transaction::STATUS_PENDING);

        $this->order($this->submission(), $this->course, Order::STATUS_COMPLETED);

        $this->artisan('orders:supersede-abandoned')->assertSuccessful();

        $this->assertSame(Order::STATUS_CANCELLED, $abandoned->fresh()->status);
    }

    private function product(string $name, float $price): Product
    {
        return Product::withoutGlobalScopes()->create([
            'name' => $name,
            'price' => $price,
            'currency' => 'USD',
            'status' => 'active',
            'environment_id' => $this->environment->id,
            'created_by' => $this->owner->id,
        ]);
    }

    private function submission(): SalesFormSubmission
    {
        $form = SalesForm::withoutGlobalScopes()->create([
            'environment_id' => $this->environment->id,
            'created_by' => $this->owner->id,
            'title' => 'Paid form',
            'slug' => 'paid-form-'.Str::lower(Str::random(6)),
            'status' => SalesForm::STATUS_PUBLISHED,
        ]);

        return SalesFormSubmission::withoutGlobalScopes()->create([
            'sales_form_id' => $form->id,
            'environment_id' => $this->environment->id,
            'user_id' => $this->learner->id,
            'access_code' => Str::upper(Str::random(8)),
            'answers' => [],
            'status' => SalesFormSubmission::STATUS_PENDING,
        ]);
    }

    private function order(
        SalesFormSubmission $submission,
        Product $product,
        string $status,
        ?User $learner = null,
    ): Order {
        $learner ??= $this->learner;

        $order = Order::withoutGlobalScopes()->create([
            'user_id' => $learner->id,
            'environment_id' => $this->environment->id,
            'sales_form_submission_id' => $submission->id,
            'order_number' => 'ORD-'.Str::upper(Str::random(8)),
            'type' => Order::TYPE_SALES_FORM,
            'status' => $status,
            'total_amount' => 6,
            'currency' => 'USD',
            'billing_name' => $learner->name,
            'billing_email' => $learner->email,
        ]);

        DB::table('order_items')->insert([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => $product->price,
            'discount' => 0,
            'total' => $product->price,
            'is_subscription' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $order;
    }

    private function transaction(Order $order, string $status): Transaction
    {
        return Transaction::withoutGlobalScopes()->create([
            'environment_id' => $this->environment->id,
            'order_id' => $order->id,
            'transaction_id' => (string) Str::uuid(),
            'amount' => 6,
            'total_amount' => 7.16,
            'currency' => 'USD',
            'status' => $status,
            'payment_method' => 'taramoney',
            'purpose' => 'course_sale',
            'gateway_transaction_id' => 'TXN-'.Str::upper(Str::random(12)),
        ]);
    }

    private function payment(Transaction $transaction, string $status): Payment
    {
        return Payment::withoutGlobalScopes()->create([
            'user_id' => $this->learner->id,
            'subscription_id' => 0,
            'transaction_id' => $transaction->transaction_id,
            'amount' => 6,
            'total_amount' => 7.16,
            'currency' => 'USD',
            'status' => $status,
            'payment_method' => 'taramoney',
        ]);
    }
}
