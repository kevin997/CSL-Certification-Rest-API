<?php

namespace Tests\Feature\Api;

use App\Models\Environment;
use App\Models\Order;
use App\Models\SalesForm;
use App\Models\SalesFormSubmission;
use App\Models\User;
use App\Support\Sales\SalesFormCheckoutToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class OrderPaymentLinkTest extends TestCase
{
    use RefreshDatabase;

    private function order(Environment $academy, array $data = []): Order
    {
        return Order::withoutGlobalScopes()->create(array_replace([
            'user_id' => User::factory()->create()->id,
            'environment_id' => $academy->id,
            'order_number' => 'ORD-'.Str::random(8),
            'type' => Order::TYPE_STOREFRONT,
            'status' => Order::STATUS_PENDING,
            'total_amount' => 1200,
            'currency' => 'XAF',
        ], $data));
    }

    private function link(Order $order, Environment $academy)
    {
        return $this->postJson("/api/orders/{$order->id}/payment-link", [], [
            'X-Frontend-Domain' => $academy->primary_domain,
        ]);
    }

    public function test_staff_can_generate_a_guest_link_bound_to_receiving_order(): void
    {
        $owner = User::factory()->create();
        $customer = User::factory()->create();
        $academy = Environment::factory()->create(['primary_domain' => 'my-academy.example', 'owner_id' => $owner->id]);
        $form = SalesForm::withoutGlobalScopes()->create([
            'title' => 'Form', 'slug' => 'form', 'environment_id' => $academy->id,
            'created_by' => $owner->id, 'status' => SalesForm::STATUS_PUBLISHED,
            'settings' => ['payment_gateway_code' => 'tara'],
        ]);
        $submission = SalesFormSubmission::withoutGlobalScopes()->create([
            'sales_form_id' => $form->id, 'environment_id' => $academy->id,
            'user_id' => $customer->id, 'access_code' => 'ABCD1234',
            'answers' => [], 'status' => SalesFormSubmission::STATUS_PENDING,
        ]);
        $order = $this->order($academy, [
            'user_id' => $customer->id, 'type' => Order::TYPE_SALES_FORM,
            'sales_form_submission_id' => $submission->id,
        ]);
        Sanctum::actingAs($owner);
        $link = $this->link($order, $academy)->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->json('data.url');
        parse_str(parse_url($link, PHP_URL_QUERY), $query);

        $this->assertSame("/checkout/continue-payment/{$order->id}", parse_url($link, PHP_URL_PATH));
        $this->assertSame((string) $submission->id, $query['registration']);
        $this->assertSame('tara', $query['payment_gateway']);
        $this->assertTrue(SalesFormCheckoutToken::valid($query['checkout_token'], $order));
        $this->assertFalse(SalesFormCheckoutToken::valid($query['checkout_token'], $this->order($academy)));
    }

    public function test_storefront_link_uses_normal_buyer_authentication(): void
    {
        $owner = User::factory()->create();
        $academy = Environment::factory()->create(['owner_id' => $owner->id]);
        $order = $this->order($academy);
        Sanctum::actingAs($owner);
        $link = $this->link($order, $academy)->assertOk()->json('data.url');
        $this->assertSame("/checkout/continue-payment/{$order->id}", parse_url($link, PHP_URL_PATH));
        $this->assertStringNotContainsString('checkout_token', $link);
    }

    public function test_unauthenticated_users_and_non_staff_cannot_get_links(): void
    {
        $buyer = User::factory()->create();
        $academy = Environment::factory()->create();
        $order = $this->order($academy, ['user_id' => $buyer->id]);
        $this->link($order, $academy)->assertUnauthorized();
        Sanctum::actingAs($buyer);
        $this->link($order, $academy)->assertForbidden();
    }

    public function test_instructor_cannot_get_another_academys_order_link(): void
    {
        $owner = User::factory()->create();
        $academy = Environment::factory()->create(['owner_id' => $owner->id]);
        $other = Environment::factory()->create();
        Sanctum::actingAs($owner);
        $this->link($this->order($other), $academy)->assertNotFound();
        $this->link($this->order($other), $other)->assertForbidden();
    }

    public function test_completed_free_unsupported_and_inconsistent_sales_form_orders_cannot_get_links(): void
    {
        $owner = User::factory()->create();
        $academy = Environment::factory()->create(['owner_id' => $owner->id]);
        Sanctum::actingAs($owner);

        foreach ([
            ['status' => Order::STATUS_COMPLETED],
            ['status' => Order::STATUS_CANCELLED],
            ['status' => Order::STATUS_PROCESSING],
            ['total_amount' => 0],
            ['type' => Order::TYPE_ENROLLMENT_CODE],
            ['type' => Order::TYPE_SALES_FORM],
        ] as $fields) {
            $this->link($this->order($academy, $fields), $academy)->assertConflict();
        }
    }
}
