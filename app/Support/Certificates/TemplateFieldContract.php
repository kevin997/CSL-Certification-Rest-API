<?php

namespace App\Support\Certificates;

/**
 * One template's own declaration of what it can be filled with, as reported by
 * GET /api/v2/templates/{name}/fields on the certificate service.
 *
 * This is the only authority on a template's field set: v2 rejects unknown
 * field names outright, and the QR field is not a text field -- it is stamped
 * separately from the `qr` payload, so it must never appear in `fields`.
 */
final class TemplateFieldContract
{
    /**
     * @param  list<array{name: string, type: string|null, rect: list<mixed>|null}>  $fields
     * @param  array{name: string, rect: list<mixed>|null, box: list<mixed>|null}|null  $qrField
     * @param  array{width: float|int|null, height: float|int|null, count: int|null}  $page
     */
    public function __construct(
        public readonly ?string $template,
        public readonly array $fields,
        public readonly ?array $qrField,
        public readonly array $page,
    ) {}

    /**
     * Parse the service response defensively: a malformed entry is dropped
     * rather than crashing a generation.
     *
     * @param  array<string, mixed>  $body
     */
    public static function fromArray(array $body, ?string $fallbackTemplate = null): self
    {
        $fields = [];

        foreach ((array) ($body['fields'] ?? []) as $field) {
            if (! is_array($field) || ! is_string($field['name'] ?? null) || $field['name'] === '') {
                continue;
            }

            $fields[] = [
                'name' => $field['name'],
                'type' => is_string($field['type'] ?? null) ? $field['type'] : null,
                'rect' => isset($field['rect']) && is_array($field['rect']) ? array_values($field['rect']) : null,
            ];
        }

        $qrField = null;
        $rawQrField = $body['qr_field'] ?? null;

        if (is_array($rawQrField) && is_string($rawQrField['name'] ?? null) && $rawQrField['name'] !== '') {
            $qrField = [
                'name' => $rawQrField['name'],
                'rect' => isset($rawQrField['rect']) && is_array($rawQrField['rect']) ? array_values($rawQrField['rect']) : null,
                'box' => isset($rawQrField['box']) && is_array($rawQrField['box']) ? array_values($rawQrField['box']) : null,
            ];
        }

        $page = [];

        foreach (['width', 'height', 'count'] as $key) {
            $page[$key] = $body['page'][$key] ?? null;
        }

        $template = $body['template'] ?? $fallbackTemplate;

        return new self(
            is_string($template) && $template !== '' ? $template : $fallbackTemplate,
            $fields,
            $qrField,
            $page,
        );
    }

    public function hasQrField(): bool
    {
        return $this->qrField !== null;
    }

    /**
     * The field the QR code is stamped into, or null.
     */
    public function qrFieldName(): ?string
    {
        return $this->qrField['name'] ?? null;
    }

    /**
     * The template's data fields, with the QR field removed even if the service
     * ever reports it under `fields`.
     *
     * @return list<array{name: string, type: string|null, rect: list<mixed>|null}>
     */
    public function dataFields(): array
    {
        $qrField = $this->qrFieldName();
        $qrFolded = $qrField === null ? null : TemplateFieldResolver::fold($qrField);

        return array_values(array_filter(
            $this->fields,
            static fn (array $field): bool => $qrFolded === null
                || TemplateFieldResolver::fold($field['name']) !== $qrFolded
        ));
    }

    /**
     * @return list<string>
     */
    public function dataFieldNames(): array
    {
        return array_map(static fn (array $field): string => $field['name'], $this->dataFields());
    }
}
