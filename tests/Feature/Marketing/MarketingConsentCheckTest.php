<?php

namespace Tests\Feature\Marketing;

use App\Models\Branding;
use App\Models\Environment;
use App\Models\MarketingConsent;
use App\Models\SalesForm;
use App\Models\SalesFormSubmission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

class MarketingConsentCheckTest extends TestCase
{
    use RefreshDatabase;

    private Environment $environment;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.marketing_service.signing_secret' => 'marketing-service-test-secret',
            'services.marketing_service.clock_skew_seconds' => 300,
        ]);

        $owner = User::factory()->create();
        $this->environment = Environment::factory()->create(['owner_id' => $owner->id]);
    }

    public function test_a_revoked_channel_returns_false_while_another_channel_remains_granted(): void
    {
        $submission = $this->submission($this->environment);
        MarketingConsent::grant($submission, 'email', 'sales_form', '2026-09', CarbonImmutable::now());
        MarketingConsent::grant($submission, 'whatsapp', 'sales_form', '2026-09', CarbonImmutable::now());
        MarketingConsent::revoke($submission, 'email', 'unsubscribe', '2026-09', CarbonImmutable::now());

        $this->consentCheck($this->environment, $submission, 'email')
            ->assertOk()
            ->assertExactJson(['granted' => false]);

        $this->consentCheck($this->environment, $submission, 'whatsapp')
            ->assertOk()
            ->assertExactJson(['granted' => true]);
    }

    public function test_a_private_recheck_ignores_an_ambient_tenant_and_never_decorates_its_response(): void
    {
        $submission = $this->submission($this->environment);
        MarketingConsent::grant($submission, 'email', 'sales_form', '2026-09', CarbonImmutable::now());

        $foreignOwner = User::factory()->create();
        $foreignEnvironment = Environment::factory()->create([
            'owner_id' => $foreignOwner->id,
            'primary_domain' => 'foreign-consent.example.test',
        ]);
        Branding::factory()->create(['environment_id' => $foreignEnvironment->id]);

        $this->consentCheck($this->environment, $submission, 'email', $foreignEnvironment->primary_domain)
            ->assertOk()
            ->assertExactJson(['granted' => true]);
    }

    public function test_a_reference_cannot_be_used_for_a_different_environment(): void
    {
        Branding::factory()->create(['environment_id' => $this->environment->id]);
        $foreignOwner = User::factory()->create();
        $foreignEnvironment = Environment::factory()->create(['owner_id' => $foreignOwner->id]);
        $foreignSubmission = $this->submission($foreignEnvironment);
        MarketingConsent::grant($foreignSubmission, 'email', 'sales_form', '2026-09', CarbonImmutable::now());

        $this->consentCheck($this->environment, $foreignSubmission, 'email', $this->environment->primary_domain)
            ->assertUnprocessable()
            ->assertExactJson(['message' => 'The recipient reference is invalid.']);
    }

    private function consentCheck(Environment $environment, SalesFormSubmission $submission, string $channel, ?string $frontendDomain = null)
    {
        $payload = [
            'environment_id' => $environment->id,
            'recipient_ref' => Crypt::encryptString(json_encode([
                'environment_id' => $submission->environment_id,
                'submission_id' => $submission->id,
            ], JSON_THROW_ON_ERROR)),
            'channel' => $channel,
        ];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp = now()->getTimestamp();
        $nonce = 'consent-check-'.bin2hex(random_bytes(12));
        $signature = hash_hmac('sha256', implode("\n", [
            'POST',
            '/api/private/marketing/consent-check',
            $timestamp,
            $nonce,
            hash('sha256', $body),
        ]), 'marketing-service-test-secret');

        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_MARKETING_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_MARKETING_NONCE' => $nonce,
            'HTTP_X_MARKETING_SIGNATURE' => $signature,
        ];

        if ($frontendDomain !== null) {
            $headers['HTTP_X_FRONTEND_DOMAIN'] = $frontendDomain;
        }

        return $this->call('POST', '/api/private/marketing/consent-check', [], [], [], $headers, $body);
    }

    private function submission(Environment $environment): SalesFormSubmission
    {
        $form = SalesForm::withoutGlobalScopes()->create([
            'environment_id' => $environment->id,
            'created_by' => $environment->owner_id,
            'title' => 'Consent form',
            'slug' => 'consent-form-'.$environment->id,
            'status' => SalesForm::STATUS_PUBLISHED,
        ]);

        return SalesFormSubmission::withoutGlobalScopes()->create([
            'environment_id' => $environment->id,
            'sales_form_id' => $form->id,
            'access_code' => 'CONSENT'.str_pad((string) $environment->id, 2, '0', STR_PAD_LEFT),
            'email' => 'consent-'.$environment->id.'@example.test',
            'answers' => [],
            'status' => SalesFormSubmission::STATUS_PENDING,
        ]);
    }
}
