<?php

namespace Tests\Feature;

use App\Events\OrderCompleted;
use App\Listeners\ProcessOrderItems;
use App\Models\Environment;
use App\Models\Order;
use App\Models\SalesForm;
use App\Models\SalesFormSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class SalesFormOrderCompletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_submission_completes_only_after_all_its_orders_complete(): void
    {
        $user = User::factory()->create();
        $environment = Environment::factory()->create(['owner_id' => $user->id]);
        $form = SalesForm::withoutGlobalScopes()->create([
            'environment_id' => $environment->id,
            'created_by' => $user->id,
            'title' => 'Paid form',
            'slug' => 'paid-form',
            'status' => SalesForm::STATUS_PUBLISHED,
        ]);
        $submission = SalesFormSubmission::withoutGlobalScopes()->create([
            'sales_form_id' => $form->id,
            'environment_id' => $environment->id,
            'user_id' => $user->id,
            'access_code' => 'PAID1234',
            'answers' => [],
            'status' => SalesFormSubmission::STATUS_PENDING,
        ]);
        $first = $this->order($user, $environment, $submission, 'ORD-FIRST');
        $second = $this->order($user, $environment, $submission, 'ORD-SECOND');

        $listener = new ProcessOrderItems;
        $listener->handle(new OrderCompleted($first));
        $this->assertSame(SalesFormSubmission::STATUS_PENDING, $submission->fresh()->status);

        $listener->handle(new OrderCompleted($second));
        $this->assertSame(SalesFormSubmission::STATUS_COMPLETED, $submission->fresh()->status);
    }

    public function test_staff_manual_completion_dispatches_order_completed_event(): void
    {
        Event::fake([OrderCompleted::class]);

        $owner = User::factory()->create();
        $environment = Environment::factory()->create(['owner_id' => $owner->id]);
        $order = Order::withoutGlobalScopes()->create([
            'user_id' => $owner->id,
            'environment_id' => $environment->id,
            'order_number' => 'ORD-MANUAL-COMPLETE',
            'type' => Order::TYPE_SALES_FORM,
            'status' => Order::STATUS_PENDING,
            'total_amount' => 10,
            'currency' => 'USD',
            'billing_email' => 'buyer@example.com',
        ]);

        $this->withoutMiddleware();
        $this->actingAs($owner);

        $this->putJson('/api/orders/'.$order->id.'/status', ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        Event::assertDispatched(OrderCompleted::class, fn (OrderCompleted $event) => $event->order->id === $order->id);
    }

    private function order(User $user, Environment $environment, SalesFormSubmission $submission, string $number): Order
    {
        return Order::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'environment_id' => $environment->id,
            'sales_form_submission_id' => $submission->id,
            'order_number' => $number,
            'type' => Order::TYPE_SALES_FORM,
            'status' => Order::STATUS_PENDING,
            'total_amount' => 10,
            'currency' => 'USD',
        ]);
    }
}
