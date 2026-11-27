<?php

namespace Tests\Feature;

use App\Jobs\SendWhatsAppNotification;
use App\Mail\AutomationMail;
use App\Models\Environment;
use App\Models\MarketingAutomation;
use App\Models\MarketingConsent;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SalesForm;
use App\Models\SalesFormSubmission;
use App\Models\ShortLink;
use App\Models\User;
use App\Services\MarketingAutomationService;
use App\Services\Sales\ShortLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class MarketingAutomationTest extends TestCase
{
    use RefreshDatabase;

    private Environment $environment;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();

        // These suites exercise marketing email delivery, which is off by default.
        config()->set('marketing.email_enabled', true);

        $this->owner = User::factory()->create([
            'whatsapp_number' => '+237600000001',
        ]);
        $this->environment = Environment::factory()->create([
            'owner_id' => $this->owner->id,
        ]);
    }

    private function makeAutomation(string $trigger, array $overrides = []): MarketingAutomation
    {
        return MarketingAutomation::withoutGlobalScopes()->create(array_merge([
            'environment_id' => $this->environment->id,
            'trigger' => $trigger,
            'enabled' => true,
            'channels' => ['email', 'whatsapp'],
            'recipient' => MarketingAutomation::DEFAULT_RECIPIENTS[$trigger],
            'email_subject' => 'New lead: {{name}}',
            'email_body' => "Hello,\n{{name}} ({{email}} / {{phone}}) submitted {{form_title}}.",
            'whatsapp_template' => 'New lead {{name}} on {{store_name}}',
        ], $overrides));
    }

    private function makeSubmission(): SalesFormSubmission
    {
        $form = SalesForm::withoutGlobalScopes()->create([
            'environment_id' => $this->environment->id,
            'created_by' => $this->owner->id,
            'title' => 'Digital Marketing Bootcamp',
            'slug' => 'digital-marketing-bootcamp',
            'status' => 'published',
        ]);

        return SalesFormSubmission::withoutGlobalScopes()->create([
            'sales_form_id' => $form->id,
            'environment_id' => $this->environment->id,
            'access_code' => 'TEST1234',
            'name' => 'Jean Mballa',
            'email' => 'jean@example.com',
            'phone' => '+237 6 99 99 99 99',
            'status' => 'pending',
            'answers' => [],
        ]);
    }

    public function test_form_submission_notifies_the_instructor_on_both_channels(): void
    {
        Mail::fake();
        Queue::fake();

        $this->makeAutomation(MarketingAutomation::TRIGGER_FORM_SUBMITTED);
        $submission = $this->makeSubmission();

        app(MarketingAutomationService::class)->handleFormSubmitted($submission);

        Mail::assertQueued(AutomationMail::class, function (AutomationMail $mail) {
            return $mail->hasTo($this->owner->email)
                && str_contains($mail->automationSubject, 'Jean Mballa')
                && str_contains($mail->automationBody, 'Digital Marketing Bootcamp');
        });

        Queue::assertPushed(SendWhatsAppNotification::class, function (SendWhatsAppNotification $job) {
            return $job->phoneNumber === '237600000001'
                && str_contains($job->message, 'Jean Mballa');
        });
    }

    public function test_disabled_automation_sends_nothing(): void
    {
        Mail::fake();
        Queue::fake();

        $this->makeAutomation(MarketingAutomation::TRIGGER_FORM_SUBMITTED, ['enabled' => false]);
        $submission = $this->makeSubmission();

        app(MarketingAutomationService::class)->handleFormSubmitted($submission);

        Mail::assertNothingQueued();
        Queue::assertNotPushed(SendWhatsAppNotification::class);
    }

    public function test_payment_confirmed_targets_the_customer(): void
    {
        Mail::fake();
        Queue::fake();

        $this->makeAutomation(MarketingAutomation::TRIGGER_PAYMENT_CONFIRMED, [
            'email_subject' => 'Thank you {{name}}',
            'email_body' => 'Order {{order_number}} for {{total}} {{currency}} is confirmed.',
            'whatsapp_template' => 'Thanks {{name}} — order {{order_number}} confirmed!',
        ]);

        $customer = User::factory()->create(['whatsapp_number' => '+237655555555']);
        $order = Order::withoutGlobalScopes()->create([
            'user_id' => $customer->id,
            'environment_id' => $this->environment->id,
            'order_number' => 'ORD-1001',
            'status' => Order::STATUS_COMPLETED,
            'type' => Order::TYPE_STOREFRONT,
            'total_amount' => 25000,
            'currency' => 'XAF',
            'billing_name' => 'Awa Client',
            'billing_email' => 'awa@example.com',
        ]);

        app(MarketingAutomationService::class)
            ->handleOrderTrigger($order, MarketingAutomation::TRIGGER_PAYMENT_CONFIRMED);

        Mail::assertQueued(AutomationMail::class, function (AutomationMail $mail) {
            return $mail->hasTo('awa@example.com')
                && str_contains($mail->automationBody, 'ORD-1001');
        });

        Queue::assertPushed(SendWhatsAppNotification::class, function (SendWhatsAppNotification $job) {
            return $job->phoneNumber === '237655555555';
        });
    }

    public function test_abandoned_orders_command_is_idempotent(): void
    {
        Mail::fake();
        Queue::fake();

        $this->makeAutomation(MarketingAutomation::TRIGGER_ORDER_ABANDONED, [
            'email_subject' => 'Complete your order {{order_number}}',
            'email_body' => 'Finish here: {{continue_url}}',
            'whatsapp_template' => 'Your order {{order_number}} is waiting: {{continue_url}}',
            'config' => ['abandoned_delay_hours' => 24],
        ]);

        $customer = User::factory()->create(['whatsapp_number' => '+237644444444']);
        $order = Order::withoutGlobalScopes()->create([
            'user_id' => $customer->id,
            'environment_id' => $this->environment->id,
            'order_number' => 'ORD-2002',
            'status' => Order::STATUS_PENDING,
            'type' => Order::TYPE_STOREFRONT,
            'total_amount' => 10000,
            'currency' => 'XAF',
            'billing_name' => 'Paul Pending',
            'billing_email' => 'paul@example.com',
        ]);
        $order->forceFill(['created_at' => now()->subHours(30)])->saveQuietly();

        $this->artisan('automations:process-abandoned-orders')->assertSuccessful();

        Mail::assertQueued(AutomationMail::class, fn (AutomationMail $mail) => $mail->hasTo('paul@example.com'));
        $this->assertNotNull($order->fresh()->abandoned_reminder_sent_at);

        // Second run must not re-send
        Mail::fake();
        Queue::fake();
        $this->artisan('automations:process-abandoned-orders')->assertSuccessful();
        Mail::assertNothingQueued();
    }

    public function test_recent_pending_orders_are_not_flagged_as_abandoned(): void
    {
        Mail::fake();
        Queue::fake();

        $this->makeAutomation(MarketingAutomation::TRIGGER_ORDER_ABANDONED);

        $customer = User::factory()->create();
        Order::withoutGlobalScopes()->create([
            'user_id' => $customer->id,
            'environment_id' => $this->environment->id,
            'order_number' => 'ORD-3003',
            'status' => Order::STATUS_PENDING,
            'type' => Order::TYPE_STOREFRONT,
            'total_amount' => 5000,
            'currency' => 'XAF',
            'billing_email' => 'fresh@example.com',
        ]);

        $this->artisan('automations:process-abandoned-orders')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_order_placed_for_a_sales_form_uses_the_order_phone_and_a_signed_continue_link(): void
    {
        Mail::fake();
        Queue::fake();

        $this->makeAutomation(MarketingAutomation::TRIGGER_ORDER_PLACED, [
            'recipient' => MarketingAutomation::RECIPIENT_CUSTOMER,
            'email_subject' => 'Pay order {{order_number}}',
            'email_body' => 'Hi {{name}}, {{product_name}} is waiting. Continue here: {{continue_url}}',
            'whatsapp_template' => 'Hi {{name}}, {{product_name}} is waiting. Continue here: {{continue_url}}',
        ]);

        $submission = $this->makeSubmission();
        MarketingConsent::grant($submission, 'whatsapp', 'sales_form', '2026-09', now());

        $customer = User::factory()->create([
            'email' => $submission->email,
            'whatsapp_number' => null,
        ]);

        $order = Order::withoutGlobalScopes()->create([
            'user_id' => $customer->id,
            'environment_id' => $this->environment->id,
            'order_number' => 'ORD-4004',
            'status' => Order::STATUS_PENDING,
            'type' => Order::TYPE_SALES_FORM,
            'sales_form_submission_id' => $submission->id,
            'total_amount' => 10.50,
            'currency' => 'USD',
            'billing_name' => $submission->name,
            'billing_email' => $submission->email,
        ]);
        $order->forceFill(['created_at' => now()->subMinutes(11)]);
        $order->phone_number = $submission->phone;
        $order->save();

        $product = Product::create([
            'name' => 'Media Buying Bootcamp',
            'slug' => 'media-buying-bootcamp',
            'price' => 10.50,
            'currency' => 'USD',
            'status' => 'active',
            'environment_id' => $this->environment->id,
            'created_by' => $this->owner->id,
        ]);

        OrderItem::create([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'price' => 10.50,
            'discount' => 0,
            'total' => 10.50,
            'is_subscription' => false,
        ]);

        app(MarketingAutomationService::class)
            ->handleOrderTrigger($order, MarketingAutomation::TRIGGER_ORDER_PLACED);

        Mail::assertQueued(AutomationMail::class, function (AutomationMail $mail) use ($submission) {
            return $mail->hasTo($submission->email)
                && str_contains($mail->automationBody, 'Media Buying Bootcamp')
                && str_contains($mail->automationBody, '/p/')
                && ! str_contains($mail->automationBody, 'checkout_token');
        });

        Queue::assertPushed(SendWhatsAppNotification::class, function (SendWhatsAppNotification $job) {
            return $job->phoneNumber === '237699999999'
                && str_contains($job->message, 'Media Buying Bootcamp')
                && str_contains($job->message, '/p/')
                && ! str_contains($job->message, 'checkout_token');
        });

        $link = ShortLink::query()->where('order_id', $order->id)->firstOrFail();
        $destination = app(ShortLinkService::class)->resolve($link->code);
        $this->assertNotNull($destination);
        $this->assertStringContainsString('/checkout/continue-payment/'.$order->id, $destination);
        $this->assertStringContainsString('checkout_token=', $destination);
        $this->assertStringContainsString('registration='.$submission->id, $destination);
    }
}
