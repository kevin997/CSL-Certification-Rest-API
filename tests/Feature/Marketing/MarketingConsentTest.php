<?php

namespace Tests\Feature\Marketing;

use App\Models\Environment;
use App\Models\MarketingConsent;
use App\Models\SalesForm;
use App\Models\SalesFormSubmission;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MarketingConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_revoking_email_consent_keeps_auditable_history_and_sets_current_state_to_revoked(): void
    {
        $submission = $this->makeSubmission();

        $grantedAt = CarbonImmutable::parse('2026-09-08 09:00:00 UTC');
        $revokedAt = CarbonImmutable::parse('2026-09-08 10:00:00 UTC');

        MarketingConsent::grant($submission, 'email', 'sales_form', '2026-09', $grantedAt);
        MarketingConsent::revoke($submission, 'email', 'unsubscribe', '2026-09', $revokedAt);

        $this->assertFalse(MarketingConsent::isGranted($submission, 'email'));
        $this->assertCount(2, $submission->marketingConsents()->get());
        $this->assertDatabaseHas('marketing_consents', [
            'sales_form_submission_id' => $submission->id,
            'channel' => 'email',
            'status' => 'granted',
            'source' => 'sales_form',
            'terms_version' => '2026-09',
        ]);
        $this->assertDatabaseHas('marketing_consents', [
            'sales_form_submission_id' => $submission->id,
            'channel' => 'email',
            'status' => 'revoked',
            'source' => 'unsubscribe',
            'terms_version' => '2026-09',
        ]);
    }

    public function test_existing_consent_events_cannot_be_updated_or_deleted(): void
    {
        $consent = MarketingConsent::grant(
            $this->makeSubmission(),
            'email',
            'sales_form',
            '2026-09',
            CarbonImmutable::parse('2026-09-08 09:00:00 UTC')
        );

        try {
            $consent->update(['status' => MarketingConsent::STATUS_REVOKED]);
            $this->fail('Existing consent events must not be updated.');
        } catch (\LogicException) {
            // Expected: consent state changes append a new event.
        }

        try {
            $consent->delete();
            $this->fail('Existing consent events must not be deleted.');
        } catch (\LogicException) {
            // Expected: consent events remain available for audit.
        }

        $this->assertSame(MarketingConsent::STATUS_GRANTED, $consent->fresh()->status);
        $this->assertDatabaseCount('marketing_consents', 1);
    }

    public function test_consent_event_timestamps_are_stored_as_utc_without_mutating_the_supplied_instant(): void
    {
        $at = Carbon::parse('2026-09-08 10:00:00', 'Africa/Douala');
        $consent = MarketingConsent::grant(
            $this->makeSubmission(),
            'email',
            'sales_form',
            '2026-09',
            $at
        );

        $this->assertSame('Africa/Douala', $at->getTimezone()->getName());
        $this->assertSame('2026-09-08 10:00:00', $at->format('Y-m-d H:i:s'));
        $this->assertSame(
            '2026-09-08 09:00:00',
            DB::table('marketing_consents')->where('id', $consent->id)->value('granted_at')
        );
        $this->assertSame(
            '2026-09-08T09:00:00+00:00',
            $consent->fresh()->granted_at->toIso8601String()
        );
    }

    private function makeSubmission(): SalesFormSubmission
    {
        $owner = User::factory()->create();
        $environment = Environment::factory()->create(['owner_id' => $owner->id]);
        $form = SalesForm::withoutGlobalScopes()->create([
            'environment_id' => $environment->id,
            'created_by' => $owner->id,
            'title' => 'Lead form',
            'slug' => 'lead-form',
            'status' => SalesForm::STATUS_PUBLISHED,
        ]);

        return SalesFormSubmission::withoutGlobalScopes()->create([
            'sales_form_id' => $form->id,
            'environment_id' => $environment->id,
            'access_code' => 'CONSENT1',
            'name' => 'Jane Learner',
            'email' => 'jane@example.com',
            'answers' => [],
            'status' => SalesFormSubmission::STATUS_PENDING,
        ]);
    }
}
