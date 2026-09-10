<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketingConsent;
use App\Models\SalesFormSubmission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * One-click marketing unsubscribe link, reached via a signed URL emailed
 * with every marketing campaign. The `signed` route middleware rejects
 * tampered/expired links with Laravel's own 403 before this ever runs.
 */
class MarketingUnsubscribeController extends Controller
{
    public function __invoke(Request $request, string $user): Response
    {
        // Earlier platform-only campaign links did not include a channel and
        // were email-only. New links always carry and sign this parameter.
        $channel = $request->query('channel', 'email');

        if (! is_string($channel) || ! in_array($channel, ['email', 'whatsapp'], true)) {
            abort(404);
        }

        $submission = $this->submissionFromReference($user);

        if ($submission) {
            $this->revokeChannelOnce($submission, $channel);
        } elseif (ctype_digit($user)) {
            // Keep pre-existing platform campaign links functional while new
            // form-campaign links use the opaque, tenant-bound reference above.
            $legacyUser = User::findOrFail((int) $user);
            $legacyUser->forceFill(['marketing_opt_in' => false])->save();
        } else {
            abort(404);
        }

        $html = <<<'HTML'
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>KURSA</title>
</head>
<body style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background-color:#f4f4f7; margin:0; padding:40px 20px; text-align:center;">
    <p style="font-size:16px; color:#333;">Vos préférences de communication ont été mises à jour.</p>
    <p style="font-size:16px; color:#333;">Your communication preferences have been updated.</p>
</body>
</html>
HTML;

        return response($html)->header('Content-Type', 'text/html');
    }

    private function submissionFromReference(string $reference): ?SalesFormSubmission
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
            || ! is_int($payload['submission_id'])) {
            return null;
        }

        return SalesFormSubmission::withoutGlobalScopes()
            ->where('environment_id', $payload['environment_id'])
            ->whereKey($payload['submission_id'])
            ->first();
    }

    private function revokeChannelOnce(SalesFormSubmission $submission, string $channel): void
    {
        try {
            Cache::lock($this->lockKey($submission, $channel), 10)->block(3, function () use ($submission, $channel): void {
                $latestState = MarketingConsent::withoutGlobalScopes()
                    ->where('environment_id', $submission->environment_id)
                    ->where('sales_form_submission_id', $submission->id)
                    ->where('channel', $channel)
                    ->orderByDesc('created_at')
                    ->orderByDesc('id')
                    ->first();

                if ($latestState?->status === MarketingConsent::STATUS_REVOKED) {
                    return;
                }

                MarketingConsent::revoke(
                    $submission,
                    $channel,
                    'unsubscribe',
                    $latestState?->terms_version
                        ?: $submission->marketing_terms_version
                        ?: SalesFormSubmission::MARKETING_TERMS_VERSION,
                    CarbonImmutable::now(),
                );
            });
        } catch (LockTimeoutException) {
            abort(503);
        }
    }

    private function lockKey(SalesFormSubmission $submission, string $channel): string
    {
        return 'marketing-unsubscribe:'.hash('sha256', implode(':', [
            $submission->environment_id,
            $submission->id,
            $channel,
        ]));
    }
}
