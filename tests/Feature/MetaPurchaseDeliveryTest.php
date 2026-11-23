<?php

namespace Tests\Feature;

use App\Models\Environment;
use App\Models\Order;
use App\Models\Transaction;
use App\Models\User;
use App\Services\MetaConversionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaPurchaseDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('services.meta_conversions', [
            'pixel_id' => '123456', 'pixel_ids' => ['654321'],
            'access_token' => 'test-token', 'api_version' => 'v25.0',
        ]);
        $owner = User::factory()->create();
        $environment = Environment::factory()->create(['owner_id' => $owner->id]);
        $this->order = Order::withoutGlobalScopes()->create([
            'user_id' => $owner->id, 'environment_id' => $environment->id,
            'order_number' => 'ORD-META-RETRY', 'status' => Order::STATUS_PENDING,
            'total_amount' => 6, 'currency' => 'USD', 'billing_email' => 'buyer@example.com',
        ]);
        Transaction::withoutGlobalScopes()->create([
            'transaction_id' => 'TXN-META-RETRY', 'environment_id' => $environment->id,
            'order_id' => $this->order->id, 'amount' => 6, 'total_amount' => 6,
            'currency' => 'USD', 'status' => Transaction::STATUS_COMPLETED,
            'paid_at' => now()->subMinutes(20),
        ]);
    }

    public function test_only_failed_pixels_retry_and_success_requires_an_accepted_event(): void
    {
        Http::fake([
            'graph.facebook.com/*/123456/events' => Http::response(['events_received' => 1]),
            'graph.facebook.com/*/654321/events' => Http::response(['error' => ['code' => 190]], 400),
        ]);
        try {
            app(MetaConversionsService::class)->purchase($this->order);
            $this->fail('A rejected purchase must leave a retryable job.');
        } catch (\RuntimeException $error) {
            $this->assertSame('Meta Purchase delivery is pending retry', $error->getMessage());
        }
        $this->assertDatabaseHas('meta_purchase_deliveries', ['pixel_id' => '654321', 'last_error_code' => 190, 'delivered_at' => null]);
        $this->assertNotNull(DB::table('meta_purchase_deliveries')->where('pixel_id', '123456')->value('delivered_at'));
        $paidAt = $this->order->transactions()->first()->paid_at->timestamp;
        Http::assertSent(fn ($request) => $request['data'][0]['event_time'] === $paidAt
            && $request->hasHeader('Authorization', 'Bearer test-token')
            && ! str_contains($request->url(), 'test-token'));

        $this->travel(2)->minutes();
        Http::swap(new Factory);
        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);
        app(MetaConversionsService::class)->purchase($this->order);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/654321/events')
            && $request['data'][0]['event_time'] === $paidAt
            && $request['data'][0]['event_id'] === 'purchase:order:'.$this->order->id);
        app(MetaConversionsService::class)->purchase($this->order);
        Http::assertSentCount(1);
    }

    public function test_an_unexpired_lease_and_a_success_response_without_acceptance_do_not_lose_a_delivery(): void
    {
        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 0])]);
        try {
            app(MetaConversionsService::class)->purchase($this->order);
        } catch (\RuntimeException) {
        }
        $this->assertSame(0, DB::table('meta_purchase_deliveries')->whereNotNull('delivered_at')->count());
        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);
        app(MetaConversionsService::class)->purchase($this->order);
        Http::assertNothingSent();
    }

    public function test_reconciliation_recovers_only_new_confirmed_payments_and_existing_pending_deliveries(): void
    {
        DB::table('meta_purchase_recovery_state')->update(['started_at' => now()]);
        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);
        $this->artisan('meta:reconcile-purchases')->assertSuccessful();
        Http::assertNothingSent();
        // The previous paid order is legacy. A new paid event becomes eligible
        // even while fulfillment has not yet changed its order from pending.
        $this->order->transactions()->update(['paid_at' => now()->addSecond()]);
        $this->travel(2)->seconds();
        $this->artisan('meta:reconcile-purchases')->assertSuccessful();
        Http::assertSentCount(2);
        $this->artisan('meta:reconcile-purchases')->assertSuccessful();
        Http::assertSentCount(2);
    }

    public function test_old_purchase_replays_never_refresh_event_time(): void
    {
        $this->order->transactions()->update(['paid_at' => now()->subDays(3)]);
        Http::fake();
        app(MetaConversionsService::class)->purchase($this->order);
        Http::assertNothingSent();
        $this->assertDatabaseCount('meta_purchase_deliveries', 0);
    }
}
