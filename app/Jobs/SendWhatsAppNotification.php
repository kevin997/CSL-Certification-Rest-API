<?php

namespace App\Jobs;

use App\Services\WachapNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Queued single WhatsApp message send (ported from the shopikat project).
 */
class SendWhatsAppNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    public function __construct(
        public string $phoneNumber,
        public string $message,
    ) {}

    public function handle(WachapNotificationService $wachap): void
    {
        if (! WachapNotificationService::isConfigured()) {
            Log::warning('WhatsApp notification skipped because Wachap is not configured.');

            return;
        }

        $wachap->sendWhatsApp($this->phoneNumber, $this->message);
    }
}
