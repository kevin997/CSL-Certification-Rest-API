<?php

namespace Tests\Feature\Marketing;

use App\Models\Environment;
use App\Models\MarketingConsent;
use App\Models\SalesForm;
use App\Models\SalesFormSubmission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingConsentTest extends TestCase
{
    use RefreshDatabase;

    public function test_revoking_email_consent_keeps_auditable_history_and_sets_current_state_to_revoked(): void
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
        $submission = SalesFormSubmission::withoutGlobalScopes()->create([
            'sales_form_id' => $form->id,
            'environment_id' => $environment->id,
            'access_code' => 'CONSENT1',
            'name' => 'Jane Learner',
            'email' => 'jane@example.com',
            'answers' => [],
            'status' => SalesFormSubmission::STATUS_PENDING,
        ]);

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
}
