<?php

namespace Tests\Feature;

use App\Models\Environment;
use App\Models\Order;
use App\Models\ShortLink;
use App\Models\User;
use App\Services\Sales\ShortLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShortLinkTest extends TestCase
{
    use RefreshDatabase;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->create();
        $environment = Environment::factory()->create([
            'owner_id' => $owner->id,
            'primary_domain' => 'shop.example.com',
            'domain_verified_at' => now(),
        ]);

        $this->order = Order::withoutGlobalScopes()->create([
            'user_id' => $owner->id,
            'environment_id' => $environment->id,
            'order_number' => 'ORD-'.Str::upper(Str::random(8)),
            'status' => Order::STATUS_PENDING,
            'type' => Order::TYPE_STOREFRONT,
            'total_amount' => 10.50,
            'currency' => 'USD',
            'billing_email' => 'buyer@example.com',
        ]);
    }

    public function test_a_short_link_resolves_to_the_pending_checkout_url(): void
    {
        $shortUrl = app(ShortLinkService::class)->forOrder($this->order);
        $code = basename(parse_url($shortUrl, PHP_URL_PATH));

        $this->assertStringContainsString('/p/'.$code, $shortUrl);

        $response = $this->getJson('/api/short-links/'.$code);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.url', 'https://shop.example.com/checkout/continue-payment/'.$this->order->id);

        $this->assertSame(1, ShortLink::query()->where('code', $code)->value('hits'));
    }

    public function test_the_same_order_reuses_its_short_link(): void
    {
        $first = app(ShortLinkService::class)->forOrder($this->order);
        $second = app(ShortLinkService::class)->forOrder($this->order);

        $this->assertSame($first, $second);
    }

    public function test_an_expired_short_link_returns_not_found(): void
    {
        $shortUrl = app(ShortLinkService::class)->forOrder($this->order);
        $code = basename(parse_url($shortUrl, PHP_URL_PATH));

        ShortLink::query()->where('code', $code)->update(['expires_at' => now()->subMinute()]);

        $this->getJson('/api/short-links/'.$code)->assertNotFound();
    }

    public function test_a_completed_order_short_link_returns_not_found(): void
    {
        $shortUrl = app(ShortLinkService::class)->forOrder($this->order);
        $code = basename(parse_url($shortUrl, PHP_URL_PATH));

        $this->order->update(['status' => Order::STATUS_COMPLETED]);

        $this->getJson('/api/short-links/'.$code)->assertNotFound();
    }
}
