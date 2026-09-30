<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EmailSuppression;
use App\Models\MarketingConsent;
use App\Support\Marketing\MarketingRecipientReference;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class MarketingConsentCheckController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'environment_id' => ['required', 'integer', 'min:1'],
            'recipient_ref' => ['required', 'string', 'max:4096'],
            'channel' => ['required', 'string', 'in:email,whatsapp'],
        ]);

        $submission = MarketingRecipientReference::submission(
            $validated['recipient_ref'],
            (int) $validated['environment_id'],
        );

        if (! $submission) {
            return $this->json(['message' => 'The recipient reference is invalid.'], 422);
        }

        // The marketing service asks this before every send, so a hard-bounced
        // address is refused here too — whichever provider it would go out on.
        if ($validated['channel'] === 'email' && EmailSuppression::isSuppressed((string) $submission->email)) {
            return $this->json(['granted' => false]);
        }

        $state = MarketingConsent::withoutGlobalScopes()
            ->where('environment_id', $submission->environment_id)
            ->where('sales_form_submission_id', $submission->id)
            ->where('channel', $validated['channel'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        return $this->json(['granted' => $state?->status === MarketingConsent::STATUS_GRANTED]);
    }

    /**
     * This private endpoint must expose no tenant or branding metadata. The
     * global tenant middleware augments JsonResponse instances, so intentionally
     * return a JSON HTTP response rather than a JsonResponse.
     *
     * @param  array<string, bool|string>  $body
     */
    private function json(array $body, int $status = 200): Response
    {
        return response(json_encode($body, JSON_THROW_ON_ERROR), $status, [
            'Content-Type' => 'application/json',
        ]);
    }
}
