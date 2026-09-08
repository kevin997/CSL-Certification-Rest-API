<?php

namespace Tests\Feature\Marketing;

use App\Models\Environment;
use App\Models\MarketingConsent;
use App\Models\SalesForm;
use App\Models\SalesFormSubmission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LegacySalesFormConsentMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_backfill_grants_only_valid_missing_channel_states_and_is_idempotent(): void
    {
        $valid = $this->makeSubmission('valid@example.com', '677 12 34 56');
        $invalidEmail = $this->makeSubmission('not-an-email', '677 12 34 57');
        $invalidPhone = $this->makeSubmission('email-only@example.com', 'not-a-phone');
        $revokedEmail = $this->makeSubmission('revoked@example.com', '677 12 34 58');
        $this->makeSubmission('invalid@example', 'not-a-phone');

        MarketingConsent::revoke($revokedEmail, 'email', 'unsubscribe', '2026-09', CarbonImmutable::parse('2026-09-08 12:00:00 UTC'));

        $this->migration()->up();

        $this->assertDatabaseCount('marketing_consents', 6);
        $this->assertDatabaseHas('marketing_consents', ['sales_form_submission_id' => $valid->id, 'channel' => 'email', 'status' => MarketingConsent::STATUS_GRANTED, 'source' => 'legacy_sales_form', 'terms_version' => 'legacy-sales-form-v1']);
        $this->assertDatabaseHas('marketing_consents', ['sales_form_submission_id' => $valid->id, 'channel' => 'whatsapp', 'status' => MarketingConsent::STATUS_GRANTED, 'source' => 'legacy_sales_form', 'terms_version' => 'legacy-sales-form-v1']);
        $this->assertDatabaseMissing('marketing_consents', ['sales_form_submission_id' => $invalidEmail->id, 'channel' => 'email']);
        $this->assertDatabaseHas('marketing_consents', ['sales_form_submission_id' => $invalidEmail->id, 'channel' => 'whatsapp', 'status' => MarketingConsent::STATUS_GRANTED]);
        $this->assertDatabaseHas('marketing_consents', ['sales_form_submission_id' => $invalidPhone->id, 'channel' => 'email', 'status' => MarketingConsent::STATUS_GRANTED]);
        $this->assertDatabaseMissing('marketing_consents', ['sales_form_submission_id' => $invalidPhone->id, 'channel' => 'whatsapp']);
        $this->assertFalse(MarketingConsent::isGranted($revokedEmail, 'email'));
        $this->assertTrue(MarketingConsent::isGranted($revokedEmail, 'whatsapp'));
        $this->assertSame('2026-09-08 09:00:00', DB::table('marketing_consents')->where('sales_form_submission_id', $valid->id)->where('channel', 'email')->value('granted_at'));

        $this->migration()->up();

        $this->assertDatabaseCount('marketing_consents', 6);
    }

    private function migration(): object
    {
        return require database_path('migrations/2026_09_08_000002_backfill_sales_form_marketing_consents.php');
    }

    private function makeSubmission(string $email, string $phone): SalesFormSubmission
    {
        static $form;

        if (! $form instanceof SalesForm) {
            $owner = User::factory()->create();
            $environment = Environment::factory()->create(['owner_id' => $owner->id]);
            $form = SalesForm::withoutGlobalScopes()->create(['environment_id' => $environment->id, 'created_by' => $owner->id, 'title' => 'Legacy form', 'slug' => 'legacy-form', 'status' => SalesForm::STATUS_PUBLISHED]);
        }

        $submission = SalesFormSubmission::withoutGlobalScopes()->create([
            'sales_form_id' => $form->id, 'environment_id' => $form->environment_id,
            'access_code' => 'LEGACY'.str_pad((string) SalesFormSubmission::withoutGlobalScopes()->count(), 2, '0', STR_PAD_LEFT),
            'name' => 'Legacy Learner', 'email' => $email, 'phone' => $phone, 'answers' => [],
            'status' => SalesFormSubmission::STATUS_PENDING,
        ]);

        DB::table('sales_form_submissions')->where('id', $submission->id)->update([
            'created_at' => CarbonImmutable::parse('2026-09-08 09:00:00 UTC'),
            'updated_at' => CarbonImmutable::parse('2026-09-08 09:00:00 UTC'),
        ]);

        return $submission->fresh();

    }
}
