<?php

namespace Tests\Feature\Marketing;

use App\Models\Branding;
use App\Models\Environment;
use App\Models\SalesForm;
use App\Models\SalesFormSubmission;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class MarketingServiceSignatureTest extends TestCase
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

    public function test_a_valid_signature_allows_the_private_consent_request(): void
    {
        $response = $this->signedRequest($this->payload());

        $response->assertOk();
    }

    public function test_a_wrong_signature_is_rejected(): void
    {
        Branding::factory()->create(['environment_id' => $this->environment->id]);

        $this->signedRequest(
            $this->payload(),
            signature: 'not-a-valid-signature',
            frontendDomain: $this->environment->primary_domain,
        )
            ->assertUnauthorized()
            ->assertExactJson(['message' => 'Unauthenticated.']);
    }

    public function test_an_expired_timestamp_is_rejected(): void
    {
        $this->signedRequest($this->payload(), timestamp: now()->subSeconds(301)->getTimestamp())
            ->assertUnauthorized();
    }

    public function test_a_nonce_cannot_be_replayed(): void
    {
        $payload = $this->payload();
        $timestamp = now()->getTimestamp();
        $nonce = 'replayed-marketing-nonce';

        $this->signedRequest($payload, timestamp: $timestamp, nonce: $nonce)->assertOk();
        $this->signedRequest($payload, timestamp: $timestamp, nonce: $nonce)->assertUnauthorized();
    }

    public function test_tampering_with_the_raw_body_invalidates_the_signature(): void
    {
        $payload = $this->payload();
        $signedBody = json_encode($payload, JSON_THROW_ON_ERROR);
        $tamperedBody = json_encode([...$payload, 'channel' => 'whatsapp'], JSON_THROW_ON_ERROR);
        $timestamp = now()->getTimestamp();
        $nonce = 'tampered-marketing-nonce';

        $signature = $this->signature($signedBody, $timestamp, $nonce);

        $this->call('POST', '/api/private/marketing/consent-check', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_MARKETING_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_MARKETING_NONCE' => $nonce,
            'HTTP_X_MARKETING_SIGNATURE' => $signature,
        ], $tamperedBody)->assertUnauthorized();
    }

    public function test_missing_service_secret_fails_closed(): void
    {
        config(['services.marketing_service.signing_secret' => null]);

        $this->signedRequest($this->payload())->assertUnauthorized();
    }

    public function test_a_throttled_private_request_is_not_decorated_by_the_resolved_tenant(): void
    {
        Branding::factory()->create(['environment_id' => $this->environment->id]);
        RateLimiter::for('public-api', fn () => Limit::perMinute(1)->by('private-marketing-throttle'));
        $payload = $this->payload();

        $this->signedRequest($payload, frontendDomain: $this->environment->primary_domain)->assertOk();

        $this->signedRequest($payload, frontendDomain: $this->environment->primary_domain)
            ->assertTooManyRequests()
            ->assertExactJson(['message' => 'Too Many Attempts.']);
    }

    /** @return array{environment_id: int, recipient_ref: string, channel: string} */
    private function payload(): array
    {
        $submission = $this->submission();

        return [
            'environment_id' => $this->environment->id,
            'recipient_ref' => Crypt::encryptString(json_encode([
                'environment_id' => $this->environment->id,
                'submission_id' => $submission->id,
            ], JSON_THROW_ON_ERROR)),
            'channel' => 'email',
        ];
    }

    private function signedRequest(
        array $payload,
        ?int $timestamp = null,
        ?string $nonce = null,
        ?string $signature = null,
        ?string $frontendDomain = null,
    ) {
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $timestamp ??= now()->getTimestamp();
        $nonce ??= 'nonce-'.bin2hex(random_bytes(12));
        $signature ??= $this->signature($body, $timestamp, $nonce);

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

    private function signature(string $body, int $timestamp, string $nonce): string
    {
        return hash_hmac('sha256', implode("\n", [
            'POST',
            '/api/private/marketing/consent-check',
            $timestamp,
            $nonce,
            hash('sha256', $body),
        ]), 'marketing-service-test-secret');
    }

    private function submission(): SalesFormSubmission
    {
        $form = SalesForm::withoutGlobalScopes()->create([
            'environment_id' => $this->environment->id,
            'created_by' => $this->environment->owner_id,
            'title' => 'Signature form',
            'slug' => 'signature-form',
            'status' => SalesForm::STATUS_PUBLISHED,
        ]);

        return SalesFormSubmission::withoutGlobalScopes()->create([
            'environment_id' => $this->environment->id,
            'sales_form_id' => $form->id,
            'access_code' => 'SIGCHECK',
            'email' => 'signature@example.test',
            'answers' => [],
            'status' => SalesFormSubmission::STATUS_PENDING,
        ]);
    }
}
