<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketingAutomation;
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
    public function index(Request $request)
    {
        $existing = MarketingAutomation::all()->keyBy('trigger');

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
    public function upsert(Request $request, string $trigger)
    {
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $automation = MarketingAutomation::updateOrCreate(
            ['trigger' => $trigger],
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
            'data' => $automation->fresh(),
        ]);
    }
}
