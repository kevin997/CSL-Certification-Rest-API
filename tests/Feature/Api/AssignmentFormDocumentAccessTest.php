<?php

namespace Tests\Feature\Api;

use App\Models\Activity;
use App\Models\AssignmentContent;
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
use Illuminate\Support\Str;
use setasign\Fpdi\Fpdi;
use Tests\TestCase;

/**
 * Fillable-PDF bytes come from the private document store, not Cloudinary.
 *
 * The generic document endpoint watermarks PDFs by re-importing every page,
 * which drops AcroForm fields. GET /api/documents/{uuid}/form-content streams
 * the stored bytes untouched, but only to people who may read the assignment
 * content that references them.
 */
class AssignmentFormDocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    private Environment $environment;

    private User $teacher;

    private User $learner;

    private Template $privateTemplate;

    private Activity $assignmentActivity;

    private DocumentationAttachment $attachment;

    private string $domain = 'assignment-forms.test';

    private string $pdf;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'devices.enforced' => true,
            'devices.learner_roles' => ['learner'],
        ]);

        $this->teacher = User::factory()->create(['role' => 'individual_teacher']);

        $this->environment = Environment::factory()->create([
            'owner_id' => $this->teacher->id,
            'primary_domain' => $this->domain,
        ]);

        $this->privateTemplate = Template::create([
            'title' => 'Workbook course',
            'created_by' => $this->teacher->id,
            'environment_id' => $this->environment->id,
        ]);

        $this->assignmentActivity = Activity::create([
            'title' => 'Workbook',
            'type' => 'assignment',
            'block_id' => $this->makeBlock($this->privateTemplate)->id,
            'created_by' => $this->teacher->id,
        ]);

        $this->pdf = $this->makePdf();

        $this->attachment = DocumentationAttachment::create([
            'documentation_content_id' => null,
            'environment_id' => $this->environment->id,
            'storage' => 'database',
            'file_path' => 'db://assignment-workbook',
            'file_name' => 'workbook.pdf',
            'file_type' => 'application/pdf',
            'file_size' => strlen($this->pdf),
            'created_by' => $this->teacher->id,
        ]);

        DocumentBlob::create([
            'documentation_attachment_id' => $this->attachment->id,
            'payload' => base64_encode($this->pdf),
            'mime_type' => 'application/pdf',
        ]);

        AssignmentContent::create([
            'activity_id' => $this->assignmentActivity->id,
            'title' => 'Workbook',
            'description' => 'Fill the workbook.',
            'instructions' => 'Complete every field.',
            'instruction_format' => 'plain',
            'submission_type' => 'fillable_pdf',
            'max_points' => 100,
            'fillable_pdf' => [
                'document_uuid' => $this->attachment->uuid,
                'file_name' => 'workbook.pdf',
                'file_type' => 'application/pdf',
                'file_size' => strlen($this->pdf),
                'page_sizes' => [['width' => 612, 'height' => 792]],
                'fields' => [[
                    'name' => 'full_name',
                    'page' => 1,
                    'type' => 'text',
                    'rect' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.5, 'h' => 0.05],
                    'required' => true,
                ]],
            ],
            'created_by' => $this->teacher->id,
        ]);

        $this->learner = User::factory()->create([
            'name' => 'Enrolled Learner',
            'role' => 'learner',
        ]);

        EnvironmentUser::create([
            'environment_id' => $this->environment->id,
            'user_id' => $this->learner->id,
            'role' => 'learner',
            'is_account_setup' => true,
        ]);

        $course = Course::create([
            'title' => 'Workbook course',
            'template_id' => $this->privateTemplate->id,
            'created_by' => $this->teacher->id,
            'environment_id' => $this->environment->id,
            'status' => 'published',
        ]);

        Enrollment::create([
            'course_id' => $course->id,
            'user_id' => $this->learner->id,
            'environment_id' => $this->environment->id,
            'status' => Enrollment::STATUS_ENROLLED,
            'enrolled_at' => now(),
        ]);
    }

    private function makeBlock(Template $template): Block
    {
        return Block::create([
            'title' => 'Week 1',
            'template_id' => $template->id,
            'created_by' => $this->teacher->id,
        ]);
    }

    private function makePdf(): string
    {
        $pdf = new Fpdi;
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(0, 10, 'Original fillable workbook');

        return $pdf->Output('S');
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

    public function test_an_enrolled_learner_receives_the_untouched_pdf_bytes(): void
    {
        $response = $this->actingAs($this->learner, 'sanctum')
            ->withHeaders($this->headers())
            ->get("/api/documents/{$this->attachment->uuid}/form-content");

        $response->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Document-Id', $this->attachment->uuid);

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));

        // Byte-identical: no page re-import, no watermark, no AcroForm loss.
        $this->assertSame($this->pdf, $response->getContent());
        $this->assertStringNotContainsString('access #', $response->getContent());
        $this->assertStringNotContainsString('Enrolled Learner', $response->getContent());

        $this->assertDatabaseHas('document_access_logs', [
            'documentation_attachment_id' => $this->attachment->id,
            'user_id' => $this->learner->id,
        ]);
    }

    public function test_the_template_creator_receives_the_untouched_pdf_bytes(): void
    {
        $response = $this->actingAs($this->teacher, 'sanctum')
            ->withHeaders($this->headers())
            ->get("/api/documents/{$this->attachment->uuid}/form-content");

        $response->assertOk();
        $this->assertSame($this->pdf, $response->getContent());
    }

    public function test_a_learner_without_an_enrollment_is_refused(): void
    {
        Enrollment::query()->delete();

        $this->actingAs($this->learner, 'sanctum')
            ->withHeaders($this->headers())
            ->get("/api/documents/{$this->attachment->uuid}/form-content")
            ->assertForbidden();
    }

    public function test_a_learner_enrolled_in_a_different_course_is_refused(): void
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

        Enrollment::query()->delete();

        Enrollment::create([
            'course_id' => $otherCourse->id,
            'user_id' => $this->learner->id,
            'environment_id' => $this->environment->id,
            'status' => Enrollment::STATUS_ENROLLED,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($this->learner, 'sanctum')
            ->withHeaders($this->headers())
            ->get("/api/documents/{$this->attachment->uuid}/form-content")
            ->assertForbidden();
    }

    public function test_a_learner_from_another_academy_gets_not_found(): void
    {
        $otherOwner = User::factory()->create(['role' => 'individual_teacher']);
        $otherEnvironment = Environment::factory()->create([
            'owner_id' => $otherOwner->id,
            'primary_domain' => 'other-assignment-forms.test',
        ]);

        $otherTemplate = Template::create([
            'title' => 'Other course',
            'created_by' => $otherOwner->id,
            'environment_id' => $otherEnvironment->id,
        ]);

        $otherCourse = Course::create([
            'title' => 'Other course',
            'template_id' => $otherTemplate->id,
            'created_by' => $otherOwner->id,
            'environment_id' => $otherEnvironment->id,
            'status' => 'published',
        ]);

        $outsider = User::factory()->create(['role' => 'learner']);

        EnvironmentUser::create([
            'environment_id' => $otherEnvironment->id,
            'user_id' => $outsider->id,
            'role' => 'learner',
            'is_account_setup' => true,
        ]);

        Enrollment::create([
            'course_id' => $otherCourse->id,
            'user_id' => $outsider->id,
            'environment_id' => $otherEnvironment->id,
            'status' => Enrollment::STATUS_ENROLLED,
            'enrolled_at' => now(),
        ]);

        $this->actingAs($outsider, 'sanctum')
            ->withHeaders($this->headers('other-assignment-forms.test'))
            ->get("/api/documents/{$this->attachment->uuid}/form-content")
            ->assertNotFound();
    }

    public function test_a_document_not_referenced_by_an_assignment_is_refused(): void
    {
        $orphan = DocumentationAttachment::create([
            'documentation_content_id' => null,
            'environment_id' => $this->environment->id,
            'storage' => 'database',
            'file_path' => 'db://orphan',
            'file_name' => 'orphan.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 10,
            'created_by' => $this->teacher->id,
        ]);

        DocumentBlob::create([
            'documentation_attachment_id' => $orphan->id,
            'payload' => base64_encode('%PDF-1.4 orphan'),
            'mime_type' => 'application/pdf',
        ]);

        $this->actingAs($this->learner, 'sanctum')
            ->withHeaders($this->headers())
            ->get("/api/documents/{$orphan->uuid}/form-content")
            ->assertForbidden();
    }

    public function test_content_store_rejects_a_document_uploaded_to_another_academy(): void
    {
        $otherEnvironment = Environment::factory()->create([
            'owner_id' => $this->teacher->id,
            'primary_domain' => 'foreign-assignment-forms.test',
        ]);

        $foreignAttachment = DocumentationAttachment::create([
            'documentation_content_id' => null,
            'environment_id' => $otherEnvironment->id,
            'storage' => 'database',
            'file_path' => 'db://foreign',
            'file_name' => 'foreign.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 100,
            'created_by' => $this->teacher->id,
        ]);

        // A second activity whose content has not been created yet.
        $activity = Activity::create([
            'title' => 'Second workbook',
            'type' => 'assignment',
            'block_id' => $this->makeBlock($this->privateTemplate)->id,
            'created_by' => $this->teacher->id,
        ]);

        $this->actingAs($this->teacher, 'sanctum')
            ->postJson("/api/activities/{$activity->id}/assignment", [
                'title' => 'Workbook',
                'description' => 'Fill it.',
                'instructions' => 'Complete every field.',
                'instruction_format' => 'plain',
                'max_points' => 100,
                'submission_type' => 'fillable_pdf',
                'fillable_pdf' => [
                    'document_uuid' => $foreignAttachment->uuid,
                    'file_name' => 'foreign.pdf',
                    'file_type' => 'application/pdf',
                    'file_size' => 100,
                    'page_sizes' => [['width' => 612, 'height' => 792]],
                    'fields' => [[
                        'name' => 'full_name',
                        'page' => 1,
                        'type' => 'text',
                        'rect' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.5, 'h' => 0.05],
                        'required' => true,
                    ]],
                ],
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['fillable_pdf.document_uuid']);
    }
}
