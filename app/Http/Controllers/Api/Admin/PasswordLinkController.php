<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\SendWhatsAppNotification;
use App\Mail\EnvironmentResetPasswordMail;
use App\Models\Environment;
use App\Models\User;
use App\Services\TelegramService;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * Admin-initiated re-send of an environment owner's password-set link.
 *
 * Onboarding emails the link once, from no.reply@okenlysolutions.com — a domain
 * unrelated to the product — so it lands in spam often enough that owners never
 * open it and cannot get into the academy they just created. Support had no way
 * to recover that: password_reset_tokens stores a bcrypt hash, so the original
 * link is unrecoverable and the only fix was a manual DB write.
 *
 * This issues a FRESH token (invalidating any previous one), delivers it over
 * email and WhatsApp, and returns the URL so an admin can also paste it into a
 * support conversation.
 *
 * Issuing is rolled back when NO channel delivers: the previous token row is
 * restored verbatim (so the owner's old link keeps working, with its original
 * expiry) and the response carries rolled_back=true with a null
 * password_set_url instead of a link that reached nobody.
 */
class PasswordLinkController extends Controller
{
    private function guard(Request $request): bool
    {
        return $request->user() && $request->user()->role->value === 'super_admin';
    }

    /**
     * POST /api/admin/environments/{environmentId}/resend-password-link
     */
    public function resend(Request $request, int $environmentId): JsonResponse
    {
        if (! $this->guard($request)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'channels' => 'sometimes|array',
            'channels.*' => 'in:email,whatsapp,telegram',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $environment = Environment::find($environmentId);

        if (! $environment) {
            return response()->json(['status' => 'error', 'message' => 'Environment not found'], 404);
        }

        $owner = User::find($environment->owner_id);

        if (! $owner) {
            return response()->json([
                'status' => 'error',
                'message' => 'This environment has no owner to send a link to.',
            ], 422);
        }

        $channels = $request->input('channels', ['email', 'whatsapp', 'telegram']);

        /**
         * Issuing a fresh token destroys the owner's previous (possibly still
         * working) link before we know whether the new one can be delivered at
         * all. Snapshot the current token row so a total delivery failure can
         * put things back exactly as they were instead of leaving the owner
         * with a link that reached nobody.
         */
        $previousTokenRow = DB::table('password_reset_tokens')
            ->where('email', $owner->email)
            ->first();

        $token = Str::random(64);
        $url = $this->persistToken($token, $owner, $environment);

        $delivered = [];
        $failed = [];

        try {
            [$delivered, $failed] = $this->deliver($channels, $environment, $owner, $token, $url);
        } catch (\Throwable $e) {
            // Belt and braces: each channel catches its own errors, so this
            // only fires on something unexpected — never leave the rotated
            // token in place when delivery blew up before completing.
            $this->rollbackIssuedToken($previousTokenRow, $owner->email, $token);
            throw $e;
        }

        $rolledBack = false;

        if ($delivered === [] && $failed !== []) {
            $this->rollbackIssuedToken($previousTokenRow, $owner->email, $token);
            $rolledBack = true;
        }

        Log::info('Admin resent environment password-set link', [
            'environment_id' => $environment->id,
            'owner_id' => $owner->id,
            'admin_id' => $request->user()->id,
            'delivered' => $delivered,
            'failed' => $failed,
            'rolled_back' => $rolledBack,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'environment_id' => $environment->id,
                'owner_email' => $owner->email,
                'owner_whatsapp' => $owner->whatsapp_number,
                // Returned so an admin can paste it into a support thread.
                // Single-use: issuing this invalidated any previous link.
                // Null when every channel failed and the token was rolled
                // back — the URL would be dead, and the owner's previous
                // link (if any) still stands.
                'password_set_url' => $rolledBack ? null : $url,
                'delivered' => $delivered,
                'failed' => $failed,
                'rolled_back' => $rolledBack,
            ],
        ]);
    }

