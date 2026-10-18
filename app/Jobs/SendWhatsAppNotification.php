<?php

namespace App\Jobs;

use App\Models\MarketingConsent;
use App\Models\Order;
use App\Models\SalesFormSubmission;
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
        public ?int $pendingOrderId = null,
    ) {}

    public function handle(WachapNotificationService $wachap): void
    {
        if ($this->pendingOrderId !== null) {
            $order = Order::withoutGlobalScopes()->with('salesFormSubmission')->find($this->pendingOrderId);

            if (! $order || $order->status !== Order::STATUS_PENDING) {
                return;
            }

            if ($order->type === Order::TYPE_SALES_FORM && ! $order->salesFormSubmission) {
                return;
            }

            if ($order->salesFormSubmission && (
                $order->salesFormSubmission->status !== SalesFormSubmission::STATUS_PENDING
                || ! MarketingConsent::isGranted($order->salesFormSubmission, 'whatsapp')
            )) {
                return;
            }
        }

        if (! WachapNotificationService::isConfigured()) {
            Log::warning('WhatsApp notification skipped because Wachap is not configured.');

            return;
        }

        $wachap->sendWhatsApp($this->phoneNumber, $this->message);
    }
}
