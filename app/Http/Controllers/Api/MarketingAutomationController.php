<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\MarketingAutomation;
use App\Scopes\EnvironmentScope;
use App\Support\Tenancy\EnvironmentContext;
use App\Support\Tenancy\EnvironmentResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Per-environment Email/WhatsApp marketing automation settings
 * (configured at /settings/integrations/automations in the frontend).
 */
class MarketingAutomationController extends Controller
{
    /**
     * All four triggers, merged with defaults for triggers without a row.
     */
    public function index(Request $request): JsonResponse
    {
        $environment = $this->authorizedEnvironment($request);
        $existing = MarketingAutomation::withoutGlobalScope(EnvironmentScope::class)
            ->where('environment_id', $environment->id)
            ->get()
            ->keyBy('trigger');

        $automations = collect(MarketingAutomation::TRIGGERS)->map(function (string $trigger) use ($existing) {
            $row = $existing->get($trigger);

            return [
                'trigger' => $trigger,
                'enabled' => (bool) ($row->enabled ?? false),
                'channels' => $row->channels ?? [],
                'recipient' => $row->recipient ?? MarketingAutomation::DEFAULT_RECIPIENTS[$trigger],
                'email_subject' => $row->email_subject ?? '',
                'email_body' => $row->email_body ?? '',
                'whatsapp_template' => $row->whatsapp_template ?? '',
                'config' => $row->config ?? ($trigger === MarketingAutomation::TRIGGER_ORDER_ABANDONED
                    ? ['abandoned_delay_hours' => 24]
                    : []),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $automations,
        ]);
    }

    /**
     * Upsert the automation config for one trigger.
     */
    public function upsert(Request $request, string $trigger): JsonResponse
    {
        $environment = $this->authorizedEnvironment($request);

        if (! in_array($trigger, MarketingAutomation::TRIGGERS, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Unknown automation trigger.',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'enabled' => 'required|boolean',
            'channels' => 'present|array',
            'channels.*' => 'string|in:email,whatsapp',
            'recipient' => 'required|string|in:instructor,customer',
            'email_subject' => 'nullable|string|max:255',
            'email_body' => 'nullable|string|max:10000',
            'whatsapp_template' => 'nullable|string|max:4000',
            'config' => 'nullable|array',
            'config.abandoned_delay_hours' => 'nullable|integer|min:1|max:168',
            'environment_id' => 'prohibited',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $automation = MarketingAutomation::withoutGlobalScope(EnvironmentScope::class)->updateOrCreate(
            ['environment_id' => $environment->id, 'trigger' => $trigger],
            [
                'enabled' => $data['enabled'],
                'channels' => array_values(array_unique($data['channels'])),
                'recipient' => $data['recipient'],
                'email_subject' => $data['email_subject'] ?? null,
                'email_body' => $data['email_body'] ?? null,
                'whatsapp_template' => $data['whatsapp_template'] ?? null,
                'config' => $data['config'] ?? null,
            ],
        );

        return response()->json([
            'success' => true,
            'message' => 'Automation saved.',
            'data' => $automation,
        ]);
    }

    private function authorizedEnvironment(Request $request): Environment
    {
        $context = $request->attributes->get(EnvironmentResolver::REQUEST_ATTRIBUTE);
        $environment = $context instanceof EnvironmentContext && $context->resolved()
            ? $context->environment
            : null;

        abort_unless($environment && $request->user()?->isStaffIn($environment->id), 403, 'Unauthorized');

        return $environment;
    }
}
