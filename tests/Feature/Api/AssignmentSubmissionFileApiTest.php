<?php

namespace Tests\Feature\Api;

use App\Models\Activity;
use App\Models\AssignmentContent;
use App\Models\AssignmentSubmission;
use App\Models\AssignmentSubmissionFile;
use App\Models\Block;
use App\Models\Course;
use App\Models\DocumentationAttachment;
use App\Models\DocumentBlob;
use App\Models\Enrollment;
use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\Template;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

/**
 * Assignment submission files are stored in the private document store.
 *
 * The learner uploads the bytes first (POST .../submission-files), submits the
 * assignment with the returned uuid, and reads the file back through the
 * protected GET /documents/{uuid}/content endpoint. Cloudinary is no longer in
 * the path: its PDF delivery returns 401 on this account.
 */
class AssignmentSubmissionFileApiTest extends TestCase
{
    use RefreshDatabase;

    private Environment $environment;

    private User $teacher;

    private User $learner;

    private User $classmate;

    private Template $template;

    private Activity $activity;

    private AssignmentContent $content;

    private string $domain = 'assignment-files.test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'devices.enforced' => false,
        ]);

        $this->teacher = User::factory()->create(['role' => 'individual_teacher']);

        $this->environment = Environment::factory()->create([
            'owner_id' => $this->teacher->id,
            'primary_domain' => $this->domain,
        ]);

        $this->template = Template::create([
            'title' => 'Essay course',
            'created_by' => $this->teacher->id,
            'environment_id' => $this->environment->id,
        ]);

        $block = Block::create([
            'title' => 'Week 1',
            'template_id' => $this->template->id,
            'created_by' => $this->teacher->id,
        ]);

        $this->activity = Activity::create([
            'title' => 'Essay',
            'type' => 'assignment',
            'block_id' => $block->id,
            'created_by' => $this->teacher->id,
        ]);

        // The editor stores extensions both with and without the leading dot.
        $this->content = AssignmentContent::create([
            'activity_id' => $this->activity->id,
            'title' => 'Essay',
            'description' => 'Upload your essay.',
            'instructions' => 'PDF or DOCX only.',
            'instruction_format' => 'plain',
            'submission_type' => 'multiple_files',
            'max_points' => 100,
            'allowed_file_types' => ['.pdf', 'docx'],
            'max_file_size' => 2,
            'created_by' => $this->teacher->id,
        ]);

        $course = Course::create([
            'title' => 'Essay course',
            'template_id' => $this->template->id,
            'created_by' => $this->teacher->id,
            'environment_id' => $this->environment->id,
            'status' => 'published',
        ]);

        $this->learner = $this->makeLearner();
        $this->classmate = $this->makeLearner();

        foreach ([$this->learner, $this->classmate] as $learner) {
            Enrollment::create([
                'course_id' => $course->id,
                'user_id' => $learner->id,
                'environment_id' => $this->environment->id,
                'status' => Enrollment::STATUS_ENROLLED,
                'enrolled_at' => now(),
            ]);
        }
    }

    private function makeLearner(): User
    {
        $learner = User::factory()->create(['role' => 'learner']);

        EnvironmentUser::create([
            'environment_id' => $this->environment->id,
            'user_id' => $learner->id,
            'role' => 'learner',
            'is_account_setup' => true,
        ]);

        return $learner;
    }

    /**
     * @return array<string, string>
     */
    private function headers(string $domain = ''): array
    {
        return [
            'X-Frontend-Domain' => $domain !== '' ? $domain : $this->domain,
            'X-Device-Id' => (string) Str::uuid(),
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0',
        ];
    }

    private function pdfBytes(string $text = 'Submission essay'): string
    {
        $pdf = new Fpdi;
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(0, 10, $text);

        return $pdf->Output('S');
    }

    private function pdfUpload(string $name = 'essay.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, $this->pdfBytes());
    }

    private function makeAttachment(User $uploader, ?Environment $environment = null, string $fileName = 'essay.pdf'): DocumentationAttachment
    {
        $attachment = DocumentationAttachment::create([
            'documentation_content_id' => null,
            'environment_id' => ($environment ?? $this->environment)->id,
            'storage' => 'database',
            'file_path' => 'db://manual',
            'file_name' => $fileName,
            'file_type' => 'application/pdf',
            'file_size' => strlen($this->pdfBytes()),
            'created_by' => $uploader->id,
        ]);

        DocumentBlob::create([
            'documentation_attachment_id' => $attachment->id,
            'payload' => base64_encode($this->pdfBytes()),
            'mime_type' => 'application/pdf',
        ]);

        return $attachment;
    }

    // ---------------------------------------------------------------------
    // Upload
    // ---------------------------------------------------------------------

    public function test_an_enrolled_learner_uploads_a_file_into_the_document_store(): void
    {
        $response = $this->actingAs($this->learner, 'sanctum')
            ->withHeaders($this->headers())
            ->post("/api/activities/{$this->activity->id}/submission-files", [
                'file' => $this->pdfUpload('essay.pdf'),
                'name' => 'final essay',
            ]);

        $response->assertCreated()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.name', 'final essay.pdf')
            ->assertJsonPath('data.file_type', 'application/pdf')
            ->assertJsonPath('data.can_preview', true);

        $uuid = (string) $response->json('data.uuid');
        $this->assertTrue(Str::isUuid($uuid));

        $attachment = DocumentationAttachment::query()->where('uuid', $uuid)->firstOrFail();
        $this->assertNull($attachment->documentation_content_id);
        $this->assertSame($this->environment->id, (int) $attachment->environment_id);
        $this->assertSame($this->learner->id, (int) $attachment->created_by);
        $this->assertSame('database', $attachment->storage);
        $this->assertSame('db://'.$uuid, $attachment->file_path);
        $this->assertGreaterThan(0, (int) $attachment->file_size);

        $this->assertDatabaseHas('document_blobs', [
            'documentation_attachment_id' => $attachment->id,
        ]);
    }

    public function test_a_learner_enrolled_in_a_different_course_cannot_upload(): void
    {
        $otherTemplate = Template::create([
            'title' => 'Other course',
            'created_by' => $this->teacher->id,
            'environment_id' => $this->environment->id,
        ]);

        $otherCourse = Course::create([
            'title' => 'Other course',
            'template_id' => $otherTemplate->id,
            'created_by' => $this->teacher->id,
            'environment_id' => $this->environment->id,
            'status' => 'published',
        ]);

        $outsider = $this->makeLearner();

        Enrollment::create([
            'course_id' => $otherCourse->id,
            'user_id' => $outsider->id,
            'environment_id' => $this->environment->id,
            'status' => Enrollment::STATUS_ENROLLED,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($outsider, 'sanctum')
            ->withHeaders($this->headers())
            ->post("/api/activities/{$this->activity->id}/submission-files", [
                'file' => $this->pdfUpload(),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('documentation_attachments', 0);
    }

    public function test_a_disallowed_extension_is_rejected(): void
    {
        $this->actingAs($this->learner, 'sanctum')
            ->withHeaders($this->headers())
            ->post("/api/activities/{$this->activity->id}/submission-files", [
                'file' => UploadedFile::fake()->createWithContent('malware.exe', 'MZ binary'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_a_file_larger_than_the_assignment_limit_is_rejected(): void
    {
        // The assignment caps files at 2 MB.
        $this->actingAs($this->learner, 'sanctum')
            ->withHeaders($this->headers())
            ->post("/api/activities/{$this->activity->id}/submission-files", [
                'file' => UploadedFile::fake()->create('large.pdf', 3 * 1024, 'application/pdf'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    public function test_an_empty_file_is_rejected(): void
    {
        $this->actingAs($this->learner, 'sanctum')
            ->withHeaders($this->headers())
            ->post("/api/activities/{$this->activity->id}/submission-files", [
                'file' => UploadedFile::fake()->create('empty.pdf', 0, 'application/pdf'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file']);
    }

    // ---------------------------------------------------------------------
    // Submission store
    // ---------------------------------------------------------------------

    public function test_submitting_with_a_document_uuid_creates_the_file_row(): void
    {
        $attachment = $this->makeAttachment($this->learner);

        $response = $this->actingAs($this->learner, 'sanctum')
            ->withHeaders($this->headers())
            ->postJson("/api/activities/{$this->activity->id}/submissions", [
                'files' => [
                    ['document_uuid' => $attachment->uuid, 'name' => 'My essay.pdf'],
                ],
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.files.0.document_uuid', $attachment->uuid)
            ->assertJsonPath('data.files.0.file_path', 'doc://'.$attachment->uuid)
            ->assertJsonPath('data.files.0.file_name', 'My essay.pdf')
            ->assertJsonPath('data.files.0.file_type', 'application/pdf');

        $this->assertDatabaseHas('assignment_submission_files', [
            'assignment_submission_id' => $response->json('data.id'),
            'document_uuid' => $attachment->uuid,
            'file_path' => 'doc://'.$attachment->uuid,
        ]);
    }

    public function test_submitting_with_a_document_uuid_without_a_name_uses_the_attachment_name(): void
    {
        $attachment = $this->makeAttachment($this->learner, null, 'draft.pdf');

        $this->actingAs($this->learner, 'sanctum')
            ->withHeaders($this->headers())
            ->postJson("/api/activities/{$this->activity->id}/submissions", [
                'files' => [
                    ['document_uuid' => $attachment->uuid],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.files.0.file_name', 'draft.pdf');
    }

    public function test_the_legacy_cloudinary_file_shape_is_still_accepted(): void
    {
        $this->actingAs($this->learner, 'sanctum')
            ->withHeaders($this->headers())
            ->postJson("/api/activities/{$this->activity->id}/submissions", [
                'files' => [
                    [
                        'file_path' => 'https://res.cloudinary.com/demo/raw/upload/essay.pdf',
                        'file_name' => 'essay.pdf',
                        'file_type' => 'pdf',
                        'file_size' => 1024,
                    ],
                ],
            ])
            ->assertCreated()
            ->assertJsonPath('data.files.0.document_uuid', null)
            ->assertJsonPath('data.files.0.file_path', 'https://res.cloudinary.com/demo/raw/upload/essay.pdf');
    }

    public function test_a_document_from_another_environment_is_rejected_on_submit(): void
    {
        $otherEnvironment = Environment::factory()->create([
            'owner_id' => $this->teacher->id,
            'primary_domain' => 'foreign-assignment-files.test',
        ]);

        $foreign = $this->makeAttachment($this->learner, $otherEnvironment);

        $this->actingAs($this->learner, 'sanctum')
            ->withHeaders($this->headers())
            ->postJson("/api/activities/{$this->activity->id}/submissions", [
                'files' => [
                    ['document_uuid' => $foreign->uuid],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['files.0.document_uuid']);
    }

    public function test_an_unknown_document_uuid_is_rejected_on_submit(): void
    {
        $this->actingAs($this->learner, 'sanctum')
            ->withHeaders($this->headers())
            ->postJson("/api/activities/{$this->activity->id}/submissions", [
                'files' => [
                    ['document_uuid' => (string) Str::uuid()],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['files.0.document_uuid']);
    }

    public function test_index_and_show_expose_the_document_uuid(): void
    {
        $attachment = $this->makeAttachment($this->learner);

        $created = $this->actingAs($this->learner, 'sanctum')
            ->withHeaders($this->headers())
            ->postJson("/api/activities/{$this->activity->id}/submissions", [
                'files' => [
                    ['document_uuid' => $attachment->uuid],
                ],
            ])
            ->assertCreated();

        $submissionId = $created->json('data.id');

        $this->getJson(
            "/api/activities/{$this->activity->id}/submissions",
            $this->headers(),
        )
            ->assertOk()
            ->assertJsonPath('data.0.files.0.document_uuid', $attachment->uuid);

        $this->getJson(
            "/api/activities/{$this->activity->id}/submissions/{$submissionId}",
            $this->headers(),
        )
            ->assertOk()
            ->assertJsonPath('data.files.0.document_uuid', $attachment->uuid);
    }

    // ---------------------------------------------------------------------
    // Protected document access
    // ---------------------------------------------------------------------

    public function test_the_owner_can_read_the_submission_file_a_classmate_cannot_and_the_creator_can(): void
    {
        $attachment = $this->makeAttachment($this->learner);

        $this->actingAs($this->learner, 'sanctum')
            ->withHeaders($this->headers())
            ->postJson("/api/activities/{$this->activity->id}/submissions", [
                'files' => [
                    ['document_uuid' => $attachment->uuid],
                ],
            ])
            ->assertCreated();

        $this->actingAs($this->learner, 'sanctum')
            ->withHeaders($this->headers())
            ->get("/api/documents/{$attachment->uuid}/content")
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Document-Id', $attachment->uuid);

        // A classmate enrolled in the same course must not read another
        // learner's work.
        $this->actingAs($this->classmate, 'sanctum')
            ->withHeaders($this->headers())
            ->get("/api/documents/{$attachment->uuid}/content")
            ->assertForbidden();

        // The template creator reviews it.
        $this->actingAs($this->teacher, 'sanctum')
            ->withHeaders($this->headers())
            ->get("/api/documents/{$attachment->uuid}/content")
            ->assertOk();
    }

    public function test_an_uploader_can_read_their_file_before_it_is_submitted(): void
    {
        $attachment = $this->makeAttachment($this->learner);

        $this->actingAs($this->learner, 'sanctum')
            ->withHeaders($this->headers())
            ->get("/api/documents/{$attachment->uuid}/content")
            ->assertOk();
    }

    public function test_a_submission_file_cannot_be_read_by_a_learner_without_an_enrollment(): void
    {
        $attachment = $this->makeAttachment($this->learner);

        $stranger = $this->makeLearner();

        $this->actingAs($stranger, 'sanctum')
            ->withHeaders($this->headers())
            ->get("/api/documents/{$attachment->uuid}/content")
            ->assertForbidden();
    }

    public function test_another_learners_submission_file_is_not_exposed_through_the_submission_response(): void
    {
        $attachment = $this->makeAttachment($this->classmate);

        $submission = AssignmentSubmission::create([
            'assignment_content_id' => $this->content->id,
            'user_id' => $this->classmate->id,
            'status' => 'submitted',
            'submitted_at' => now(),
            'attempt_number' => 1,
        ]);

        AssignmentSubmissionFile::create([
            'assignment_submission_id' => $submission->id,
            'document_uuid' => $attachment->uuid,
            'file_path' => 'doc://'.$attachment->uuid,
            'file_name' => $attachment->file_name,
            'file_type' => $attachment->file_type,
            'file_size' => $attachment->file_size,
        ]);

        Sanctum::actingAs($this->learner);
        $this->getJson("/api/activities/{$this->activity->id}/submissions/{$submission->id}")
            ->assertForbidden();
    }
}
