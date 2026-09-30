<?php

namespace App\Support\Marketing;

use App\Models\SalesFormSubmission;
use Illuminate\Support\Facades\Crypt;

/**
 * The opaque recipient_ref the marketing service holds instead of an address.
 *
 * It is an encrypted {environment_id, submission_id} pair, so the marketing
 * service never needs the email to ask about or report on a recipient, and a
 * reference minted for one environment cannot be replayed against another.
 */
final class MarketingRecipientReference
{
    public static function submission(string $reference, int $environmentId): ?SalesFormSubmission
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
