<?php

namespace App\Http\Requests\Marketing;

use App\Enums\UserRole;
use App\Models\EnvironmentUser;
use App\Models\SalesForm;
use App\Models\SalesFormSubmission;
use App\Services\Marketing\AudienceSelection;
use App\Support\Tenancy\EnvironmentResolver;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PreviewSalesFormCampaignAudienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        $form = $this->route('form');
        $context = $this->attributes->get(EnvironmentResolver::REQUEST_ATTRIBUTE);
        $environment = $context?->environment;

        if (! $user || ! $form instanceof SalesForm || ! $environment) {
            return false;
        }

        if ((int) $form->environment_id !== (int) $environment->id) {
            return false;
        }

        $platformRoles = [
            UserRole::ADMIN->value,
            UserRole::SUPER_ADMIN->value,
            UserRole::SALES_AGENT->value,
        ];
        $role = $user->role instanceof UserRole ? $user->role->value : $user->role;

        if (is_string($role) && in_array($role, $platformRoles, true)) {
            return true;
        }

        if ((int) $environment->owner_id === (int) $user->id
            || (int) $form->created_by === (int) $user->id) {
            return true;
        }

        $membership = EnvironmentUser::query()
            ->where('environment_id', $environment->id)
            ->where('user_id', $user->id)
            ->first();

        if (! $membership) {
            return false;
        }

        $membershipRole = is_string($membership->role) ? strtolower($membership->role) : null;

        return $user->isTeacher()
            && in_array($membershipRole, ['admin', 'instructor', 'owner', 'teacher'], true);
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
            'selection.filters.status' => ['sometimes', 'string', 'in:'.implode(',', [
                SalesFormSubmission::STATUS_PENDING,
                SalesFormSubmission::STATUS_COMPLETED,
                SalesFormSubmission::STATUS_CANCELLED,
            ])],
            'selection.filters.name' => ['sometimes', 'string', 'min:1', 'max:255'],
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
                $supportedKeys = ['name', 'status'];
                $actualFilterKeys = is_array($filters) ? array_keys($filters) : [];
                sort($supportedKeys);
                sort($actualFilterKeys);

                if (! is_array($filters) || $actualFilterKeys === [] || array_diff($actualFilterKeys, $supportedKeys) !== []) {
                    $validator->errors()->add('selection.filters', 'Filtered audiences support only name and status filters.');
                }

                foreach (is_array($filters) ? $filters : [] as $value) {
                    if ($value === null) {
                        $validator->errors()->add('selection.filters', 'Filtered audience filters cannot be null.');
                        break;
                    }
                }
            }
        });
    }

    public function selection(): AudienceSelection
    {
        /** @var array{mode: string, submission_ids?: array<int, int>, filters?: array{name?: string, status?: string}} $selection */
        $selection = $this->input('selection');

        return AudienceSelection::fromArray($selection);
    }
}
