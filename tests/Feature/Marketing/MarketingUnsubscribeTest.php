<?php

namespace Tests\Feature\Marketing;

use App\Mail\MarketingCampaignMail;
use App\Models\Environment;
use App\Models\MarketingConsent;
use App\Models\SalesForm;
use App\Models\SalesFormSubmission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class MarketingUnsubscribeTest extends TestCase
{
    use RefreshDatabase;

    private Environment $environment;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->create();
        $this->environment = Environment::factory()->create(['owner_id' => $owner->id]);
    }

    public function test_an_unsubscribe_link_revokes_only_its_channel_and_is_idempotent(): void
    {
        $submission = $this->submission();
        MarketingConsent::grant($submission, 'email', 'sales_form', '2026-09', CarbonImmutable::now());
        MarketingConsent::grant($submission, 'whatsapp', 'sales_form', '2026-09', CarbonImmutable::now());
        $url = $this->unsubscribeUrl($submission, 'email');

        $this->get($url)
            ->assertOk()
            ->assertSee('Your communication preferences have been updated.')
            ->assertDontSee('unsubscribe@example.test')
            ->assertDontSee('UNSUBSCRIBE');

        $this->assertFalse(MarketingConsent::isGranted($submission, 'email'));
        $this->assertTrue(MarketingConsent::isGranted($submission, 'whatsapp'));
        $this->assertDatabaseCount('marketing_consents', 3);

        $this->get($url)->assertOk();

        $this->assertDatabaseCount('marketing_consents', 3);
    }

    public function test_existing_marketing_mail_uses_an_expiring_email_specific_unsubscribe_link(): void
    {
        $user = User::factory()->create();

        $unsubscribeUrl = (new MarketingCampaignMail('Campaign', '<p>Message</p>', $user))
            ->content()
            ->with['unsubscribeUrl'];

        $query = [];
        parse_str((string) parse_url($unsubscribeUrl, PHP_URL_QUERY), $query);

        $this->assertSame('email', $query['channel'] ?? null);
        $this->assertArrayHasKey('expires', $query);
        $this->assertArrayHasKey('signature', $query);
    }

    private function unsubscribeUrl(SalesFormSubmission $submission, string $channel): string
    {
        $recipientReference = Crypt::encryptString(json_encode([
            'environment_id' => $submission->environment_id,
            'submission_id' => $submission->id,
        ], JSON_THROW_ON_ERROR));

        return URL::temporarySignedRoute('marketing.unsubscribe', now()->addDays(30), [
            'user' => $recipientReference,
            'channel' => $channel,
        ]);
    }

    private function submission(): SalesFormSubmission
    {
        $form = SalesForm::withoutGlobalScopes()->create([
            'environment_id' => $this->environment->id,
            'created_by' => $this->environment->owner_id,
            'title' => 'Unsubscribe form',
            'slug' => 'unsubscribe-form',
            'status' => SalesForm::STATUS_PUBLISHED,
        ]);

        return SalesFormSubmission::withoutGlobalScopes()->create([
            'environment_id' => $this->environment->id,
            'sales_form_id' => $form->id,
            'access_code' => 'UNSUBSCRIBE',
            'email' => 'unsubscribe@example.test',
            'answers' => [],
            'status' => SalesFormSubmission::STATUS_PENDING,
        ]);
    }
}
