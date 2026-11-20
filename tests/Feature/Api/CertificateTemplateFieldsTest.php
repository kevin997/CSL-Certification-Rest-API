<?php

namespace Tests\Feature\Api;

use App\Http\Controllers\Api\CertificateTemplateController;
use App\Models\CertificateTemplate;
use App\Models\Environment;
use App\Models\ThirdPartyService;
use App\Services\CertificateGenerationService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * The dashboard-facing view of a template's field contract.
 *
 * The template PDF is the only authority on which fields exist, so the
 * endpoint proxies the certificate service rather than guessing from a schema,
 * and reports each field's default source so the UI can offer an override.
 */
class CertificateTemplateFieldsTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://certificates.example.test';

    private Environment $mine;

    private Environment $theirs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mine = Environment::factory()->create();
        $this->theirs = Environment::factory()->create();

        ThirdPartyService::create([
            'name' => 'Certificates',
            'service_type' => 'certificate_generation',
            'base_url' => self::BASE,
            'bearer_token' => 'token-123',
            'is_active' => true,
        ]);

        session(['current_environment_id' => $this->mine->id]);
    }

    private function controller(): CertificateTemplateController
    {
        return new CertificateTemplateController(app(CertificateGenerationService::class));
    }

    private function template(?Environment $environment, array $attributes = []): CertificateTemplate
    {
        return CertificateTemplate::create(array_merge([
            'environment_id' => $environment?->id,
            'name' => 'Bootcamps',
            'filename' => 'bootcamps.pdf',
            'file_path' => 'templates/bootcamps.pdf',
            'template_type' => 'completion',
            'is_default' => false,
        ], $attributes));
    }

    private function fakeContract(?string $qrField = 'QRCode'): void
    {
        $contract = [
            'template' => 'bootcamps.pdf',
            'page' => ['width' => 841.89, 'height' => 595.28, 'count' => 1],
            'fields' => [
                ['name' => 'NOMS', 'type' => 'text', 'rect' => [1, 2, 3, 4]],
                ['name' => 'SPECIALITE', 'type' => 'text', 'rect' => [5, 6, 7, 8]],
                ['name' => 'MATRICULE', 'type' => 'text', 'rect' => [9, 10, 11, 12]],
                ['name' => 'CUSTOM', 'type' => 'text', 'rect' => [13, 14, 15, 16]],
            ],
        ];

        if ($qrField !== null) {
            $contract['qr_field'] = ['name' => $qrField, 'rect' => [0, 0, 97.2, 94.3], 'box' => [97.2, 94.3]];
        }

        Http::fake([self::BASE.'/api/v2/templates/*/fields' => Http::response($contract)]);
    }

    public function test_fields_returns_the_contract_with_the_default_sources(): void
    {
        $template = $this->template($this->mine);
        $this->fakeContract();

        $response = $this->controller()->fields($template->id);
        $payload = $response->getData(true);

        $this->assertSame('success', $payload['status']);
        $this->assertSame($template->id, $payload['data']['template']['id']);
        $this->assertSame('Bootcamps', $payload['data']['template']['name']);
        $this->assertSame('templates/bootcamps.pdf', $payload['data']['template']['file_path']);
        $this->assertSame(['width' => 841.89, 'height' => 595.28, 'count' => 1], $payload['data']['page']);
        $this->assertSame('QRCode', $payload['data']['qr_field']['name']);
        $this->assertSame(
            ['NOMS', 'SPECIALITE', 'MATRICULE', 'CUSTOM'],
            array_column($payload['data']['fields'], 'name')
        );
        $this->assertSame(
            ['fullName', 'courseTitle', 'matricule', null],
            array_column($payload['data']['fields'], 'maps_to')
        );

        // The service knows the template by its bare filename including .pdf,
        // while the column stores "templates/<name>.pdf".
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/v2/templates/bootcamps.pdf/fields'));
    }

    public function test_fields_reports_no_qr_field_when_the_template_has_none(): void
    {
        $template = $this->template($this->mine);
        $this->fakeContract(null);

        $payload = $this->controller()->fields($template->id)->getData(true);

        $this->assertNull($payload['data']['qr_field']);
    }

    public function test_fields_is_tenant_scoped(): void
    {
        $theirs = $this->template($this->theirs);
        Http::fake();

        try {
            $this->controller()->fields($theirs->id);

            $this->fail('Another environment\'s template was readable by id.');
        } catch (ModelNotFoundException) {
            // Expected.
        }

        Http::assertNothingSent();
    }

    public function test_fields_reports_a_service_failure_as_an_error(): void
    {
        $template = $this->template($this->mine);
        Http::fake([self::BASE.'/api/v2/templates/*/fields' => Http::response(['error' => 'unreadable'], 500)]);

        $response = $this->controller()->fields($template->id);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame('error', $response->getData(true)['status']);
    }

    public function test_store_persists_and_returns_the_uploaded_templates_contract(): void
    {
        Http::fake([
            self::BASE.'/api/templates/upload' => Http::response([
                'data' => [
                    'filename' => 'bootcamps.pdf',
                    'path' => 'templates/bootcamps.pdf',
                    'id' => 'remote-1',
                ],
            ]),
            self::BASE.'/api/v2/templates/bootcamps.pdf/fields' => Http::response([
                'template' => 'bootcamps.pdf',
                'page' => ['width' => 841.89, 'height' => 595.28, 'count' => 1],
                'qr_field' => ['name' => 'QRCode', 'rect' => [0, 0, 97.2, 94.3], 'box' => [97.2, 94.3]],
                'fields' => [
                    ['name' => 'NOMS', 'type' => 'text', 'rect' => [1, 2, 3, 4]],
                ],
            ]),
        ]);

        $request = Request::create('/api/certificate-templates', 'POST', [
            'name' => 'Bootcamps',
            'template_type' => 'completion',
        ], [], [
            // Non-empty content: the upload is multipart-attached from the
            // real file, and an empty fake file produces no part at all.
            'template' => UploadedFile::fake()->createWithContent('bootcamps.pdf', "%PDF-1.4\n%fake\n"),
        ]);

        $response = $this->controller()->store($request);
        $payload = $response->getData(true);

        $this->assertSame('success', $payload['status'], json_encode($payload));
        $this->assertSame('templates/bootcamps.pdf', $payload['data']['file_path']);
        $this->assertSame('NOMS', $payload['data']['metadata']['fields'][0]['name']);
        $this->assertSame('fullName', $payload['data']['metadata']['fields'][0]['maps_to']);
        $this->assertSame('QRCode', $payload['data']['metadata']['qr_field']['name']);

        $stored = CertificateTemplate::query()->findOrFail($payload['data']['id']);
        $this->assertSame('NOMS', $stored->metadata['fields'][0]['name']);
        $this->assertSame($this->mine->id, $stored->environment_id);
    }
}
