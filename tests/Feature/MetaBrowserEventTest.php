<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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

    public function test_long_click_ids_from_a_real_facebook_ad_are_accepted(): void
    {
        // Meta's fbclid (and therefore our _fbc cookie) is commonly longer than
        // 200 characters; such a visitor must not lose the event to a 422.
        $longFbc = 'fb.1.1700000000.'.str_repeat('A', 300);

        $this->postJson('/api/meta/browser-events', [
            'event_name' => 'ViewContent',
            'event_id' => 'ViewContent:long-fbc',
            'event_source_url' => 'https://bootcamps.csl-brands.com/forms/facebook-tiktok-google-meta',
            'fbc' => $longFbc,
            'fbp' => 'fb.1.1700000000.1234567890',
        ], ['Origin' => 'https://bootcamps.csl-brands.com'])
            ->assertAccepted();

        Http::assertSent(fn ($request) => $request['data'][0]['event_id'] === 'ViewContent:long-fbc');
    }

    public function test_a_rejected_event_is_logged_without_the_click_ids(): void
    {
        Log::spy();

        $this->postJson('/api/meta/browser-events', [
            'event_name' => 'ViewContent',
            'event_id' => 'ViewContent:page:1',
            'event_source_url' => 'not-a-url',
            'fbp' => 'fb.1.1700000000.1234567890',
            'fbc' => 'fb.1.1700000000.9876543210',
        ], ['Origin' => 'https://bootcamps.csl-brands.com'])
            ->assertUnprocessable();

        Log::shouldHaveReceived('error')
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'Rejected meta browser event'
                    && $context['origin'] === 'https://bootcamps.csl-brands.com'
                    && $context['payload']['event_source_url'] === 'not-a-url'
                    && ! array_key_exists('fbp', $context['payload'])
                    && ! array_key_exists('fbc', $context['payload']);
            })
            ->once();
    }

    public function test_a_non_https_event_source_is_logged(): void
    {
        Log::spy();

        $this->postJson('/api/meta/browser-events', [
            'event_name' => 'ViewContent',
            'event_id' => 'ViewContent:page:1',
            'event_source_url' => 'http://bootcamps.csl-brands.com/landing',
        ], ['Origin' => 'https://bootcamps.csl-brands.com'])
            ->assertUnprocessable();

        Log::shouldHaveReceived('error')
            ->withArgs(fn (string $message): bool => $message === 'Rejected meta browser event')
            ->once();
    }
}
