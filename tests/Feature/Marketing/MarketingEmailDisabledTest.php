<?php

namespace Tests\Feature\Marketing;

use App\Mail\MarketingCampaignMail;
use App\Mail\WelcomeToEnvironment;
use App\Models\Environment;
use App\Models\MarketingMessage;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Marketing email is off by default: the transactional mailbox (Hostinger,
 * 100/day) must not be consumed by campaigns, digests or retention. WhatsApp
 * remains the marketing/retention channel. Transactional emails (welcome,
 * password setup, receipts...) must keep working.
 */
class MarketingEmailDisabledTest extends TestCase
{
    use RefreshDatabase;

    public function test_marketing_email_is_disabled_by_default(): void
    {
        $this->assertFalse((bool) config('marketing.email_enabled'));
        $this->assertFalse((bool) config('marketing.push_enabled'));
    }

    public function test_email_campaigns_are_paused_while_marketing_email_is_disabled(): void
    {
        $message = MarketingMessage::create([
            'channel' => MarketingMessage::CHANNEL_EMAIL,
            'title' => 'Paused campaign',
            'body' => 'Body',
            'email_subject' => 'Paused campaign',
            'email_html' => '<p>Body</p>',
            'hash' => Str::random(40),
            'status' => MarketingMessage::STATUS_PENDING,
        ]);

        Mail::fake();

        $this->artisan('kursa:send-email-campaign')->assertSuccessful();

        Mail::assertNothingQueued();
        $this->assertSame(MarketingMessage::STATUS_PENDING, $message->fresh()->status);
    }

    public function test_email_campaigns_send_again_when_the_flag_is_enabled(): void
    {
        config(['marketing.email_enabled' => true]);

        $owner = User::factory()->create([
            'email_verified_at' => now(),
            'marketing_opt_in' => true,
        ]);
        Environment::factory()->create(['owner_id' => $owner->id]);

        MarketingMessage::create([
            'channel' => MarketingMessage::CHANNEL_EMAIL,
            'title' => 'Enabled campaign',
            'body' => 'Body',
            'email_subject' => 'Enabled campaign',
            'email_html' => '<p>Body</p>',
            'hash' => Str::random(40),
            'status' => MarketingMessage::STATUS_PENDING,
        ]);

        Mail::fake();

        $this->artisan('kursa:send-email-campaign')->assertSuccessful();

        Mail::assertQueued(MarketingCampaignMail::class, fn (MarketingCampaignMail $mail) => $mail->hasTo($owner->email));
    }

    public function test_weekly_digests_are_skipped_while_marketing_email_is_disabled(): void
    {
        $owner = User::factory()->create();
        Environment::factory()->create(['owner_id' => $owner->id, 'is_active' => true]);

        Mail::fake();

        $this->artisan('engagement:learner-digest')->assertSuccessful();
        $this->artisan('engagement:instructor-digest')->assertSuccessful();

        Mail::assertNothingQueued();
    }

    public function test_password_setup_email_still_sends_while_marketing_email_is_disabled(): void
    {
        $environment = Environment::factory()->create([
            'owner_id' => User::factory()->create()->id,
            'primary_domain' => 'password-setup.example.test',
            'domain_verified_at' => now(),
        ]);

        $learner = User::factory()->create(['email' => 'paid-learner@example.test']);

        Order::withoutGlobalScopes()->create([
            'user_id' => $learner->id,
            'environment_id' => $environment->id,
            'order_number' => 'ORD-'.Str::upper(Str::random(8)),
            'type' => Order::TYPE_SALES_FORM,
            'status' => Order::STATUS_COMPLETED,
            'total_amount' => 6,
            'currency' => 'USD',
        ]);

        Mail::fake();

        $this->artisan('users:send-password-setup-link', ['email' => $learner->email])
            ->assertSuccessful();

        Mail::assertSent(WelcomeToEnvironment::class, function (WelcomeToEnvironment $mail) use ($learner) {
            return $mail->hasTo($learner->email)
                && is_string($mail->passwordSetupUrl)
                && $mail->passwordSetupUrl !== '';
        });
    }
}