    /**
     * Attempts delivery over each requested channel. Every channel traps its
     * own errors, so this reports outcomes rather than throwing.
     *
     * @param  array<int, string>  $channels
     * @return array{0: array<int, string>, 1: array<int, string>} [delivered, failed]
     */
    private function deliver(array $channels, Environment $environment, User $owner, string $token, string $url): array
    {
        $delivered = [];
        $failed = [];

        if (in_array('email', $channels, true)) {
            try {
                Mail::to($owner->email)->send(
                    new EnvironmentResetPasswordMail($token, $environment, $owner->email, $owner->email)
                );
                $delivered[] = 'email';
            } catch (\Throwable $e) {
                Log::error('Admin resend password link: email failed: '.$e->getMessage(), [
                    'environment_id' => $environment->id,
                ]);
                $failed[] = 'email';
            }
        }

        if (in_array('whatsapp', $channels, true)) {
            $phone = PhoneNumber::normalize((string) ($owner->whatsapp_number ?? ''), $environment->country_code);

            if ($phone === '') {
                $failed[] = 'whatsapp';
            } else {
                try {
                    SendWhatsAppNotification::dispatch($phone, $this->whatsAppBody($environment, $owner, $url));
                    $delivered[] = 'whatsapp';
                } catch (\Throwable $e) {
                    Log::error('Admin resend password link: WhatsApp dispatch failed: '.$e->getMessage(), [
                        'environment_id' => $environment->id,
                    ]);
                    $failed[] = 'whatsapp';
                }
            }
        }

        if (in_array('telegram', $channels, true)) {
            try {
                $telegram = app(TelegramService::class);
                $chatId = $telegram->getChatId();

                if (! $chatId) {
                    $failed[] = 'telegram';
                } else {
                    // sendMessage() forces parse_mode=MarkdownV2, so the body
                    // must be escaped or Telegram rejects the whole request
                    // with "can't parse entities" and nothing is delivered.
                    $sent = $telegram->sendMessage(
                        $chatId,
                        $telegram->escapeMarkdownV2($this->telegramBody($environment, $owner)),
                        ['text' => 'Definir le mot de passe', 'url' => $url],
                    );
                    $sent ? $delivered[] = 'telegram' : $failed[] = 'telegram';
                }
            } catch (\Throwable $e) {
                Log::error('Admin resend password link: Telegram send failed: '.$e->getMessage(), [
                    'environment_id' => $environment->id,
                ]);
                $failed[] = 'telegram';
            }
        }

        return [$delivered, $failed];
    }

    /**
     * Restores the token state captured before persistToken() overwrote it.
     *
     * The previous row is restored verbatim — including its original
     * created_at, because link expiry is computed from that column
     * (config/auth.php → passwords.users.expire); restoring with now() would
     * silently extend the old link's life. When no row existed before, the
     * freshly written one is removed so no orphan token lingers.
     *
     * password_reset_metadata is keyed by the PLAINTEXT token, so
     * persistToken() inserted a brand-new row there and left any prior one
     * untouched — rolling back only requires deleting the new row.
     */
    private function rollbackIssuedToken(?object $previousTokenRow, string $email, string $newToken): void
    {
        DB::transaction(function () use ($previousTokenRow, $email, $newToken): void {
            DB::table('password_reset_metadata')->where('token', $newToken)->delete();

            if ($previousTokenRow !== null) {
                DB::table('password_reset_tokens')->updateOrInsert(
                    ['email' => $email],
                    [
                        'email' => $email,
                        'token' => $previousTokenRow->token,
                        'created_at' => $previousTokenRow->created_at,
                    ]
                );
            } else {
                DB::table('password_reset_tokens')->where('email', $email)->delete();
            }
        });
    }

    /**
     * Mirrors LicenceService::sendPasswordSetLink() so the token this issues is
     * accepted by the same EnvironmentUserController::resetPassword() flow.
     */
    private function persistToken(string $token, User $owner, Environment $environment): string
    {
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $owner->email],
            ['email' => $owner->email, 'token' => Hash::make($token), 'created_at' => now()]
        );

        DB::table('password_reset_metadata')->updateOrInsert(
            ['token' => $token],
            [
                'token' => $token,
                'metadata' => json_encode([
                    'environment_id' => $environment->id,
                    'environment_email' => $owner->email,
                    'is_environment_reset' => true,
                ]),
                'created_at' => now(),
            ]
        );

        return 'https://'.$environment->primary_domain.'/auth/reset-password?'.http_build_query([
            'token' => $token,
            'email' => $owner->email,
            'environment_id' => $environment->id,
        ]);
    }

    /**
     * Telegram goes to the internal support group, not to the owner, so it
     * names who the link belongs to. The URL rides on the inline button rather
     * than in the text, which keeps it out of MarkdownV2 escaping entirely.
     */
    private function telegramBody(Environment $environment, User $owner): string
    {
        return implode("\n", [
            'Lien de mot de passe - '.$environment->name,
            '',
            'Proprietaire: '.$owner->name,
            'Email: '.$owner->email,
            'Academie: '.$environment->primary_domain,
            '',
            'Usage unique, valable 60 minutes.',
        ]);
    }

    private function whatsAppBody(Environment $environment, User $owner, string $url): string
    {
        return implode("\n", [
            "Bonjour {$owner->name},",
            '',
            "Voici votre lien pour définir le mot de passe de *{$environment->name}* (usage unique) :",
            $url,
            '',
            '---',
            "Hello {$owner->name},",
            '',
            "Here is your link to set the password for *{$environment->name}* (single-use):",
            $url,
        ]);
    }
}
