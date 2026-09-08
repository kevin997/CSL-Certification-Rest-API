<?php

namespace App\Http\Requests\Marketing;

use App\Models\SalesFormSubmission;
use App\Services\Marketing\AudienceSelection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PreviewSalesFormCampaignAudienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'selection' => ['required', 'array'],
            'selection.mode' => ['required', 'string', 'in:selected,filtered,all'],
            'selection.submission_ids' => ['nullable', 'array'],
            'selection.submission_ids.*' => ['integer', 'distinct'],
            'selection.filters' => ['nullable', 'array'],
            'selection.filters.status' => ['nullable', 'string', 'in:'.implode(',', [
                SalesFormSubmission::STATUS_PENDING,
                SalesFormSubmission::STATUS_COMPLETED,
                SalesFormSubmission::STATUS_CANCELLED,
            ])],
            'channels' => ['required', 'array', 'min:1'],
            'channels.*' => ['required', 'string', 'distinct', 'in:email,whatsapp'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $selection = $this->input('selection');
            if (! is_array($selection) || ! isset($selection['mode'])) {
                return;
            }

            $expectedKeys = match ($selection['mode']) {
                'selected' => ['mode', 'submission_ids'],
                'filtered' => ['mode', 'filters'],
                'all' => ['mode'],
                default => [],
            };
            $actualKeys = array_keys($selection);
            sort($expectedKeys);
            sort($actualKeys);

            if ($actualKeys !== $expectedKeys) {
                $validator->errors()->add('selection', 'The selection fields must exactly match its mode.');

                return;
            }

            if ($selection['mode'] === 'selected' && (! is_array($selection['submission_ids'] ?? null) || $selection['submission_ids'] === [])) {
                $validator->errors()->add('selection.submission_ids', 'Selected audiences require at least one submission ID.');
            }

            if ($selection['mode'] === 'filtered') {
                $filters = $selection['filters'] ?? null;
                if (! is_array($filters) || array_keys($filters) !== ['status']) {
                    $validator->errors()->add('selection.filters', 'Filtered audiences support only the submission status filter.');
                }
            }
        });
    }

    public function selection(): AudienceSelection
    {
        /** @var array{mode: string, submission_ids?: array<int, int>, filters?: array{status?: string}} $selection */
        $selection = $this->input('selection');

        return AudienceSelection::fromArray($selection);
    }
}
