<?php

namespace App\Services\Workflows;

use App\Models\Course;
use App\Models\Environment;
use App\Models\EnvironmentLicence;
use App\Models\Product;
use App\Models\SalesForm;
use App\Scopes\EnvironmentScope;
use App\Services\Licensing\EntitlementService;
use App\Services\WachapNotificationService;
use App\Services\WhatsApp\AcademyWhatsApp;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class WorkflowDefinition
{
    public const TRIGGERS = [
        'form_submitted' => SalesForm::class,
        'order_placed' => Product::class,
        'payment_confirmed' => Product::class,
        'certificate_issued' => Course::class,
    ];

    public const CHANNELS = ['in_app', 'email', 'whatsapp'];

    public static function canRun(Environment $environment): bool
    {
        if (! $environment->is_active || $environment->is_demo) {
            return false;
        }
        if (! config('licensing.enforcement_enabled')) {
            return true;
        }
        $entitlement = EntitlementService::for($environment);

        return ($entitlement->features()['marketing_automations'] ?? false) === true
            && ! in_array($entitlement->status(), [
                EnvironmentLicence::STATUS_GRACE,
                EnvironmentLicence::STATUS_PAST_DUE,
            ], true);
    }

    public function resources(int $environmentId): array
    {
        $result = [];
        foreach (self::TRIGGERS as $trigger => $model) {
            if ($trigger === 'payment_confirmed') {
                $result[$trigger] = $result['order_placed'];

                continue;
            }
            $name = $model === Product::class ? 'name' : 'title';
            $result[$trigger] = $model::withoutGlobalScope(EnvironmentScope::class)->where('environment_id', $environmentId)
                ->orderBy($name)->orderBy('id')->get(['id', $name])
                ->map(fn ($item): array => ['id' => (int) $item->id, 'name' => (string) $item->{$name}])->all();
        }

        return $result;
    }

    public function normalize(array $input, int $environmentId): array
    {
        $data = Validator::make(['definition' => $input], [
            'definition' => ['required', 'array:name,trigger,filter_id,actions,draft_version,sample_matches'],
            'definition.name' => ['required', 'string', 'max:120'],
            'definition.trigger' => ['required', Rule::in(array_keys(self::TRIGGERS))],
            'definition.filter_id' => ['nullable', 'integer', 'min:1'],
            'definition.actions' => ['present', 'array', 'max:3', 'list'],
            'definition.actions.*' => ['required', 'array:channel,recipient,title,message'],
            'definition.actions.*.channel' => ['required', 'distinct', Rule::in(self::CHANNELS)],
            'definition.actions.*.recipient' => ['required', Rule::in(['owner', 'subject'])],
            'definition.actions.*.title' => ['nullable', 'string', 'max:120', 'not_regex:/[\r\n]/'],
            'definition.actions.*.message' => ['nullable', 'string', 'max:2000'],
            'definition.draft_version' => ['sometimes', 'integer', 'min:1'],
            'definition.sample_matches' => ['sometimes', 'boolean'],
        ])->validate()['definition'];

        $filter = isset($data['filter_id']) ? (int) $data['filter_id'] : null;
        if ($filter !== null && ! $this->filterExists($data['trigger'], $filter, $environmentId)) {
            throw ValidationException::withMessages(['filter_id' => 'The selected resource is not available in this academy.']);
        }

        return [
            'name' => $data['name'],
            'trigger' => $data['trigger'],
            'filter_id' => $filter,
            'actions' => array_map(fn ($action) => [
                'channel' => $action['channel'], 'recipient' => $action['recipient'],
                'title' => (string) ($action['title'] ?? ''), 'message' => (string) ($action['message'] ?? ''),
            ], $data['actions']),
        ];
    }

    public function filterExists(string $trigger, int $id, int $environmentId): bool
    {
        $model = self::TRIGGERS[$trigger];

        return $model::withoutGlobalScope(EnvironmentScope::class)->where('environment_id', $environmentId)->whereKey($id)->exists();
    }

    public function requirePublishable(array $definition): void
    {
        if (! $definition['actions']) {
            throw ValidationException::withMessages(['actions' => 'Add at least one action before publishing.']);
        }
        foreach ($definition['actions'] as $action) {
            if (blank($action['message']) || ($action['channel'] !== 'whatsapp' && blank($action['title']))) {
                throw ValidationException::withMessages(['actions' => 'Complete the title and message of each action.']);
            }
        }
    }

    public static function channelAvailable(string $channel, AcademyWhatsApp $whatsapp): bool
    {
        return match ($channel) {
            'in_app' => true,
            'email' => (bool) config('marketing.email_enabled'),
            'whatsapp' => $whatsapp->available() || WachapNotificationService::wahaConfigured(),
            default => false,
        };
    }

    public static function render(string $template, array $context): string
    {
        return strtr($template, [
            '{{academy}}' => $context['academy'], '{{reference}}' => $context['reference'],
            '{{event}}' => $context['event'], '{{name}}' => $context['name'],
        ]);
    }
}
