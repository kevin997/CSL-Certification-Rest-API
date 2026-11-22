<?php

namespace Tests\Unit;

use App\Models\Order;
use App\Models\SalesFormSubmission;
use App\Services\MetaConversionsService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaConversionsServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.meta_conversions', [
            'pixel_id' => '123456',
            'access_token' => 'test-token',
            'api_version' => 'v23.0',
        ]);
        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);
    }

    public function test_purchase_requires_a_completed_paid_order_and_uses_stable_deduplication_id(): void
    {
        $order = new Order([
            'status' => Order::STATUS_PENDING,
            'total_amount' => 5000,
            'currency' => 'XAF',
            'order_number' => 'ORD-123',
            'billing_email' => 'Buyer@Example.com',
        ]);
        $order->id = 42;

        $service = app(MetaConversionsService::class);
        $service->purchase($order);
        Http::assertNothingSent();

        $order->status = Order::STATUS_COMPLETED;
        $service->purchase($order);

        Http::assertSent(function ($request) {
            $event = $request['data'][0];

            return $event['event_name'] === 'Purchase'
                && $event['event_id'] === 'purchase:order:42'
                && $event['custom_data']['value'] === 5000.0
                && $event['custom_data']['currency'] === 'XAF'
                && $event['user_data']['em'] === hash('sha256', 'buyer@example.com')
                && ! str_contains($request->body(), 'Buyer@Example.com');
        });
    }

    public function test_sales_form_registration_uses_the_same_id_as_the_browser_pixel(): void
    {
        $submission = new SalesFormSubmission(['email' => 'learner@example.com']);
        $submission->id = 17;

        app(MetaConversionsService::class)->registration($submission);

        Http::assertSent(fn ($request) => $request['data'][0]['event_id'] === 'registration:submission:17');
    }

    public function test_every_configured_pixel_receives_the_same_event_id(): void
    {
        // The academy pixel stays configured the legacy way; a second brand
        // pixel is added through the multi-pixel setting. Invalid ids are
        // ignored rather than sending a request to a malformed endpoint.
        config()->set('services.meta_conversions', [
            'pixel_id' => '123456',
            'pixel_ids' => ['654321', '123456', 'not-a-pixel'],
            'access_token' => 'test-token',
            'api_version' => 'v23.0',
        ]);

        $submission = new SalesFormSubmission(['email' => 'learner@example.com']);
        $submission->id = 18;

        app(MetaConversionsService::class)->registration($submission);

        Http::assertSent(fn ($request) => str_contains($request->url(), '/123456/events')
            && $request['data'][0]['event_id'] === 'registration:submission:18');

        Http::assertSent(fn ($request) => str_contains($request->url(), '/654321/events')
            && $request['data'][0]['event_id'] === 'registration:submission:18');

        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'not-a-pixel'));

        // Same event, two pixel endpoints.
        $pixelEndpoints = collect(Http::recorded())
            ->map(fn ($pair) => $pair[0]->url())
            ->filter(fn (string $url) => str_contains($url, '/events'))
            ->values();
        $this->assertCount(2, $pixelEndpoints);
    }
}
