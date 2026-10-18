<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaBrowserEventTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware();
        config()->set('services.meta_conversions', [
            'pixel_id' => '123456',
            'access_token' => 'test-token',
            'api_version' => 'v23.0',
        ]);
        Http::fake(['graph.facebook.com/*' => Http::response(['events_received' => 1])]);
    }

    public function test_valid_checkout_event_is_relayed_with_matching_event_id(): void
    {
        $this->postJson('/api/meta/browser-events', [
            'event_name' => 'InitiateCheckout',
            'event_id' => 'InitiateCheckout:order:42',
            'event_source_url' => 'https://bootcamps.csl-brands.com/checkout/continue-payment/42',
            'value' => 5000,
            'currency' => 'XAF',
        ], ['Origin' => 'https://bootcamps.csl-brands.com'])
            ->assertAccepted();

        Http::assertSent(fn ($request) => $request['data'][0]['event_id'] === 'InitiateCheckout:order:42');
    }

    public function test_browser_cannot_claim_a_purchase_or_use_an_unrelated_origin(): void
    {
        $payload = [
            'event_name' => 'Purchase',
            'event_id' => 'purchase:order:42',
            'event_source_url' => 'https://bootcamps.csl-brands.com/checkout/42',
        ];

        $this->postJson('/api/meta/browser-events', $payload, ['Origin' => 'https://bootcamps.csl-brands.com'])
            ->assertUnprocessable();
        $this->postJson('/api/meta/browser-events', array_replace($payload, ['event_name' => 'ViewContent']),
            ['Origin' => 'https://attacker.example'])->assertForbidden();
        Http::assertNothingSent();
    }
}
