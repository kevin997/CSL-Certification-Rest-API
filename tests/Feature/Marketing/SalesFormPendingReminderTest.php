<?php

namespace Tests\Feature\Marketing;

use App\Jobs\SendWhatsAppNotification;
use App\Mail\AutomationMail;
use App\Models\Environment;
use App\Models\MarketingAutomation;
use App\Models\MarketingConsent;
use App\Models\Order;
use App\Models\SalesForm;
use App\Models\SalesFormField;
use App\Models\SalesFormSubmission;
use App\Models\User;
use App\Services\WachapNotificationService;
use App\Support\Sales\SalesFormCheckoutToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SalesFormPendingReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // These suites exercise marketing email delivery, which is off by default.
        config()->set('marketing.email_enabled', true);
        config()->set('services.waha', ['base_url' => 'https://waha.test', 'api_key' => 'test', 'session' => 'kursa']);
        config()->set('services.wachap.base_url', 'https://example.test');
        config()->set('services.wachap.token', 'test-token');
        config()->set('services.wachap.account_id', 'test-account');
    }

    private function makeOrder(string $country, string $status = Order::STATUS_PENDING): Order
    {
        $owner = User::factory()->create();
        $customer = User::factory()->create();
        $environment = Environment::factory()->create(['owner_id' => $owner->id]);
        MarketingAutomation::withoutGlobalScopes()->create([
            'environment_id' => $environment->id,
            'trigger' => MarketingAutomation::TRIGGER_ORDER_ABANDONED,
            'enabled' => true,
            'channels' => ['whatsapp'],
            'recipient' => MarketingAutomation::RECIPIENT_CUSTOMER,
        ]);
        $form = SalesForm::withoutGlobalScopes()->create([
            'environment_id' => $environment->id,
            'created_by' => $owner->id,
            'title' => 'Media Buying',
            'slug' => 'media-buying-reminder-'.$environment->id,
            'status' => SalesForm::STATUS_PUBLISHED,
        ]);
        SalesFormField::create([
            'sales_form_id' => $form->id,
            'type' => 'country',
            'field_key' => 'location_country',
            'label' => 'Country',
            'order' => 1,
        ]);
        $submission = SalesFormSubmission::withoutGlobalScopes()->create([
            'sales_form_id' => $form->id,
            'environment_id' => $environment->id,
            'access_code' => 'REMIND01',
            'name' => 'Mianda',
            'email' => 'mianda@example.com',
            'phone' => '+242050330038',
            'answers' => ['location_country' => $country],
            'status' => SalesFormSubmission::STATUS_PENDING,
        ]);
        foreach (['email', 'whatsapp'] as $channel) {
            MarketingConsent::grant($submission, $channel, 'sales_form', '2026-09', now());
        }

        $order = Order::withoutGlobalScopes()->create([
            'environment_id' => $environment->id,
            'user_id' => $customer->id,
            'sales_form_submission_id' => $submission->id,
            'order_number' => 'ORD-REMIND'.$environment->id,
            'status' => $status,
            'type' => Order::TYPE_SALES_FORM,
            'total_amount' => 5000,
            'currency' => 'XAF',
        ]);
        $order->forceFill(['created_at' => now()->subHours(30)])->saveQuietly();

        return $order;
    }

    public function test_pending_congo_prospect_receives_french_email_and_whatsapp_once(): void
    {
        Mail::fake();
        Queue::fake();
        $order = $this->makeOrder('Congo');

        $this->artisan('automations:process-abandoned-orders')->assertSuccessful();

        Mail::assertQueued(AutomationMail::class, fn ($mail) => $mail->hasTo('mianda@example.com')
            && str_contains($mail->automationBody, 'Bonjour Mianda'));
        Queue::assertPushed(SendWhatsAppNotification::class, function ($job) use ($order) {
            $this->assertSame($order->id, $job->pendingOrderId);
            $this->assertStringContainsString('en attente de paiement', $job->message);
            preg_match('~https://[^\s]+~', $job->message, $matches);
            $shortUrl = rtrim($matches[0] ?? '', '.');
            $this->assertMatchesRegularExpression('~^/p/[a-z0-9]{10}$~', parse_url($shortUrl, PHP_URL_PATH));
            $destination = $this->getJson('/api/short-links/'.basename(parse_url($shortUrl, PHP_URL_PATH)))->assertOk()->json('data.url');
            parse_str((string) parse_url($destination, PHP_URL_QUERY), $params);
            $this->assertTrue(SalesFormCheckoutToken::valid($params['checkout_token'] ?? null, $order));

            return true;
        });
        $this->assertNotNull($order->fresh()->abandoned_reminder_sent_at);

        $this->artisan('automations:process-abandoned-orders')->assertSuccessful();
        Mail::assertQueued(AutomationMail::class, 1);
        Queue::assertPushed(SendWhatsAppNotification::class, 1);
    }

    public function test_english_prospect_receives_english_copy_and_completed_order_is_excluded(): void
    {
        Mail::fake();
        Queue::fake();
        $pending = $this->makeOrder('Nigeria');
        $completed = $this->makeOrder('Congo', Order::STATUS_COMPLETED);

        $this->artisan('automations:process-abandoned-orders')->assertSuccessful();

        Queue::assertPushed(SendWhatsAppNotification::class, fn ($job) => $job->pendingOrderId === $pending->id
            && str_contains($job->message, 'awaiting payment'));
        Queue::assertNotPushed(SendWhatsAppNotification::class, fn ($job) => $job->pendingOrderId === $completed->id);
        $this->assertNull($completed->fresh()->abandoned_reminder_sent_at);
    }

    public function test_revoked_channel_is_not_used(): void
    {
        Mail::fake();
        Queue::fake();
        $order = $this->makeOrder('Congo');
        MarketingConsent::revoke($order->salesFormSubmission, 'whatsapp', 'unsubscribe', '2026-09', now());

        $this->artisan('automations:process-abandoned-orders')->assertSuccessful();

        Mail::assertQueued(AutomationMail::class, 1);
        Queue::assertNotPushed(SendWhatsAppNotification::class);
    }

    public function test_no_consent_does_not_claim_a_reminder_was_sent(): void
    {
        Mail::fake();
        Queue::fake();
        $order = $this->makeOrder('Congo');
        MarketingConsent::revoke($order->salesFormSubmission, 'email', 'unsubscribe', '2026-09', now());
        MarketingConsent::revoke($order->salesFormSubmission, 'whatsapp', 'unsubscribe', '2026-09', now());

        $this->artisan('automations:process-abandoned-orders')->assertSuccessful();

        Mail::assertNothingOutgoing();
        Queue::assertNotPushed(SendWhatsAppNotification::class);
        $this->assertNull($order->fresh()->abandoned_reminder_sent_at);
    }

    public function test_whatsapp_starts_at_thirty_minutes_without_accelerating_email(): void
    {
        $this->freezeTime();
        Mail::fake();
        Queue::fake();
        $order = $this->makeOrder('Congo');
        $order->forceFill(['created_at' => now()->subMinutes(29)])->saveQuietly();

        $this->artisan('automations:process-abandoned-orders')->assertSuccessful();
        Queue::assertNotPushed(SendWhatsAppNotification::class);

        $this->travel(1)->minute();
        $this->artisan('automations:process-abandoned-orders')->assertSuccessful();
        Queue::assertPushed(SendWhatsAppNotification::class, fn ($job) => $job->pendingOrderId === $order->id);
        Mail::assertNothingOutgoing();
    }

    public function test_disabled_whatsapp_automation_does_not_queue_sales_form_reminders(): void
    {
        Mail::fake();
        Queue::fake();
        $order = $this->makeOrder('Congo');
        MarketingAutomation::withoutGlobalScopes()->where('environment_id', $order->environment_id)
            ->update(['enabled' => false]);

        $this->artisan('automations:process-abandoned-orders')->assertSuccessful();

        Queue::assertNotPushed(SendWhatsAppNotification::class);
        $this->assertNull($order->fresh()->abandoned_whatsapp_attempted_at);
    }

    public function test_expired_order_never_queues_or_sends_a_reminder(): void
    {
        $this->freezeTime();
        Mail::fake();
        Queue::fake();
        $order = $this->makeOrder('Congo');
        $order->forceFill(['created_at' => now()->subDays(7)])->saveQuietly();

        $this->artisan('automations:process-abandoned-orders')->assertSuccessful();
        Mail::assertNothingOutgoing();
        Queue::assertNotPushed(SendWhatsAppNotification::class);

        $wachap = $this->mock(WachapNotificationService::class);
        $wachap->shouldNotReceive('sendWhatsApp');
        $wachap->shouldNotReceive('sendVerifiedPaymentReminder');
        (new SendWhatsAppNotification('242050330038', 'Reminder', $order->id, true))->handle($wachap);
    }

    public function test_reminder_uses_verified_chat_id_and_only_marks_success_once(): void
    {
        $order = $this->makeOrder('Congo');
        config()->set('services.waha', ['base_url' => 'https://waha.test', 'api_key' => 'test', 'session' => 'kursa']);
        Http::preventStrayRequests();
        Http::fake([
            'https://waha.test/api/sessions/kursa' => Http::response(['status' => 'WORKING']),
            'https://waha.test/api/contacts/check-exists*' => Http::response(['numberExists' => true, 'chatId' => '123456789@lid']),
            'https://waha.test/api/sendText' => Http::response(['id' => 'sent']),
        ]);
        $job = new SendWhatsAppNotification('242050330038', 'Reminder', $order->id, true);
        $job->handle(app(WachapNotificationService::class));
        $this->assertNotNull($order->fresh()->abandoned_whatsapp_sent_at);
        $job->handle(app(WachapNotificationService::class));
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => $request->url() === 'https://waha.test/api/sendText'
            && $request['chatId'] === '123456789@lid');
    }

    public function test_unregistered_number_is_not_sent_or_marked_sent(): void
    {
        $order = $this->makeOrder('Congo');
        config()->set('services.waha', ['base_url' => 'https://waha.test', 'api_key' => 'test', 'session' => 'kursa']);
        Http::preventStrayRequests();
        Http::fake([
            'https://waha.test/api/sessions/kursa' => Http::response(['status' => 'WORKING']),
            'https://waha.test/api/contacts/check-exists*' => Http::response(['numberExists' => false]),
        ]);

        (new SendWhatsAppNotification('242050330038', 'Reminder', $order->id, true))->handle(app(WachapNotificationService::class));

        Http::assertSentCount(2);
        $this->assertNull($order->fresh()->abandoned_whatsapp_sent_at);
    }

    public function test_queued_whatsapp_does_not_send_after_order_completes(): void
    {
        $order = $this->makeOrder('Congo');
        $job = new SendWhatsAppNotification('242050330038', 'Reminder', $order->id);
        $order->update(['status' => Order::STATUS_COMPLETED]);
        config()->set('services.wachap.base_url', 'https://example.test');
        config()->set('services.wachap.token', 'test-token');
        config()->set('services.wachap.account_id', 'test-account');
        $wachap = $this->mock(WachapNotificationService::class);
        $wachap->shouldNotReceive('sendWhatsApp');
        $wachap->shouldNotReceive('sendVerifiedPaymentReminder');

        $job->handle($wachap);
    }
}
