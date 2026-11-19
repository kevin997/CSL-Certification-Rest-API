<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\Block;
use App\Models\Course;
use App\Models\DocumentationAttachment;
use App\Models\DocumentationContent;
use App\Models\Enrollment;
use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\MediaAsset;
use App\Models\Template;
use App\Models\User;
use App\Models\VideoContent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class DocumentProtectionTest extends TestCase
{
    use RefreshDatabase;

    private Environment $environment;

    private User $teacher;

    private User $learner;

    private DocumentationContent $documentation;

    private DocumentationAttachment $attachment;

    private Block $block;

    private string $domain = 'academy-documents.test';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'devices.enforced' => true,
            'documents.allowed_source_hosts' => ['res.cloudinary.com'],
        ]);

        Http::fake([
            'res.cloudinary.com/*' => Http::response('%PDF-1.4 protected document', 200, [
                'Content-Type' => 'application/pdf',
            ]),
        ]);

        $this->teacher = User::factory()->create([
            'role' => UserRole::INDIVIDUAL_TEACHER->value,
        ]);

        $this->environment = Environment::factory()->create([
            'owner_id' => $this->teacher->id,
            'primary_domain' => $this->domain,
            'domain_verified_at' => now(),
        ]);

        $template = Template::create([
            'title' => 'Media Buying',
            'created_by' => $this->teacher->id,
            'environment_id' => $this->environment->id,
        ]);

        $course = Course::create([
            'title' => 'Media Buying Bootcamp',
            'environment_id' => $this->environment->id,
            'template_id' => $template->id,
            'created_by' => $this->teacher->id,
        ]);

        $this->block = Block::create([
            'title' => 'Week 1',
            'template_id' => $template->id,
            'created_by' => $this->teacher->id,
        ]);

        $activity = Activity::create([
            'title' => 'Course handbook',
            'type' => 'documentation',
            'block_id' => $this->block->id,
            'created_by' => $this->teacher->id,
        ]);

        $this->documentation = DocumentationContent::create([
            'activity_id' => $activity->id,
            'title' => 'Course handbook',
            'content' => 'Read this before the first lesson.',
            'format' => 'markdown',
            'created_by' => $this->teacher->id,
        ]);

        $this->attachment = DocumentationAttachment::create([
            'documentation_content_id' => $this->documentation->id,
            'environment_id' => $this->environment->id,
            'source_url' => 'https://res.cloudinary.com/demo/raw/upload/handbook.pdf',
            'file_path' => 'https://res.cloudinary.com/demo/raw/upload/handbook.pdf',
            'file_name' => 'handbook.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 1024,
            'created_by' => $this->teacher->id,
        ]);

        $this->learner = User::factory()->create([
            'role' => UserRole::LEARNER->value,
        ]);

        EnvironmentUser::create([
            'environment_id' => $this->environment->id,
            'user_id' => $this->learner->id,
            'role' => UserRole::LEARNER->value,
            'is_account_setup' => true,
        ]);

        Enrollment::create([
            'course_id' => $course->id,
            'user_id' => $this->learner->id,
            'environment_id' => $this->environment->id,
            'status' => Enrollment::STATUS_ENROLLED,
            'enrolled_at' => now(),
        ]);
    }

    public function test_an_enrolled_learner_opens_the_document_through_the_protected_endpoint(): void
    {
        $response = $this->asLearner()->get("/api/documents/{$this->attachment->uuid}/content");

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $this->assertSame('%PDF-1.4 protected document', $response->getContent());

        $this->assertDatabaseHas('document_access_logs', [
            'documentation_attachment_id' => $this->attachment->id,
            'environment_id' => $this->environment->id,
            'user_id' => $this->learner->id,
        ]);
    }

    public function test_a_learner_without_an_enrollment_is_refused(): void
    {
        Enrollment::query()->delete();

        $this->asLearner()
            ->get("/api/documents/{$this->attachment->uuid}/content")
            ->assertForbidden();
    }

    public function test_a_learner_from_another_academy_cannot_open_the_document(): void
    {
        $other = Environment::factory()->create([
            'owner_id' => $this->teacher->id,
            'primary_domain' => 'other-documents.test',
            'domain_verified_at' => now(),
        ]);

        $this->asLearner('other-documents.test')
            ->get("/api/documents/{$this->attachment->uuid}/content")
            ->assertNotFound();
    }

    public function test_the_documentation_payload_never_contains_the_storage_url(): void
    {
        $response = $this->asLearner()->getJson("/api/activities/{$this->documentation->activity_id}/documentation");

        $response->assertOk();
        $response->assertJsonPath('data.attachments.0.uuid', $this->attachment->uuid);
        $response->assertJsonPath('data.attachments.0.name', 'handbook.pdf');
        $this->assertStringNotContainsString('res.cloudinary.com', $response->getContent());
        $this->assertStringNotContainsString('file_url', $response->getContent());
    }

    public function test_an_instructor_can_read_their_own_document_and_an_unrelated_learner_cannot(): void
    {
        $this->actingAs($this->teacher, 'sanctum')
            ->withHeaders($this->headers())
            ->get("/api/documents/{$this->attachment->uuid}/content")
            ->assertOk();

        $stranger = User::factory()->create(['role' => UserRole::LEARNER->value]);

        $this->actingAs($stranger, 'sanctum')
            ->withHeaders($this->headers())
            ->get("/api/documents/{$this->attachment->uuid}/content")
            ->assertForbidden();
    }

    public function test_video_playback_requires_an_enrollment(): void
    {
        config([
            'services.bunny_stream.library_id' => '12345',
            'services.bunny_stream.api_key' => 'test-api-key',
            'services.bunny_stream.cdn_hostname' => 'video.example.test',
            'services.bunny_stream.token_auth_key' => 'test-token-key',
        ]);

        $asset = MediaAsset::create([
            'environment_id' => $this->environment->id,
            'owner_user_id' => $this->teacher->id,
            'provider' => 'bunny_stream',
            'provider_asset_id' => 'video-1',
            'title' => 'Week 1 lesson',
            'type' => 'video',
            'status' => 'ready',
            'meta' => ['upload_id' => 'video-1'],
        ]);

        $activity = Activity::create([
            'title' => 'Week 1 video',
            'type' => 'video',
            'block_id' => $this->block->id,
            'created_by' => $this->teacher->id,
        ]);

        VideoContent::create([
            'activity_id' => $activity->id,
            'title' => 'Week 1 lesson',
            'media_asset_id' => $asset->id,
            'video_type' => 'mp4',
            'created_by' => $this->teacher->id,
        ]);

        Enrollment::query()->update(['status' => Enrollment::STATUS_CANCELLED]);

        $this->asLearner()
            ->getJson("/api/media/playback/{$asset->id}")
            ->assertForbidden()
            ->assertJson(['code' => 'not_enrolled']);

        Enrollment::query()->update([
            'status' => Enrollment::STATUS_ENROLLED,
            'enrolled_at' => now(),
        ]);

        $this->asLearner()
            ->getJson("/api/media/playback/{$asset->id}")
            ->assertOk()
            ->assertJsonStructure(['stream_url', 'token', 'expires']);
    }

    private function asLearner(string $domain = ''): static
    {
        return $this->actingAs($this->learner, 'sanctum')
            ->withHeaders($this->headers($domain));
    }

    private function headers(string $domain = ''): array
    {
        return [
            'X-Frontend-Domain' => $domain !== '' ? $domain : $this->domain,
            'X-Device-Id' => Str::uuid(),
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0',
        ];
    }
}
