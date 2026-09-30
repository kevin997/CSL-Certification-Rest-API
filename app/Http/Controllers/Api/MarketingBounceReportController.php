<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailSuppression;
use App\Support\Marketing\MarketingRecipientReference;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Where the marketing service reports a hard bounce its own email provider saw.
 *
 * The address joins the same block list Hostinger's bounces feed, so a mailbox
 * that does not exist stops receiving digests and campaigns alike, whichever
 * system discovered it. The service identifies the recipient by its opaque
 * reference, never by address.
 */
class MarketingBounceReportController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'environment_id' => ['required', 'integer', 'min:1'],
            'recipient_ref' => ['required', 'string', 'max:4096'],
            'provider' => ['required', 'string', 'max:64'],
            'status_code' => ['nullable', 'string', 'max:16'],
            'diagnostic' => ['nullable', 'string', 'max:1000'],
        ]);

        $submission = MarketingRecipientReference::submission($validated['recipient_ref'], (int) $validated['environment_id']);

        if (! $submission || blank($submission->email)) {
            return $this->json(['message' => 'The recipient reference is invalid.'], 422);
        }

        EmailSuppression::recordHardBounce(
            (string) $submission->email,
            $validated['status_code'] ?? null,
            $validated['diagnostic'] ?? null,
            'marketing:'.$validated['provider'],
            now(),
        );

        return $this->json(['suppressed' => true]);
    }

    /**
     * Plain JSON, not a JsonResponse, for the same reason as the consent
     * check: the tenant middleware decorates JsonResponse instances, and this
     * private route must expose no tenant metadata.
     *
     * @param  array<string, bool|string>  $body
     */
    private function json(array $body, int $status = 200): Response
    {
        return response(json_encode($body, JSON_THROW_ON_ERROR), $status, ['Content-Type' => 'application/json']);
    }
}
