<?php

namespace Tests\Feature;

use App\Models\Environment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SalesForm;
use App\Models\SalesFormSubmission;
use App\Models\User;
use App\Services\MetaConversionsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MetaConversionsServiceTest extends TestCase
{
    use RefreshDatabase;

    private Environment $environment;

    private User $user;

    private Order $order;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.meta_conversions.pixel_id' => '1636552768056179',
            'services.meta_conversions.access_token' => 'test-token',
            'services.meta_conversions.api_version' => 'v23.0',
        ]);

        $this->user = User::factory()->create([
            'email' => 'buyer@example.com',
            'whatsapp_number' => '+237681685901',
        ]);

        $this->environment = Environment::factory()->create([
            'owner_id' => $this->user->id,
            'primary_domain' => 'bootcamps.csl-brands.com',
            'domain_verified_at' => now(),
        ]);

        $product = Product::create([
            'name' => 'Media Buying Bootcamp',
            'slug' => 'media-buying-bootcamp',
            'price' => 8.38,
            'currency' => 'USD',
            'status' => 'active',
            'environment_id' => $this->environment->id,
            'created_by' => $this->user->id,
        ]);

        $this->order = Order::withoutGlobalScopes()->create([
            'user_id' => $this->user->id,
            'environment_id' => $this->environment->id,
            'order_number' => 'ORD-META1',
            'status' => Order::STATUS_PENDING,
            'type' => Order::TYPE_SALES_FORM,
            'total_amount' => 8.38,
            'currency' => 'USD',
            'billing_email' => 'buyer@example.com',
            'billing_name' => 'Buyer Name',
        ]);

        OrderItem::create([
            'order_id' => $this->order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 8.38,
            'discount' => 0,
            'total' => 8.38,
            'is_subscription' => false,
        ]);
    }

    public function test_initiate_checkout_uses_a_deduplicated_event_and_order_content(): void
    {
        Http::fake();

        app(MetaConversionsService::class)->initiateCheckout($this->order);

        Http::assertSent(function (Request $request) {
            $event = $request->data()['data'][0] ?? [];

            return $event['event_name'] === 'InitiateCheckout'
                && $event['event_id'] === 'InitiateCheckout:order:'.$this->order->id
                && $event['custom_data']['value'] === 8.38
                && $event['custom_data']['currency'] === 'USD'
                && $event['custom_data']['content_name'] === 'Media Buying Bootcamp'
                && $event['custom_data']['content_ids'] === [(string) $this->order->items()->value('product_id')];
        });
    }

    public function test_purchase_uses_the_same_event_id_as_the_browser_success_page(): void
    {
        $this->order->update(['status' => Order::STATUS_COMPLETED]);
        Http::fake();

        app(MetaConversionsService::class)->purchase($this->order->fresh());

        Http::assertSent(function (Request $request) {
            $event = $request->data()['data'][0] ?? [];

            return $event['event_name'] === 'Purchase'
                && $event['event_id'] === 'purchase:order:'.$this->order->id
                && $event['custom_data']['value'] === 8.38
                && $event['custom_data']['content_name'] === 'Media Buying Bootcamp';
        });
    }

    public function test_registration_uses_the_submission_event_id(): void
    {
        $form = SalesForm::withoutGlobalScopes()->create([
            'environment_id' => $this->environment->id,
            'created_by' => $this->user->id,
            'title' => 'Media Buying',
            'slug' => 'media-buying',
            'status' => 'published',
        ]);

        $submission = SalesFormSubmission::withoutGlobalScopes()->create([
            'sales_form_id' => $form->id,
            'environment_id' => $this->environment->id,
            'user_id' => $this->user->id,
            'access_code' => 'META1234',
            'name' => 'Buyer Name',
            'email' => 'buyer@example.com',
            'phone' => '+237681685901',
            'status' => 'pending',
            'answers' => [],
        ]);

        $this->order->update(['sales_form_submission_id' => $submission->id]);
        Http::fake();

        app(MetaConversionsService::class)->registration($submission);

        Http::assertSent(function (Request $request) use ($submission) {
            $event = $request->data()['data'][0] ?? [];

            return $event['event_name'] === 'CompleteRegistration'
                && $event['event_id'] === 'registration:submission:'.$submission->id
                && $event['custom_data']['content_name'] === 'Media Buying Bootcamp';
        });
    }
}
