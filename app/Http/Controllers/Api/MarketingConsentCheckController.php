<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketingConsent;
use App\Models\SalesFormSubmission;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Crypt;

class MarketingConsentCheckController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $validated = $request->validate([
            'environment_id' => ['required', 'integer', 'min:1'],
            'recipient_ref' => ['required', 'string', 'max:4096'],
            'channel' => ['required', 'string', 'in:email,whatsapp'],
        ]);

        $submission = $this->submissionFromReference(
            $validated['recipient_ref'],
            (int) $validated['environment_id'],
        );

        if (! $submission) {
            return $this->json(['message' => 'The recipient reference is invalid.'], 422);
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

    private function submissionFromReference(string $reference, int $environmentId): ?SalesFormSubmission
    {
        try {
            $payload = json_decode(Crypt::decryptString($reference), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($payload)
            || count($payload) !== 2
            || array_diff(array_keys($payload), ['environment_id', 'submission_id']) !== []
            || ! is_int($payload['environment_id'])
            || ! is_int($payload['submission_id'])
            || $payload['environment_id'] !== $environmentId) {
            return null;
        }

        return SalesFormSubmission::withoutGlobalScopes()
            ->where('environment_id', $environmentId)
            ->whereKey($payload['submission_id'])
            ->first();
    }
}
