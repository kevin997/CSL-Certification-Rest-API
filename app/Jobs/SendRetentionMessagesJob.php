<?php

namespace App\Jobs;

use App\Mail\RetentionMail;
use App\Models\Environment;
use App\Models\RetentionMessage;
use App\Services\PushNotificationService;
use App\Services\WachapNotificationService;
use App\Support\PhoneNumber;
use App\Support\WhatsAppThrottle;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Sends one batch of pre-rendered retention messages, paced by the anti-ban
 * WhatsApp throttle, and records each attempt in `retention_messages` (which
 * also powers cooldowns). Best-effort: tries=1 so a mid-batch failure never
 * re-blasts recipients who already received their message this run.
 *
 * Ported from shopikat's SendRetentionMessagesJob. shopikat is WhatsApp-only
 * (Wachap) with SMS as its only fallback; KURSA gives every user an email
 * address, so the channel order here is WhatsApp → email fallback (no SMS).
 * A best-effort web push fires alongside, same as shopikat.
 */
class SendRetentionMessagesJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    // Human-paced sends run long; don't let the queue kill the batch.
    public int $timeout = 0;

    /**
     * @param  array<int, array{recipient_type:string, recipient_id:string, scenario_key:string, phone:?string, email:?string, message:string, push_user_id:?int, environment_id:?int}>  $items
     */
    public function __construct(private readonly array $items) {}

    public function handle(WachapNotificationService $wachap): void
    {
        $whatsappConfigured = WachapNotificationService::isConfigured();

        foreach ($this->items as $item) {
            $message = (string) $item['message'];
            $email = $item['email'] ?? null;

            // Complementary channel: fire a best-effort web push too (no-op if
            // the recipient has no subscription / push isn't configured).
            $this->maybePush($item, $message);

            // PhoneNumber::normalize() never returns null — empty input yields "".
            $phone = PhoneNumber::normalize((string) ($item['phone'] ?? ''));
            $phone = $phone !== '' ? $phone : null;

            $sent = false;
            $channel = null;
            $error = null;

            if ($phone !== null && $whatsappConfigured) {
                try {
                    $wachap->sendWhatsApp($phone, $message);
                    $sent = true;
                    $channel = 'whatsapp';
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                    Log::warning('Retention WhatsApp send failed', [
                        'scenario' => $item['scenario_key'] ?? null,
                        'recipient' => ($item['recipient_type'] ?? '').':'.($item['recipient_id'] ?? ''),
                        'error' => $error,
                    ]);
                }

                // Anti-ban pacing — only between actual WhatsApp send attempts.
                WhatsAppThrottle::pause();
            }

            // Email fallback: no phone, WhatsApp not configured, or the send failed above.
            if (! $sent) {
                if ($email) {
                    try {
                        $subject = Str::of($message)->before("\n")->squish()->limit(120)->toString();

                        Mail::to($email)->send(new RetentionMail($subject, $message, $this->environmentFor($item)));
                        $sent = true;
                        $channel = 'email';
                        $error = null;
                    } catch (Throwable $e) {
                        $channel = 'email';
                        $error = $e->getMessage();
                        Log::warning('Retention email fallback failed', [
                            'scenario' => $item['scenario_key'] ?? null,
                            'recipient' => ($item['recipient_type'] ?? '').':'.($item['recipient_id'] ?? ''),
                            'error' => $error,
                        ]);
                    }
                } else {
                    $error = $error ?? 'no phone or email available';
                }
            }

            $this->record($item, $phone, $sent
                ? RetentionMessage::STATUS_SENT
                : ($channel ? RetentionMessage::STATUS_FAILED : RetentionMessage::STATUS_SKIPPED), $error, $channel);
        }
    }

    /**
     * Best-effort web push alongside WhatsApp/email, using the first line of
     * the message as the push body. Silently skipped when the target has no
     * push user/environment, or when PushNotificationService has nothing to
     * send to (no VAPID keys, no subscription).
     *
     * @param  array{recipient_type:string, recipient_id:string, scenario_key:string, phone:?string, email:?string, message:string, push_user_id:?int, environment_id:?int}  $item
     */
    private function maybePush(array $item, string $message): void
    {
        $userId = $item['push_user_id'] ?? null;
        $environmentId = $item['environment_id'] ?? null;

        if (! $userId || ! $environmentId) {
            return;
        }

        $body = Str::of($message)->before("\n")->squish()->limit(140)->toString();

        try {
            app(PushNotificationService::class)->sendToUser((int) $environmentId, (int) $userId, [
                'title' => 'KURSA',
                'body' => $body,
                'data' => [
                    'type' => 'retention',
                    'scenario' => $item['scenario_key'] ?? null,
                ],
            ]);
        } catch (Throwable $e) {
            Log::warning('Retention web push failed', [
                'scenario' => $item['scenario_key'] ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array{environment_id:?int}  $item
     */
    private function environmentFor(array $item): ?Environment
    {
        $environmentId = $item['environment_id'] ?? null;

        return $environmentId ? Environment::find($environmentId) : null;
    }

    /**
     * @param  array{recipient_type:string, recipient_id:string, scenario_key:string}  $item
     */
    private function record(array $item, ?string $phone, string $status, ?string $error = null, ?string $channel = null): void
    {
        RetentionMessage::create([
            'recipient_type' => $item['recipient_type'],
            'recipient_id' => $item['recipient_id'],
            'scenario_key' => $item['scenario_key'],
            'phone' => $phone,
            'status' => $status,
            'error' => $error,
            'meta' => $channel ? ['channel' => $channel] : null,
            'sent_at' => $status === RetentionMessage::STATUS_SENT ? now() : null,
        ]);
    }
}
