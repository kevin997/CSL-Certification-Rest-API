<?php

namespace App\Services;

use App\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    private function buildWebPush(): ?WebPush
    {
        $publicKey = (string) env('VAPID_PUBLIC_KEY', 'BGXX0uU01rtvrPCa_wfKwSKI3dK2M5_IqGWO7UUsH7S4vmrUya_R1or7xvsLilNcF5q1Gy_M5Q_397lTRHrQRlE');
        $privateKey = (string) env('VAPID_PRIVATE_KEY', 'HLJ92u4KrOx7jLMLw8aq9JzHQhmiVOWUwJgfaIDCUyk');
        $subject = (string) env('VAPID_SUBJECT', 'mailto:kevinliboire@gmail.com');

        if ($publicKey === '' || $privateKey === '') {
            return null;
        }

        return new WebPush([
            'VAPID' => [
                'subject' => $subject,
                'publicKey' => $publicKey,
                'privateKey' => $privateKey,
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function sendToUser(int $environmentId, int $userId, array $payload): void
    {
        $webPush = $this->buildWebPush();
        if (!$webPush) {
            return;
        }

        $subs = PushSubscription::where('environment_id', $environmentId)
            ->where('user_id', $userId)
            ->get();

        if ($subs->isEmpty()) {
            return;
        }

        $json = json_encode($payload);
        if ($json === false) {
            return;
        }

        foreach ($subs as $sub) {
            try {
                $subscription = Subscription::create([
                    'endpoint' => $sub->endpoint,
                    'publicKey' => $sub->public_key,
                    'authToken' => $sub->auth_token,
                    'contentEncoding' => $sub->content_encoding,
                ]);

                $webPush->queueNotification($subscription, $json);
            } catch (\Throwable $e) {
                Log::warning('Failed to queue web push notification', [
                    'environment_id' => $environmentId,
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        try {
            foreach ($webPush->flush() as $report) {
                if (!$report->isSuccess()) {
                    $endpoint = $report->getRequest()->getUri()->__toString();

                    PushSubscription::where('environment_id', $environmentId)
                        ->where('user_id', $userId)
                        ->where('endpoint', $endpoint)
                        ->delete();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('Failed to flush web push notifications', [
                'environment_id' => $environmentId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
