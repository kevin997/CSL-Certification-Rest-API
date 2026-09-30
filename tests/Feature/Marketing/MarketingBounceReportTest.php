<?php

namespace Tests\Feature\Marketing;

use App\Models\EmailSuppression;
use App\Models\Environment;
use App\Models\SalesForm;
use App\Models\SalesFormSubmission;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A hard bounce the marketing service's provider saw must block the address
 * for Kursa's own mail too, not only for the next campaign.
 */
class MarketingBounceReportTest extends TestCase
{
    use RefreshDatabase;

    private const PATH = '/api/private/marketing/bounces';

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

    public function test_a_reported_hard_bounce_blocks_the_submissions_address(): void
    {
        $submission = $this->submission($this->environment, 'Ghost@Example.test');

        $this->report($this->environment, $this->reference($submission), ['status_code' => '5.1.1', 'diagnostic' => 'Mailbox not found'])
            ->assertOk()
            ->assertExactJson(['suppressed' => true]);

        $suppression = EmailSuppression::sole();
        $this->assertSame('ghost@example.test', $suppression->email);
        $this->assertSame('marketing:sender', $suppression->source);
        $this->assertSame('5.1.1', $suppression->status_code);
    }

    public function test_reporting_the_same_bounce_twice_keeps_one_entry(): void
    {
        $submission = $this->submission($this->environment, 'ghost@example.test');

        $this->report($this->environment, $this->reference($submission))->assertOk();
        $this->report($this->environment, $this->reference($submission))->assertOk();

        $this->assertSame(1, EmailSuppression::query()->count());
    }

    public function test_a_reference_from_another_environment_blocks_nothing(): void
    {
        $foreign = Environment::factory()->create(['owner_id' => User::factory()->create()->id]);
        $submission = $this->submission($foreign, 'someone@example.test');

        $this->report($this->environment, $this->reference($submission))
            ->assertUnprocessable()
            ->assertExactJson(['message' => 'The recipient reference is invalid.']);

        $this->assertSame(0, EmailSuppression::query()->count());
    }

    public function test_an_unsigned_report_is_refused(): void
    {
        $submission = $this->submission($this->environment, 'ghost@example.test');
        $body = json_encode(['environment_id' => $this->environment->id, 'recipient_ref' => $this->reference($submission), 'provider' => 'sender'], JSON_THROW_ON_ERROR);

        $this->call('POST', self::PATH, [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)->assertUnauthorized();

        $this->assertSame(0, EmailSuppression::query()->count());
    }

    /** @param array<string, string> $extra */
    private function report(Environment $environment, string $reference, array $extra = []): TestResponse
    {
        $body = json_encode(['environment_id' => $environment->id, 'recipient_ref' => $reference, 'provider' => 'sender', ...$extra], JSON_THROW_ON_ERROR);
        $timestamp = now()->getTimestamp();
        $nonce = 'bounce-report-'.bin2hex(random_bytes(12));
        $signature = hash_hmac('sha256', implode("\n", ['POST', self::PATH, $timestamp, $nonce, hash('sha256', $body)]), 'marketing-service-test-secret');

        return $this->call('POST', self::PATH, [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_MARKETING_TIMESTAMP' => (string) $timestamp,
            'HTTP_X_MARKETING_NONCE' => $nonce,
            'HTTP_X_MARKETING_SIGNATURE' => $signature,
        ], $body);
    }

    private function reference(SalesFormSubmission $submission): string
    {
        return Crypt::encryptString(json_encode(['environment_id' => $submission->environment_id, 'submission_id' => $submission->id], JSON_THROW_ON_ERROR));
    }

    private function submission(Environment $environment, string $email): SalesFormSubmission
    {
        $form = SalesForm::withoutGlobalScopes()->create([
            'environment_id' => $environment->id,
            'created_by' => $environment->owner_id,
            'title' => 'Bounce form',
            'slug' => 'bounce-form-'.$environment->id,
            'status' => SalesForm::STATUS_PUBLISHED,
        ]);

        return SalesFormSubmission::withoutGlobalScopes()->create([
            'environment_id' => $environment->id,
            'sales_form_id' => $form->id,
            'access_code' => 'BOUNCE'.str_pad((string) $environment->id, 2, '0', STR_PAD_LEFT),
            'email' => $email,
            'answers' => [],
            'status' => SalesFormSubmission::STATUS_PENDING,
        ]);
    }
}
