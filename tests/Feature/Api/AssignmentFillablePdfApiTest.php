<?php

namespace Tests\Feature\Api;

use App\Models\Activity;
use App\Models\AssignmentContent;
use App\Models\AssignmentSubmission;
use App\Models\DocumentationAttachment;
use App\Models\DocumentBlob;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Fillable-PDF assignments and the submission/review endpoints.
 *
 * The assignment API was unusable in production: the content controller never
 * set activities.content_id, so every submission lookup died on
 * ModelNotFoundException, learners could not list their own submissions, and
 * any viewer with template access could read a classmate's submission.
 */
class AssignmentFillablePdfApiTest extends TestCase
{
    use RefreshDatabase;

    private const PDF_BYTES = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF";

    // ---------------------------------------------------------------------
    // Fixtures
    // ---------------------------------------------------------------------

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function makeStructure(User $owner, bool $isPublic = false): array
    {
        $templateId = DB::table('templates')->insertGetId([
            'title' => 'Assignments',
            'status' => 'published',
            'is_public' => $isPublic,
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $blockId = DB::table('blocks')->insertGetId([
            'title' => 'Week 1',
            'order' => 0,
            'template_id' => $templateId,
            'status' => 'active',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $activityId = DB::table('activities')->insertGetId([
            'title' => 'Workbook',
            'type' => 'assignment',
            'order' => 0,
            'block_id' => $blockId,
            'status' => 'active',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$templateId, $blockId, $activityId];
    }

    private function makePdfAttachment(User $owner, ?int $environmentId = null): DocumentationAttachment
    {
        $attachment = DocumentationAttachment::create([
            'documentation_content_id' => null,
            'environment_id' => $environmentId,
            'storage' => 'database',
            'file_path' => 'db://workbook',
            'file_name' => 'workbook.pdf',
            'file_type' => 'application/pdf',
            'file_size' => strlen(self::PDF_BYTES),
            'created_by' => $owner->id,
        ]);

        DocumentBlob::create([
            'documentation_attachment_id' => $attachment->id,
            'payload' => base64_encode(self::PDF_BYTES),
            'mime_type' => 'application/pdf',
        ]);

        return $attachment;
    }

    /**
     * @return array<string, mixed>
     */
    private function fillablePdf(string $uuid): array
    {
        return [
            'document_uuid' => $uuid,
            'file_name' => 'workbook.pdf',
            'file_type' => 'application/pdf',
            'file_size' => strlen(self::PDF_BYTES),
            'page_sizes' => [
                ['width' => 612, 'height' => 792],
            ],
            'fields' => [
                [
                    'name' => 'full_name',
                    'page' => 1,
                    'type' => 'text',
                    'rect' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.5, 'h' => 0.05],
                    'required' => true,
                    'max_length' => 120,
                ],
                [
                    'name' => 'skills',
                    'page' => 1,
                    'type' => 'multiselect',
                    'rect' => ['x' => 0.1, 'y' => 0.2, 'w' => 0.5, 'h' => 0.1],
                    'required' => false,
                    'options' => ['php', 'laravel'],
                ],
                [
                    'name' => 'agree',
                    'page' => 1,
                    'type' => 'checkbox',
                    'rect' => ['x' => 0.1, 'y' => 0.35, 'w' => 0.05, 'h' => 0.02],
                    'required' => false,
                ],
                [
                    'name' => 'level',
                    'page' => 1,
                    'type' => 'select',
                    'rect' => ['x' => 0.1, 'y' => 0.4, 'w' => 0.5, 'h' => 0.05],
                    'required' => false,
                    'options' => ['beginner', 'advanced'],
                ],
            ],
        ];
    }

    private function makeContent(User $owner, int $activityId, string $uuid, array $overrides = []): AssignmentContent
    {
        return AssignmentContent::create(array_merge([
            'activity_id' => $activityId,
            'title' => 'Workbook',
            'description' => 'Complete the workbook.',
            'instructions' => 'Fill every field of the attached PDF.',
            'instruction_format' => 'plain',
            'submission_type' => 'fillable_pdf',
            'max_points' => 100,
            'fillable_pdf' => $this->fillablePdf($uuid),
            'created_by' => $owner->id,
        ], $overrides));
    }

    /**
     * @return array<string, mixed>
     */
    private function contentPayload(string $uuid, array $overrides = []): array
    {
        return array_merge([
            'title' => 'Workbook',
            'description' => 'Complete the workbook.',
            'instructions' => 'Fill every field of the attached PDF.',
            'instruction_format' => 'plain',
            'max_points' => 100,
            'submission_type' => 'fillable_pdf',
            'fillable_pdf' => $this->fillablePdf($uuid),
        ], $overrides);
    }

    /**
     * @return array<string, mixed>
     */
    private function valuesPayload(array $overrides = []): array
    {
        return array_merge([
            'fillable_data' => [
                'values' => [
                    'full_name' => 'Awa Nkeng',
                    'skills' => ['php', 'laravel'],
                    'agree' => true,
                    'level' => 'beginner',
                ],
            ],
        ], $overrides);
    }

    // ---------------------------------------------------------------------
    // Content endpoints
    // ---------------------------------------------------------------------

    public function test_it_stores_fillable_pdf_content_and_links_the_activity(): void
    {
        $owner = User::factory()->create(['role' => 'individual_teacher']);
        Sanctum::actingAs($owner);

        [$templateId, $blockId, $activityId] = $this->makeStructure($owner);
        $attachment = $this->makePdfAttachment($owner);

        $response = $this->postJson("/api/activities/{$activityId}/assignment", $this->contentPayload($attachment->uuid));

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.submission_type', 'fillable_pdf')
            ->assertJsonPath('data.fillable_pdf.document_uuid', $attachment->uuid)
            ->assertJsonPath('data.fillable_pdf.fields.0.name', 'full_name');

        $content = AssignmentContent::firstOrFail();
        $this->assertSame($attachment->uuid, $content->fillable_pdf['document_uuid']);

        $activity = Activity::findOrFail($activityId);
        $this->assertSame($content->id, (int) $activity->content_id);
        $this->assertSame(AssignmentContent::class, $activity->content_type);
    }

    public function test_it_rejects_an_invalid_rect_coordinate(): void
    {
        $owner = User::factory()->create(['role' => 'individual_teacher']);
        Sanctum::actingAs($owner);

        [, , $activityId] = $this->makeStructure($owner);
        $attachment = $this->makePdfAttachment($owner);

        $payload = $this->contentPayload($attachment->uuid);
        $payload['fillable_pdf']['fields'][0]['rect']['x'] = 1.5;

        $this->postJson("/api/activities/{$activityId}/assignment", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fillable_pdf.fields.0.rect.x']);
    }

    public function test_it_rejects_an_unsupported_field_type(): void
    {
        $owner = User::factory()->create(['role' => 'individual_teacher']);
        Sanctum::actingAs($owner);

        [, , $activityId] = $this->makeStructure($owner);
        $attachment = $this->makePdfAttachment($owner);

        $payload = $this->contentPayload($attachment->uuid);
        $payload['fillable_pdf']['fields'][0]['type'] = 'drawing';

        $this->postJson("/api/activities/{$activityId}/assignment", $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fillable_pdf.fields.0.type']);
    }

    public function test_it_rejects_an_unknown_document_uuid(): void
    {
        $owner = User::factory()->create(['role' => 'individual_teacher']);
        Sanctum::actingAs($owner);

        [, , $activityId] = $this->makeStructure($owner);

        $this->postJson(
            "/api/activities/{$activityId}/assignment",
            $this->contentPayload('00000000-0000-4000-8000-000000000000')
        )
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fillable_pdf.document_uuid']);
    }

    public function test_it_rejects_a_non_pdf_document(): void
    {
        $owner = User::factory()->create(['role' => 'individual_teacher']);
        Sanctum::actingAs($owner);

        [, , $activityId] = $this->makeStructure($owner);

        $attachment = DocumentationAttachment::create([
            'documentation_content_id' => null,
            'storage' => 'database',
            'file_path' => 'db://brief',
            'file_name' => 'brief.docx',
            'file_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'file_size' => 100,
            'created_by' => $owner->id,
        ]);

        $this->postJson("/api/activities/{$activityId}/assignment", $this->contentPayload($attachment->uuid))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fillable_pdf.document_uuid']);
    }

    public function test_it_updates_fillable_pdf_content_and_links_a_legacy_activity(): void
    {
        $owner = User::factory()->create(['role' => 'individual_teacher']);
        Sanctum::actingAs($owner);

        [, , $activityId] = $this->makeStructure($owner);
        $attachment = $this->makePdfAttachment($owner);

        // Legacy content: activity.content_id was never written.
        $content = $this->makeContent($owner, $activityId, $attachment->uuid, [
            'fillable_pdf' => null,
            'submission_type' => 'text',
        ]);
        $this->assertNull(Activity::findOrFail($activityId)->content_id);

        $response = $this->putJson("/api/activities/{$activityId}/assignment", [
            'submission_type' => 'fillable_pdf',
            'fillable_pdf' => $this->fillablePdf($attachment->uuid),
        ]);

        $response->assertOk()
            ->assertJsonPath('data.fillable_pdf.document_uuid', $attachment->uuid);

        $this->assertSame($attachment->uuid, $content->fresh()->fillable_pdf['document_uuid']);

        $activity = Activity::findOrFail($activityId);
        $this->assertSame($content->id, (int) $activity->content_id);
        $this->assertSame(AssignmentContent::class, $activity->content_type);
    }

    public function test_an_enrolled_learner_can_read_private_template_content_and_a_stranger_cannot(): void
    {
        $owner = User::factory()->create(['role' => 'individual_teacher']);
        $learner = User::factory()->create(['role' => 'learner']);
        $stranger = User::factory()->create(['role' => 'learner']);

        [$templateId, , $activityId] = $this->makeStructure($owner, isPublic: false);
        $attachment = $this->makePdfAttachment($owner);
        $this->makeContent($owner, $activityId, $attachment->uuid);

        Sanctum::actingAs($stranger);
        $this->getJson("/api/activities/{$activityId}/assignment")->assertForbidden();

        $courseId = DB::table('courses')->insertGetId([
            'title' => 'Course',
            'template_id' => $templateId,
            'created_by' => $owner->id,
            'status' => 'published',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('enrollments')->insert([
            'course_id' => $courseId,
            'user_id' => $learner->id,
            'status' => 'enrolled',
            'enrolled_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Sanctum::actingAs($learner);
        $this->getJson("/api/activities/{$activityId}/assignment")
            ->assertOk()
            ->assertJsonPath('data.fillable_pdf.document_uuid', $attachment->uuid);
    }

    // ---------------------------------------------------------------------
    // Submission endpoints
    // ---------------------------------------------------------------------

    public function test_a_draft_is_upserted_instead_of_creating_a_second_row(): void
    {
        $owner = User::factory()->create(['role' => 'individual_teacher']);
        $learner = User::factory()->create(['role' => 'learner']);
        Sanctum::actingAs($learner);

        [, , $activityId] = $this->makeStructure($owner);
        $attachment = $this->makePdfAttachment($owner);
        $content = $this->makeContent($owner, $activityId, $attachment->uuid);

        $first = $this->postJson(
            "/api/activities/{$activityId}/submissions",
            $this->valuesPayload(['status' => 'draft'])
        );

        $first->assertCreated()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.fillable_data.values.full_name', 'Awa Nkeng');

        $firstId = $first->json('data.id');

        $second = $this->postJson("/api/activities/{$activityId}/submissions", [
            'status' => 'draft',
            'fillable_data' => ['values' => ['full_name' => 'Awa N.']],
        ]);

        $second->assertCreated()
            ->assertJsonPath('data.id', $firstId)
            ->assertJsonPath('data.fillable_data.values.full_name', 'Awa N.');

        $this->assertSame(
            1,
            AssignmentSubmission::where('assignment_content_id', $content->id)->count()
        );
    }

    public function test_submitting_a_draft_converts_it_in_place_and_counts_the_attempt(): void
    {
        $owner = User::factory()->create(['role' => 'individual_teacher']);
        $learner = User::factory()->create(['role' => 'learner']);
        Sanctum::actingAs($learner);

        [, , $activityId] = $this->makeStructure($owner);
        $attachment = $this->makePdfAttachment($owner);
        $content = $this->makeContent($owner, $activityId, $attachment->uuid);

        $draft = $this->postJson("/api/activities/{$activityId}/submissions", $this->valuesPayload(['status' => 'draft']));
        $draftId = $draft->json('data.id');

        $submitted = $this->postJson("/api/activities/{$activityId}/submissions", [
            'fillable_data' => ['values' => ['full_name' => 'Awa Nkeng']],
        ]);

        $submitted->assertCreated()
            ->assertJsonPath('data.id', $draftId)
            ->assertJsonPath('data.status', 'submitted')
            ->assertJsonPath('data.attempt_number', 1)
            ->assertJsonPath('data.fillable_data.values.full_name', 'Awa Nkeng');

        $this->assertNotNull($submitted->json('data.submitted_at'));
        $this->assertSame(
            1,
            AssignmentSubmission::where('assignment_content_id', $content->id)
                ->where('status', '!=', 'draft')
                ->count()
        );

        // A second real submission is a new row and the next attempt.
        $resubmitted = $this->postJson("/api/activities/{$activityId}/submissions", $this->valuesPayload());
        $resubmitted->assertCreated()->assertJsonPath('data.attempt_number', 2);
    }

    public function test_a_submission_works_for_a_legacy_activity_without_content_id(): void
    {
        $owner = User::factory()->create(['role' => 'individual_teacher']);
        $learner = User::factory()->create(['role' => 'learner']);
        Sanctum::actingAs($learner);

        [, , $activityId] = $this->makeStructure($owner);
        $attachment = $this->makePdfAttachment($owner);
        $this->makeContent($owner, $activityId, $attachment->uuid);

        // The content controller was never run for this activity.
        $this->assertNull(Activity::findOrFail($activityId)->content_id);

        $this->postJson("/api/activities/{$activityId}/submissions", $this->valuesPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'submitted');
    }

    public function test_an_unknown_fillable_field_is_rejected(): void
    {
        $owner = User::factory()->create(['role' => 'individual_teacher']);
        $learner = User::factory()->create(['role' => 'learner']);
        Sanctum::actingAs($learner);

        [, , $activityId] = $this->makeStructure($owner);
        $attachment = $this->makePdfAttachment($owner);
        $this->makeContent($owner, $activityId, $attachment->uuid);

        $this->postJson("/api/activities/{$activityId}/submissions", [
            'fillable_data' => ['values' => ['not_a_field' => 'x']],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fillable_data.values.not_a_field']);
    }

    public function test_a_value_with_the_wrong_type_is_rejected(): void
    {
        $owner = User::factory()->create(['role' => 'individual_teacher']);
        $learner = User::factory()->create(['role' => 'learner']);
        Sanctum::actingAs($learner);

        [, , $activityId] = $this->makeStructure($owner);
        $attachment = $this->makePdfAttachment($owner);
        $this->makeContent($owner, $activityId, $attachment->uuid);

        $this->postJson("/api/activities/{$activityId}/submissions", [
            'fillable_data' => ['values' => ['agree' => 'yes']],
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fillable_data.values.agree']);
    }

    public function test_a_learner_only_lists_their_own_submissions_and_the_creator_lists_all(): void
    {
        $owner = User::factory()->create(['role' => 'individual_teacher']);
        $learner = User::factory()->create(['role' => 'learner']);
        $classmate = User::factory()->create(['role' => 'learner']);

        [, , $activityId] = $this->makeStructure($owner);
        $attachment = $this->makePdfAttachment($owner);
        $content = $this->makeContent($owner, $activityId, $attachment->uuid);

        $own = AssignmentSubmission::create([
            'assignment_content_id' => $content->id,
            'user_id' => $learner->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'attempt_number' => 1,
        ]);

        $theirs = AssignmentSubmission::create([
            'assignment_content_id' => $content->id,
            'user_id' => $classmate->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'attempt_number' => 1,
        ]);

        Sanctum::actingAs($learner);
        $response = $this->getJson("/api/activities/{$activityId}/submissions");
        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id);

        Sanctum::actingAs($owner);
        $creatorResponse = $this->getJson("/api/activities/{$activityId}/submissions");
        $creatorResponse->assertOk()->assertJsonCount(2, 'data');
        $this->assertEqualsCanonicalizing(
            [$own->id, $theirs->id],
            array_column($creatorResponse->json('data'), 'id')
        );
    }

    public function test_a_learner_cannot_read_a_classmates_submission_but_the_creator_can(): void
    {
        $owner = User::factory()->create(['role' => 'individual_teacher']);
        $learner = User::factory()->create(['role' => 'learner']);
        $classmate = User::factory()->create(['role' => 'learner']);

        [, , $activityId] = $this->makeStructure($owner);
        $attachment = $this->makePdfAttachment($owner);
        $content = $this->makeContent($owner, $activityId, $attachment->uuid);

        $submission = AssignmentSubmission::create([
            'assignment_content_id' => $content->id,
            'user_id' => $classmate->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'attempt_number' => 1,
        ]);

        Sanctum::actingAs($learner);
        $this->getJson("/api/activities/{$activityId}/submissions/{$submission->id}")
            ->assertForbidden();

        Sanctum::actingAs($owner);
        $this->getJson("/api/activities/{$activityId}/submissions/{$submission->id}")
            ->assertOk()
            ->assertJsonPath('data.assignment_content.fillable_pdf.document_uuid', $attachment->uuid);

        Sanctum::actingAs($classmate);
        $this->getJson("/api/activities/{$activityId}/submissions/{$submission->id}")
            ->assertOk()
            ->assertJsonPath('data.user.id', $classmate->id);
    }

    // ---------------------------------------------------------------------
    // Grading
    // ---------------------------------------------------------------------

    public function test_grading_with_corrections_returns_the_submission_for_correction(): void
    {
        $owner = User::factory()->create(['role' => 'individual_teacher']);
        $learner = User::factory()->create(['role' => 'learner']);

        [, , $activityId] = $this->makeStructure($owner);
        $attachment = $this->makePdfAttachment($owner);
        $content = $this->makeContent($owner, $activityId, $attachment->uuid);

        $submission = AssignmentSubmission::create([
            'assignment_content_id' => $content->id,
            'user_id' => $learner->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'attempt_number' => 1,
            'fillable_data' => ['values' => ['full_name' => 'Awa Nkeng']],
        ]);

        Sanctum::actingAs($owner);
        $response = $this->putJson("/api/activities/{$activityId}/submissions/{$submission->id}/grade", [
            'score' => 40,
            'feedback' => 'Please fix the name field.',
            'correction_data' => [
                'values' => ['full_name' => 'Awa Nkeng'],
                'comments' => ['full_name' => 'Use your full legal name.'],
                'overall' => 'One field needs another pass.',
            ],
            'return_for_correction' => true,
        ]);

        $response->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'returned')
            ->assertJsonPath('data.correction_data.values.full_name', 'Awa Nkeng')
            ->assertJsonPath('data.correction_data.comments.full_name', 'Use your full legal name.')
            ->assertJsonPath('data.correction_data.overall', 'One field needs another pass.')
            ->assertJsonPath('data.correction_data.corrected_by', $owner->id);

        $this->assertNotNull($response->json('data.correction_data.corrected_at'));
        $this->assertSame($owner->id, (int) $submission->fresh()->graded_by);
    }

    public function test_grading_without_corrections_marks_the_submission_graded(): void
    {
        $owner = User::factory()->create(['role' => 'individual_teacher']);
        $learner = User::factory()->create(['role' => 'learner']);

        [, , $activityId] = $this->makeStructure($owner);
        $attachment = $this->makePdfAttachment($owner);
        $content = $this->makeContent($owner, $activityId, $attachment->uuid);

        $submission = AssignmentSubmission::create([
            'assignment_content_id' => $content->id,
            'user_id' => $learner->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'attempt_number' => 1,
        ]);

        Sanctum::actingAs($owner);
        $this->putJson("/api/activities/{$activityId}/submissions/{$submission->id}/grade", [
            'score' => 92,
            'feedback' => 'Excellent.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'graded');
    }

    public function test_returning_for_correction_without_a_score_keeps_the_existing_score(): void
    {
        $owner = User::factory()->create(['role' => 'individual_teacher']);
        $learner = User::factory()->create(['role' => 'learner']);

        [, , $activityId] = $this->makeStructure($owner);
        $attachment = $this->makePdfAttachment($owner);
        $content = $this->makeContent($owner, $activityId, $attachment->uuid);

        $submission = AssignmentSubmission::create([
            'assignment_content_id' => $content->id,
            'user_id' => $learner->id,
            'status' => 'graded',
            'score' => 70,
            'graded_by' => $owner->id,
            'graded_at' => now(),
            'submitted_at' => now(),
            'attempt_number' => 1,
        ]);

        Sanctum::actingAs($owner);
        $this->putJson("/api/activities/{$activityId}/submissions/{$submission->id}/grade", [
            'feedback' => 'One more pass, please.',
            'return_for_correction' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'returned')
            ->assertJsonPath('data.score', 70);

        $this->assertSame(70, (int) $submission->fresh()->score);
    }

    public function test_returning_for_correction_without_a_score_leaves_a_missing_score_null(): void
    {
        $owner = User::factory()->create(['role' => 'individual_teacher']);
        $learner = User::factory()->create(['role' => 'learner']);

        [, , $activityId] = $this->makeStructure($owner);
        $attachment = $this->makePdfAttachment($owner);
        $content = $this->makeContent($owner, $activityId, $attachment->uuid);

        $submission = AssignmentSubmission::create([
            'assignment_content_id' => $content->id,
            'user_id' => $learner->id,
            'status' => 'submitted',
            'score' => null,
            'submitted_at' => now(),
            'attempt_number' => 1,
        ]);

        Sanctum::actingAs($owner);
        $this->putJson("/api/activities/{$activityId}/submissions/{$submission->id}/grade", [
            'return_for_correction' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'returned')
            ->assertJsonPath('data.score', null);

        $this->assertNull($submission->fresh()->score);
    }

    public function test_returning_for_correction_with_a_score_still_persists_it(): void
    {
        $owner = User::factory()->create(['role' => 'individual_teacher']);
        $learner = User::factory()->create(['role' => 'learner']);

        [, , $activityId] = $this->makeStructure($owner);
        $attachment = $this->makePdfAttachment($owner);
        $content = $this->makeContent($owner, $activityId, $attachment->uuid);

        $submission = AssignmentSubmission::create([
            'assignment_content_id' => $content->id,
            'user_id' => $learner->id,
            'status' => 'graded',
            'score' => 70,
            'submitted_at' => now(),
            'attempt_number' => 1,
        ]);

        Sanctum::actingAs($owner);
        $this->putJson("/api/activities/{$activityId}/submissions/{$submission->id}/grade", [
            'score' => 55,
            'return_for_correction' => true,
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'returned')
            ->assertJsonPath('data.score', 55);
    }

    public function test_grading_without_a_score_and_without_returning_is_rejected(): void
    {
        $owner = User::factory()->create(['role' => 'individual_teacher']);
        $learner = User::factory()->create(['role' => 'learner']);

        [, , $activityId] = $this->makeStructure($owner);
        $attachment = $this->makePdfAttachment($owner);
        $content = $this->makeContent($owner, $activityId, $attachment->uuid);

        $submission = AssignmentSubmission::create([
            'assignment_content_id' => $content->id,
            'user_id' => $learner->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'attempt_number' => 1,
        ]);

        Sanctum::actingAs($owner);
        $this->putJson("/api/activities/{$activityId}/submissions/{$submission->id}/grade", [
            'feedback' => 'No score yet.',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['score']);
    }

    public function test_a_correction_naming_an_unknown_field_is_rejected(): void
    {
        $owner = User::factory()->create(['role' => 'individual_teacher']);
        $learner = User::factory()->create(['role' => 'learner']);

        [, , $activityId] = $this->makeStructure($owner);
        $attachment = $this->makePdfAttachment($owner);
        $content = $this->makeContent($owner, $activityId, $attachment->uuid);

        $submission = AssignmentSubmission::create([
            'assignment_content_id' => $content->id,
            'user_id' => $learner->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'attempt_number' => 1,
        ]);

        Sanctum::actingAs($owner);
        $this->putJson("/api/activities/{$activityId}/submissions/{$submission->id}/grade", [
            'score' => 50,
            'correction_data' => [
                'values' => ['ghost_field' => 'x'],
            ],
            'return_for_correction' => true,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['correction_data.values.ghost_field']);
    }
}
