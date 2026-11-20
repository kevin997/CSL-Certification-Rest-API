<?php

namespace Tests\Unit\Certificates;

use App\Support\Certificates\TemplateFieldContract;
use PHPUnit\Framework\TestCase;

/**
 * Parsing the certificate service's field contract.
 *
 * The contract is untrusted input (a remote service response), so malformed
 * entries are dropped rather than turned into an exception mid-generation.
 */
class TemplateFieldContractTest extends TestCase
{
    /**
     * @return array<string, mixed>
     */
    private function body(): array
    {
        return [
            'template' => 'bootcamps.pdf',
            'page' => ['width' => 841.89, 'height' => 595.28, 'count' => 1],
            'qr_field' => ['name' => 'QRCode', 'rect' => [10, 10, 107.2, 104.3], 'box' => [97.2, 94.3]],
            'fields' => [
                ['name' => 'NOMS', 'type' => 'text', 'rect' => [1, 2, 3, 4]],
                ['name' => 'SPECIALITE', 'type' => 'text', 'rect' => [5, 6, 7, 8]],
                ['name' => 'QRCode', 'type' => 'text', 'rect' => [9, 10, 11, 12]],
            ],
        ];
    }

    public function test_it_parses_the_contract(): void
    {
        $contract = TemplateFieldContract::fromArray($this->body());

        $this->assertSame('bootcamps.pdf', $contract->template);
        $this->assertSame(['width' => 841.89, 'height' => 595.28, 'count' => 1], $contract->page);
        $this->assertTrue($contract->hasQrField());
        $this->assertSame('QRCode', $contract->qrFieldName());
    }

    public function test_the_qr_field_is_never_a_data_field(): void
    {
        /* The service reports it separately and rejects it if it arrives in
           fields; excluding it here is the API-side guarantee. */
        $contract = TemplateFieldContract::fromArray($this->body());

        $this->assertSame(['NOMS', 'SPECIALITE'], $contract->dataFieldNames());
        $this->assertSame(['NOMS', 'SPECIALITE'], array_column($contract->dataFields(), 'name'));
    }

    public function test_the_qr_field_name_is_matched_without_case_or_separators(): void
    {
        $body = $this->body();
        $body['qr_field']['name'] = 'Qr Code';

        $contract = TemplateFieldContract::fromArray($body);

        $this->assertSame(['NOMS', 'SPECIALITE'], $contract->dataFieldNames());
    }

    public function test_a_contract_without_a_qr_field_reports_none(): void
    {
        $body = $this->body();
        $body['qr_field'] = null;

        $contract = TemplateFieldContract::fromArray($body);

        $this->assertFalse($contract->hasQrField());
        $this->assertNull($contract->qrFieldName());
        $this->assertSame(['NOMS', 'SPECIALITE', 'QRCode'], $contract->dataFieldNames());
    }

    public function test_malformed_entries_are_dropped_instead_of_throwing(): void
    {
        $contract = TemplateFieldContract::fromArray([
            'template' => 'x.pdf',
            'page' => [],
            'qr_field' => ['rect' => [1, 2, 3, 4]],
            'fields' => [
                ['name' => 'NOMS'],
                ['type' => 'text'],
                'not-an-array',
                null,
            ],
        ]);

        $this->assertSame(['NOMS'], $contract->dataFieldNames());
        $this->assertFalse($contract->hasQrField());
        $this->assertSame(['width' => null, 'height' => null, 'count' => null], $contract->page);
    }

    public function test_the_fallback_template_name_is_used_when_the_service_omits_it(): void
    {
        $body = $this->body();
        unset($body['template']);

        $this->assertSame('fallback.pdf', TemplateFieldContract::fromArray($body, 'fallback.pdf')->template);
    }
}
