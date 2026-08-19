<?php

namespace Tests\Feature\Api;

use App\Models\CertificateContent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Certificate content may exist before a template is chosen.
 *
 * CertificateContentController::store() fills template_path only when the
 * request carries a certificate_template_id. The column was created NOT NULL
 * with no default, so every certificate saved without a template died on the
 * insert with SQLSTATE[HY000] 1364 and surfaced to the instructor as a 500.
 */
class CertificateContentWithoutTemplateTest extends TestCase
{
    use RefreshDatabase;

    public function test_certificate_content_saves_without_a_template(): void
    {
        $user = User::factory()->create();

        // certificate_contents.activity_id is a real foreign key, and an
        // activity only exists inside a block inside a template.
        $templateId = DB::table('templates')->insertGetId([
            'title' => 'Course', 'status' => 'published', 'created_by' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $blockId = DB::table('blocks')->insertGetId([
            'title' => 'Block', 'order' => 0, 'template_id' => $templateId,
            'status' => 'active', 'created_by' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $activityId = DB::table('activities')->insertGetId([
            'title' => 'Certificate', 'type' => 'certificate', 'order' => 0,
            'block_id' => $blockId, 'status' => 'active', 'created_by' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        // Exactly the attribute set store() builds when the instructor has not
        // picked a template: no template_path key at all.
        $content = CertificateContent::create([
            'activity_id' => $activityId,
            'title' => 'Certificate of Completion',
            'description' => 'Certificate for course completion',
            'expiry_period' => 3,
            'expiry_period_unit' => 'years',
            'completion_criteria' => json_encode(['type' => 'all_activities']),
            'created_by' => $user->id,
        ]);

        $this->assertTrue($content->exists);
        $this->assertNull($content->fresh()->template_path);
    }
}
