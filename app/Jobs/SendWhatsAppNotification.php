<?php

namespace App\Jobs;

use App\Models\MarketingAutomation;
use App\Models\MarketingConsent;
use App\Models\Order;
use App\Models\SalesFormSubmission;
use App\Services\WachapNotificationService;
use App\Support\Sales\SalesFormPhone;
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

    public function __construct(
        public string $phoneNumber,
        public string $message,
        public ?int $pendingOrderId = null,
        public bool $paymentReminder = false,
    ) {}

    public function backoff(): array
    {
        return $this->paymentReminder ? [60, 900] : [10];
    }

    public function handle(WachapNotificationService $wachap): void
    {
        if ($this->pendingOrderId !== null) {
            $order = Order::withoutGlobalScopes()->with('salesFormSubmission')->find($this->pendingOrderId);

            if (! $order || $order->status !== Order::STATUS_PENDING) {
                return;
            }

            if ($order->created_at?->lte(now()->subDays(7)) !== false
                || ($this->paymentReminder && $order->abandoned_whatsapp_sent_at !== null)) {
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

        if (! $this->paymentReminder || ! isset($order)) {
            $wachap->sendWhatsApp($this->phoneNumber, $this->message);

            return;
        }

        $response = $wachap->sendVerifiedPaymentReminder($this->phoneNumber, $this->message, function () use ($order): bool {
            $current = Order::withoutGlobalScopes()->with('salesFormSubmission')->find($order->id);
            if (! $current || $current->status !== Order::STATUS_PENDING
                || $current->created_at?->lte(now()->subDays(7)) !== false
                || $current->abandoned_whatsapp_sent_at !== null) {
                return false;
            }

            if (! MarketingAutomation::enabledFor($current->environment_id, MarketingAutomation::TRIGGER_ORDER_ABANDONED)
                ?->usesChannel(MarketingAutomation::CHANNEL_WHATSAPP)) {
                return false;
            }

            return $current->type !== Order::TYPE_SALES_FORM
                || ($current->salesFormSubmission?->status === SalesFormSubmission::STATUS_PENDING
                    && MarketingConsent::isGranted($current->salesFormSubmission, 'whatsapp')
                    && SalesFormPhone::normalize($current->salesFormSubmission->phone) === '+'.$this->phoneNumber);
        });

        if ($response?->successful()) {
            $order->forceFill(['abandoned_whatsapp_sent_at' => now()])->saveQuietly();
        }
    }
}
