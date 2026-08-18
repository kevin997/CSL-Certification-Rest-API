<?php

namespace Tests\Feature\Api;

use App\Models\ThirdPartyService;
use App\Services\CertificateGenerationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Generation goes through the v2 endpoint, and the template decides the fields.
 *
 * v1 accepted a fixed five-key payload (fullName, courseTitle, certificateDate,
 * expiryDate, accessCode). A tenant whose template declares MATRICULE, NOMS,
 * SPECIALITE, SESSION got none of them filled, because those names were never
 * sent -- and v1 could not have accepted them anyway, since it validated
 * fullName and courseTitle as required.
 *
 * v2 takes an open `fields` map validated against the template's own
 * declaration, so what is sent is decided per template. It also REJECTS fields
 * the template does not declare, which is why the map is intersected here
 * rather than sent wholesale.
 */
class CertificateGenerationV2Test extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://certificates.example.test';

    protected function setUp(): void
    {
        parent::setUp();

        ThirdPartyService::create([
            'name' => 'Certificates',
            'service_type' => 'certificate_generation',
            'base_url' => self::BASE,
            'bearer_token' => 'token-123',
            'is_active' => true,
        ]);
    }

    /**
     * @param  list<string>  $declaredFields
     */
    private function fakeService(array $declaredFields, int $createStatus = 201): void
    {
        Http::fake([
            self::BASE.'/api/v2/templates/*/fields' => Http::response([
                'template' => 'tenant.pdf',
                'page' => ['width' => 841.89, 'height' => 595.28, 'count' => 1],
                'fields' => array_map(fn ($name) => ['name' => $name, 'type' => 'text'], $declaredFields),
            ]),
            self::BASE.'/api/v2/certificates' => Http::response([
                'identifier' => 'abc123',
                'download_url' => self::BASE.'/api/v2/certificates/abc123/download',
                'preview_url' => self::BASE.'/api/v2/certificates/abc123/preview',
            ], $createStatus),
        ]);
    }

    /**
     * Invoke the private v2 path directly: the public entry points need a
     * CertificateContent graph that is irrelevant to what is under test here.
     *
     * @param  array<string, mixed>  $values
     */
    private function issue(array $values, string $template = 'tenant.pdf'): ?array
    {
        $service = new CertificateGenerationService;
        $method = new \ReflectionMethod($service, 'issueViaV2');

        return $method->invoke($service, $template, $values, 'CODE1');
    }

    /**
     * @return array<string, mixed>
     */
    private function sentFields(): array
    {
        $sent = [];
        Http::recorded(function ($request) use (&$sent) {
            if (str_contains($request->url(), '/v2/certificates') && $request->method() === 'POST') {
                $sent = $request->data()['fields'] ?? [];
            }

            return true;
        });

        return $sent;
    }

    public function test_it_posts_to_the_v2_endpoint(): void
    {
        $this->fakeService(['fullName']);

        $this->issue(['fullName' => 'RALPH DARYL']);

        Http::assertSent(fn ($request) => $request->method() === 'POST'
            && $request->url() === self::BASE.'/api/v2/certificates');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/api/certificates/generate'));
    }

    public function test_it_sends_the_tenant_fields_the_template_declares(): void
    {
        /* The bootcamps template: none of these names existed in the v1 payload. */
        $this->fakeService(['MATRICULE', 'NOMS', 'SPECIALITE', 'DDD', 'VALIDITE', 'EXPIRE', 'SESSION']);

        $this->issue([
            'MATRICULE' => '05CBRANDSS0126',
            'NOMS' => 'RALPH DARYL',
            'SPECIALITE' => 'MARKETING',
            'SESSION' => 'Session 7 Avril',
            'fullName' => 'ignored, not declared',
        ]);

        $sent = $this->sentFields();

        $this->assertSame('05CBRANDSS0126', $sent['MATRICULE']);
        $this->assertSame('RALPH DARYL', $sent['NOMS']);
        $this->assertSame('Session 7 Avril', $sent['SESSION']);
    }

    public function test_it_drops_fields_the_template_does_not_declare(): void
    {
        /* v2 rejects the whole request on an unknown field, so the intersection
           is what keeps a shared code path working across differing templates. */
        $this->fakeService(['fullName']);

        $this->issue(['fullName' => 'RALPH', 'MATRICULE' => '05CB', 'courseTitle' => 'Marketing']);

        $this->assertSame(['fullName' => 'RALPH'], $this->sentFields());
    }

    public function test_field_names_are_matched_case_insensitively(): void
    {
        /* Production templates disagree: QrCode vs QRCode, fullName vs FULLNAME. */
        $this->fakeService(['FULLNAME']);

        $this->issue(['fullName' => 'RALPH']);

        $this->assertSame(['FULLNAME' => 'RALPH'], $this->sentFields());
    }

    public function test_unfillable_values_are_dropped(): void
    {
        /* null and false both cast to '' and would blank a field while the
           service reported success; arrays are refused outright. */
        $this->fakeService(['fullName', 'expiryDate', 'courseTitle']);

        $this->issue(['fullName' => 'RALPH', 'expiryDate' => null, 'courseTitle' => ['a']]);

        $this->assertSame(['fullName' => 'RALPH'], $this->sentFields());
    }

    public function test_no_qr_is_requested_when_no_verification_url_is_configured(): void
    {
        /* The service allow-lists QR hosts; sending an unconfigured one fails
           the generation instead of just omitting the code. */
        config(['services.certificate_generation.qr_verify_base_url' => null]);
        $this->fakeService(['fullName']);

        $this->issue(['fullName' => 'RALPH', 'accessCode' => 'CODE1']);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/v2/certificates') || $request->method() !== 'POST') {
                return true;
            }

            return ! array_key_exists('qr', $request->data());
        });
    }

    public function test_a_qr_is_requested_when_a_verification_url_is_configured(): void
    {
        config(['services.certificate_generation.qr_verify_base_url' => 'https://learning.example.test/verify']);
        $this->fakeService(['fullName']);

        $this->issue(['fullName' => 'RALPH', 'accessCode' => 'CODE1']);

        Http::assertSent(function ($request) {
            if (! str_contains($request->url(), '/v2/certificates') || $request->method() !== 'POST') {
                return true;
            }

            return ($request->data()['qr']['url'] ?? null) === 'https://learning.example.test/verify/CODE1';
        });
    }

    public function test_the_response_keeps_the_shape_callers_expect(): void
    {
        /* The transport change must stop at this class: callers read
           data.certificate_url and data.preview_url. */
        $this->fakeService(['fullName']);

        $result = $this->issue(['fullName' => 'RALPH']);

        $this->assertSame(self::BASE.'/api/v2/certificates/abc123/download', $result['data']['certificate_url']);
        $this->assertSame(self::BASE.'/api/v2/certificates/abc123/preview', $result['data']['preview_url']);
        $this->assertSame('abc123', $result['data']['identifier']);
    }

    public function test_a_rejected_generation_returns_null(): void
    {
        $this->fakeService(['fullName'], 422);

        $this->assertNull($this->issue(['fullName' => 'RALPH']));
    }

    public function test_it_does_not_generate_when_the_template_cannot_be_read(): void
    {
        /* Without the declaration there is no safe field set to send, and
           guessing would risk the unknown-field rejection on every call. */
        Http::fake([
            self::BASE.'/api/v2/templates/*/fields' => Http::response(['error' => 'not found'], 404),
            self::BASE.'/api/v2/certificates' => Http::response([], 201),
        ]);

        $this->assertNull($this->issue(['fullName' => 'RALPH']));
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/v2/certificates')
            && $request->method() === 'POST');
    }
}
