<?php

namespace Tests\Feature\Marketing;

use App\Models\Course;
use App\Models\Environment;
use App\Models\MarketingConsent;
use App\Models\Product;
use App\Models\SalesForm;
use App\Models\SalesFormField;
use App\Models\SalesFormSubmission;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SalesFormConsentCaptureTest extends TestCase
{
    use RefreshDatabase;

    public function test_unchecked_channel_consent_creates_no_marketing_grant(): void
    {
        $form = $this->makePublishedForm();
        $response = $this->submit($form, ['email' => false, 'whatsapp' => false]);

        $response->assertCreated();
        $this->assertDatabaseCount('marketing_consents', 0);
    }

    public function test_checked_email_consent_creates_only_an_email_grant(): void
    {
        $form = $this->makePublishedForm();
        $response = $this->submit($form, ['email' => true, 'whatsapp' => false]);
        $submission = SalesFormSubmission::withoutGlobalScopes()->findOrFail($response->json('submission_id'));

        $this->assertTrue(MarketingConsent::isGranted($submission, 'email'));
        $this->assertFalse(MarketingConsent::isGranted($submission, 'whatsapp'));
        $this->assertDatabaseHas('marketing_consents', [
            'sales_form_submission_id' => $submission->id,
            'environment_id' => $form->environment_id,
            'channel' => 'email',
            'status' => MarketingConsent::STATUS_GRANTED,
            'source' => 'sales_form',
            'terms_version' => '2026-09',
        ]);
        $this->assertDatabaseCount('marketing_consents', 1);
    }

    public function test_public_submission_cannot_attach_its_consent_to_another_environment(): void
    {
        $form = $this->makePublishedForm();
        $otherEnvironment = Environment::factory()->create();
        $response = $this->submit($form, ['email' => true, 'whatsapp' => true], ['environment_id' => $otherEnvironment->id]);
        $submission = SalesFormSubmission::withoutGlobalScopes()->findOrFail($response->json('submission_id'));

        $this->assertSame($form->environment_id, $submission->environment_id);
        $this->assertDatabaseCount('marketing_consents', 2);
        $this->assertDatabaseMissing('marketing_consents', [
            'environment_id' => $otherEnvironment->id,
            'sales_form_submission_id' => $submission->id,
        ]);
    }

    public function test_unapproved_terms_version_is_rejected_without_creating_a_submission(): void
    {
        $form = $this->makePublishedForm();

        $this->submit($form, ['email' => true, 'whatsapp' => false], [
            'marketing_terms_version' => 'made-up-terms',
        ])->assertUnprocessable()->assertJsonValidationErrors('marketing_terms_version');

        $this->assertDatabaseCount('sales_form_submissions', 0);
        $this->assertDatabaseCount('marketing_consents', 0);
    }

    private function makePublishedForm(): SalesForm
    {
        $owner = User::factory()->create(['role' => 'company_teacher']);
        $environment = Environment::factory()->create(['owner_id' => $owner->id]);
        $template = Template::create(['title' => 'Consent template', 'environment_id' => $environment->id, 'created_by' => $owner->id]);
        $course = Course::create(['title' => 'Consent course', 'environment_id' => $environment->id, 'created_by' => $owner->id, 'template_id' => $template->id]);
        $product = Product::create(['name' => 'Consent product', 'slug' => 'consent-product-'.$environment->id, 'price' => 10, 'currency' => 'USD', 'status' => 'active', 'environment_id' => $environment->id, 'created_by' => $owner->id]);
        $product->courses()->attach($course->id);
        $form = SalesForm::withoutGlobalScopes()->create(['environment_id' => $environment->id, 'created_by' => $owner->id, 'title' => 'Consent form', 'slug' => 'consent-form-'.$environment->id, 'status' => SalesForm::STATUS_PUBLISHED]);
        $form->products()->attach($product->id);
        SalesFormField::create(['sales_form_id' => $form->id, 'type' => 'phone', 'field_key' => 'phone', 'label' => 'Phone', 'is_required' => false, 'order' => 0]);

        return $form;
    }

    /** @param array{email: bool, whatsapp: bool} $consent @param array<string, mixed> $overrides */
    private function submit(SalesForm $form, array $consent, array $overrides = []): TestResponse
    {
        Event::fake();

        return $this->postJson("/api/sales-forms/public/{$form->slug}/submit", array_merge([
            'name' => 'Jane Learner', 'email' => 'jane@example.com', 'password' => 'password123',
            'answers' => ['phone' => '677 12 34 56'], 'marketing_consent' => $consent,
            'marketing_terms_version' => '2026-09',
        ], $overrides));
    }
}
