<?php

namespace Tests\Feature\Marketing;

use App\Models\Environment;
use App\Models\MarketingConsent;
use App\Models\SalesForm;
use App\Models\SalesFormSubmission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SalesFormCampaignAudienceTest extends TestCase
{
    use RefreshDatabase;

    private Environment $environment;

    private User $owner;

    private SalesForm $form;

    protected function setUp(): void
    {
        parent::setUp();

        config(['licensing.enforcement_enabled' => false]);

        $this->owner = User::factory()->create();
        $this->environment = Environment::factory()->create([
            'owner_id' => $this->owner->id,
            'primary_domain' => 'audience.example.test',
            'country_code' => 'CM',
        ]);
        $this->form = SalesForm::withoutGlobalScopes()->create([
            'environment_id' => $this->environment->id,
            'created_by' => $this->owner->id,
            'title' => 'Audience form',
            'slug' => 'audience-form',
            'status' => SalesForm::STATUS_PUBLISHED,
        ]);
    }

    public function test_preview_table_covers_selected_all_and_supported_status_filter_without_contact_addresses(): void
    {
        $eligible = $this->submission('Person@Example.test');
        $duplicate = $this->submission(' person@example.test ');
        $missing = $this->submission(null);
        $invalid = $this->submission('not an address');
        $absent = $this->submission('absent@example.test');
        $revoked = $this->submission('revoked@example.test');
        $completed = $this->submission('completed@example.test', SalesFormSubmission::STATUS_COMPLETED);
        $foreign = $this->foreignSubmission('foreign@example.test');

        foreach ([$eligible, $duplicate, $completed] as $submission) {
            $this->grant($submission, 'email');
        }
        $this->grant($revoked, 'email');
        MarketingConsent::revoke($revoked, 'email', 'unsubscribe', '2026-09', CarbonImmutable::parse('2026-09-08 12:00:00 UTC'));

        $cases = [
            'selected' => [
                'selection' => ['mode' => 'selected', 'submission_ids' => [$foreign->id, $eligible->id, $duplicate->id]],
                'eligible_ids' => [$eligible->id],
                'exclusions' => ['missing_address' => 0, 'invalid_address' => 0, 'no_consent' => 0, 'revoked' => 0, 'duplicate' => 1],
            ],
            'all' => [
                'selection' => ['mode' => 'all'],
                'eligible_ids' => [$eligible->id, $completed->id],
                'exclusions' => ['missing_address' => 1, 'invalid_address' => 1, 'no_consent' => 1, 'revoked' => 1, 'duplicate' => 1],
            ],
            'filtered' => [
                'selection' => ['mode' => 'filtered', 'filters' => ['status' => SalesFormSubmission::STATUS_COMPLETED]],
                'eligible_ids' => [$completed->id],
                'exclusions' => ['missing_address' => 0, 'invalid_address' => 0, 'no_consent' => 0, 'revoked' => 0, 'duplicate' => 0],
            ],
        ];

        foreach ($cases as $name => $case) {
            $response = $this->preview($case['selection'], ['email']);

            $response->assertOk()
                ->assertJsonPath('success', true)
                ->assertJsonPath('data.channels.email.eligible_count', count($case['eligible_ids']))
                ->assertJsonPath('data.channels.email.submission_ids', $case['eligible_ids'])
                ->assertJsonPath('data.channels.email.exclusion_counts', $case['exclusions']);

            $this->assertStringNotContainsString('person@example.test', $response->getContent(), "{$name} preview leaked a contact address.");
            $this->assertStringNotContainsString('foreign@example.test', $response->getContent(), "{$name} preview leaked a foreign-environment contact address.");
        }
    }

    public function test_preview_uses_latest_consent_state_and_normalizes_whatsapp_before_deduplicating(): void
    {
        $eligible = $this->submission(null, SalesFormSubmission::STATUS_PENDING, '677 12 34 56');
        $duplicate = $this->submission(null, SalesFormSubmission::STATUS_PENDING, '+237677123456');
        $revoked = $this->submission(null, SalesFormSubmission::STATUS_PENDING, '677 00 00 00');

        $this->grant($eligible, 'whatsapp');
        $this->grant($duplicate, 'whatsapp');
        $this->grant($revoked, 'whatsapp');
        MarketingConsent::revoke($revoked, 'whatsapp', 'unsubscribe', '2026-09', CarbonImmutable::parse('2026-09-08 12:00:00 UTC'));

        $this->preview(['mode' => 'all'], ['whatsapp'])
            ->assertOk()
            ->assertJsonPath('data.channels.whatsapp.eligible_count', 1)
            ->assertJsonPath('data.channels.whatsapp.submission_ids', [$eligible->id])
            ->assertJsonPath('data.channels.whatsapp.exclusion_counts', [
                'missing_address' => 0,
                'invalid_address' => 0,
                'no_consent' => 0,
                'revoked' => 1,
                'duplicate' => 1,
            ]);
    }

    public function test_preview_rejects_mixed_selection_shapes_unknown_filters_and_channels(): void
    {
        foreach ([
            ['mode' => 'all', 'submission_ids' => [1]],
            ['mode' => 'selected', 'submission_ids' => [1], 'filters' => ['status' => 'pending']],
            ['mode' => 'filtered', 'filters' => ['created_after' => '2026-09-01']],
            ['mode' => 'filtered', 'filters' => ['status' => 'unknown']],
            ['mode' => 'filtered', 'filters' => ['status' => null]],
            ['mode' => 'filtered', 'filters' => []],
            ['mode' => 'filtered', 'filters' => ['name' => null]],
        ] as $selection) {
            $this->preview($selection, ['email'])->assertUnprocessable();
        }

        $this->preview(['mode' => 'all'], ['email', 'sms'])->assertUnprocessable();
    }

    public function test_preview_recomputes_the_current_name_search_filter(): void
    {
        $matching = $this->submission('match@example.test', name: 'Jane Learner');
        $other = $this->submission('other@example.test', name: 'John Instructor');
        $this->grant($matching, 'email');
        $this->grant($other, 'email');

        $this->preview(['mode' => 'filtered', 'filters' => ['name' => 'jane']], ['email'])
            ->assertOk()
            ->assertJsonPath('data.channels.email.submission_ids', [$matching->id]);
    }

    public function test_preview_treats_name_search_wildcards_and_escape_characters_as_literals(): void
    {
        $percent = $this->submission('percent@example.test', name: 'Save 100% Today');
        $percentOther = $this->submission('percent-other@example.test', name: 'Save 1000 Today');
        $underscore = $this->submission('underscore@example.test', name: 'A_B');
        $underscoreOther = $this->submission('underscore-other@example.test', name: 'A1B');
        $backslash = $this->submission('backslash@example.test', name: 'A\\B');
        $backslashOther = $this->submission('backslash-other@example.test', name: 'AXB');

        foreach ([$percent, $percentOther, $underscore, $underscoreOther, $backslash, $backslashOther] as $submission) {
            $this->grant($submission, 'email');
        }

        $this->preview(['mode' => 'filtered', 'filters' => ['name' => '%']], ['email'])
            ->assertOk()
            ->assertJsonPath('data.channels.email.submission_ids', [$percent->id]);

        $this->preview(['mode' => 'filtered', 'filters' => ['name' => '_']], ['email'])
            ->assertOk()
            ->assertJsonPath('data.channels.email.submission_ids', [$underscore->id]);

        $this->preview(['mode' => 'filtered', 'filters' => ['name' => '\\']], ['email'])
            ->assertOk()
            ->assertJsonPath('data.channels.email.submission_ids', [$backslash->id]);
    }

    public function test_preview_requires_an_authenticated_authorized_caller_for_the_form_environment(): void
    {
        $this->postJson("/api/sales-forms/{$this->form->id}/campaigns/audience-preview", [
            'selection' => ['mode' => 'all'],
            'channels' => ['email'],
        ], ['X-Frontend-Domain' => $this->environment->primary_domain])->assertUnauthorized();

        $otherOwner = User::factory()->create(['role' => 'company_teacher']);
        Environment::factory()->create(['owner_id' => $otherOwner->id]);

        $this->actingAs($otherOwner)
            ->postJson("/api/sales-forms/{$this->form->id}/campaigns/audience-preview", [
                'selection' => ['mode' => 'all'],
                'channels' => ['email'],
            ], ['X-Frontend-Domain' => $this->environment->primary_domain])
            ->assertForbidden();

        $learner = User::factory()->create(['role' => 'learner']);
        $this->actingAs($learner)
            ->postJson("/api/sales-forms/{$this->form->id}/campaigns/audience-preview", [
                'selection' => ['mode' => 'all'],
                'channels' => ['email'],
            ], ['X-Frontend-Domain' => $this->environment->primary_domain])
            ->assertForbidden();
    }

    public function test_preview_allows_an_environment_instructor_and_platform_role(): void
    {
        $instructor = User::factory()->create(['role' => 'individual_teacher']);
        $instructor->environments()->attach($this->environment->id, ['role' => 'instructor']);

        $this->actingAs($instructor)
            ->postJson("/api/sales-forms/{$this->form->id}/campaigns/audience-preview", [
                'selection' => ['mode' => 'all'],
                'channels' => ['email'],
            ], ['X-Frontend-Domain' => $this->environment->primary_domain])
            ->assertOk();

        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)
            ->postJson("/api/sales-forms/{$this->form->id}/campaigns/audience-preview", [
                'selection' => ['mode' => 'all'],
                'channels' => ['email'],
            ], ['X-Frontend-Domain' => $this->environment->primary_domain])
            ->assertOk();
    }

    public function test_preview_denies_a_creator_after_membership_removal_and_role_demotion(): void
    {
        $creator = User::factory()->create(['role' => 'individual_teacher']);
        $this->form->update(['created_by' => $creator->id]);
        $creator->environments()->attach($this->environment->id, ['role' => 'instructor']);
        $creator->environments()->detach($this->environment->id);
        $creator->update(['role' => 'learner']);

        $this->actingAs($creator)
            ->postJson("/api/sales-forms/{$this->form->id}/campaigns/audience-preview", [
                'selection' => ['mode' => 'all'],
                'channels' => ['email'],
            ], ['X-Frontend-Domain' => $this->environment->primary_domain])
            ->assertForbidden();
    }

    public function test_preview_batches_latest_consent_queries_when_processing_many_submissions(): void
    {
        foreach (range(1, 5) as $number) {
            $submission = $this->submission("batch{$number}@example.test", name: "Batch {$number}");
            $this->grant($submission, 'email');
            $this->grant($submission, 'whatsapp');
        }

        $consentQueries = 0;
        DB::listen(function (QueryExecuted $query) use (&$consentQueries): void {
            if (str_contains(strtolower($query->sql), 'marketing_consents')) {
                $consentQueries++;
            }
        });

        $this->preview(['mode' => 'all'], ['email', 'whatsapp'])->assertOk();

        $this->assertLessThanOrEqual(2, $consentQueries);
    }

    private function preview(array $selection, array $channels)
    {
        return $this->actingAs($this->owner)
            ->postJson("/api/sales-forms/{$this->form->id}/campaigns/audience-preview", [
                'selection' => $selection,
                'channels' => $channels,
            ], ['X-Frontend-Domain' => $this->environment->primary_domain]);
    }

    private function submission(?string $email, string $status = SalesFormSubmission::STATUS_PENDING, ?string $phone = null, ?string $name = null): SalesFormSubmission
    {
        return SalesFormSubmission::withoutGlobalScopes()->create([
            'sales_form_id' => $this->form->id,
            'environment_id' => $this->environment->id,
            'access_code' => 'AUD'.str_pad((string) SalesFormSubmission::withoutGlobalScopes()->count(), 5, '0', STR_PAD_LEFT),
            'email' => $email,
            'phone' => $phone,
            'name' => $name,
            'answers' => [],
            'status' => $status,
        ]);
    }

    private function foreignSubmission(string $email): SalesFormSubmission
    {
        $owner = User::factory()->create();
        $environment = Environment::factory()->create(['owner_id' => $owner->id]);
        $form = SalesForm::withoutGlobalScopes()->create([
            'environment_id' => $environment->id,
            'created_by' => $owner->id,
            'title' => 'Foreign form',
            'slug' => 'foreign-form',
            'status' => SalesForm::STATUS_PUBLISHED,
        ]);

        return SalesFormSubmission::withoutGlobalScopes()->create([
            'sales_form_id' => $form->id,
            'environment_id' => $environment->id,
            'access_code' => 'FOREIGN1',
            'email' => $email,
            'answers' => [],
            'status' => SalesFormSubmission::STATUS_PENDING,
        ]);
    }

    private function grant(SalesFormSubmission $submission, string $channel): void
    {
        MarketingConsent::grant($submission, $channel, 'sales_form', '2026-09', CarbonImmutable::parse('2026-09-08 11:00:00 UTC'));
    }
}
