<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MarketingConsent;
use App\Models\SalesFormSubmission;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
            $latestState = MarketingConsent::latestStateFor($submission, $channel);

            if ($latestState?->status !== MarketingConsent::STATUS_REVOKED) {
                MarketingConsent::revoke(
                    $submission,
                    $channel,
                    'unsubscribe',
                    SalesFormSubmission::MARKETING_TERMS_VERSION,
                    CarbonImmutable::now(),
                );
            }
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
}
