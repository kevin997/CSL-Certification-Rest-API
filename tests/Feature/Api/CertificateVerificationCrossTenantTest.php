<?php

namespace Tests\Feature\Api;

use App\Models\CertificateContent;
use App\Models\Environment;
use App\Models\IssuedCertificate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * A printed QR is scanned from the shared certification host.
 *
 * That request resolves the shared environment, while the certificate belongs
 * to the tenant it was issued by. Filtering the lookup by the request's
 * environment made every cross-tenant certificate read as "not found" even
 * though the code itself is the bearer of the claim.
 */
class CertificateVerificationCrossTenantTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_certificate_issued_by_another_tenant_verifies(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $sharedHost = Environment::factory()->create();
        $tenant = Environment::factory()->create([
            'name' => 'BootCamps',
            'primary_domain' => 'bootcamps.csl-brands.com',
        ]);

        // The verification request resolves the shared host, not the tenant
        // that issued the certificate.
        session(['current_environment_id' => $sharedHost->id]);

        $user = User::factory()->create();

        // The certificate content hangs off a course-template chain; build the
        // minimum of it so the foreign keys hold.
        $templateId = DB::table('templates')->insertGetId([
            'title' => 'Media Buying', 'created_by' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $blockId = DB::table('blocks')->insertGetId([
            'title' => 'Evaluation', 'template_id' => $templateId, 'created_by' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $activityId = DB::table('activities')->insertGetId([
            'title' => 'Certificat', 'type' => 'certificate', 'block_id' => $blockId,
            'created_by' => $user->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $content = CertificateContent::create([
            'activity_id' => $activityId,
            'title' => 'Certificat Media Buyer',
            'description' => 'Certificate for course completion',
            'created_by' => $user->id,
        ]);

        IssuedCertificate::create([
            'certificate_content_id' => $content->id,
            'user_id' => $user->id,
            'environment_id' => $tenant->id,
            'certificate_number' => 'CROSSTEN',
            'issued_date' => now(),
            'expiry_date' => now()->addYear(),
            'file_path' => 'https://gen-certificate.example.test/api/v2/certificates/abc/download',
            'status' => 'issued',
            'custom_fields' => [
                'recipient_name' => 'Ralph NDONGO',
                'course_title' => 'Formation Media Buying',
                'certificate_date' => now()->format('F j, Y'),
            ],
        ]);

        $this->postJson('/api/certificates/verify', ['accessCode' => 'CROSSTEN'])
            ->assertOk()
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.isValid', true)
            ->assertJsonPath('data.certificate.recipient_name', 'Ralph NDONGO')
            // The verifier page runs on a shared host and needs the issuing
            // tenant to load its branding.
            ->assertJsonPath('data.environment.id', $tenant->id)
            ->assertJsonPath('data.environment.name', 'BootCamps')
            ->assertJsonPath('data.environment.primary_domain', 'bootcamps.csl-brands.com');
    }

    public function test_an_unknown_code_is_still_not_found(): void
    {
        $this->postJson('/api/certificates/verify', ['accessCode' => 'NOSUCHCD'])
            ->assertNotFound();
    }
}
