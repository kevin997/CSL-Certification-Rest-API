<?php

namespace App\Jobs;

use App\Models\Environment;
use App\Models\MarketingAutomation;
use App\Models\MarketingConsent;
use App\Models\Order;
use App\Models\SalesFormSubmission;
use App\Services\WachapNotificationService;
use App\Services\WhatsApp\AcademyWhatsApp;
use App\Services\WhatsApp\AcademyWhatsAppDeliveryUncertain;
use App\Support\Sales\SalesFormPhone;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;

/**
 * Queued single WhatsApp message send (ported from the shopikat project).
 */
class SendWhatsAppNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public ?int $pendingOrderId = null;

    public bool $paymentReminder = false;

    public ?int $completedPaymentOrderId = null;

    public ?string $reminderRecipient = null;

    public function __construct(
        public string $phoneNumber,
        public string $message,
        ?int $pendingOrderId = null,
        bool $paymentReminder = false,
        ?int $completedPaymentOrderId = null,
        ?string $reminderRecipient = null,
    ) {
        $this->pendingOrderId = $pendingOrderId;
        $this->paymentReminder = $paymentReminder;
        $this->completedPaymentOrderId = $completedPaymentOrderId;
        $this->reminderRecipient = $reminderRecipient;
    }

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

        if ($this->completedPaymentOrderId !== null) {
            $this->sendPaymentConfirmation($wachap);

            return;
        }

        if (! $this->paymentReminder || ! isset($order)) {
            if (WachapNotificationService::isConfigured()) {
                $wachap->sendWhatsApp($this->phoneNumber, $this->message);
            } else {
                Log::warning('WhatsApp notification skipped because Wachap is not configured.');
            }

            return;
        }

        $stillEligible = function () use ($order): bool {
            $current = Order::withoutGlobalScopes()->with('salesFormSubmission')->find($order->id);
            if (! $current || $current->status !== Order::STATUS_PENDING
                || $current->created_at?->lte(now()->subDays(7)) !== false
                || $current->abandoned_whatsapp_sent_at !== null) {
                return false;
            }

            $config = MarketingAutomation::enabledFor($current->environment_id, MarketingAutomation::TRIGGER_ORDER_ABANDONED);
            if (! $config?->usesChannel(MarketingAutomation::CHANNEL_WHATSAPP)
                || ($this->reminderRecipient !== null && $config->recipient !== $this->reminderRecipient)) {
                return false;
            }

            return $current->type !== Order::TYPE_SALES_FORM
                || ($current->salesFormSubmission?->status === SalesFormSubmission::STATUS_PENDING
                    && MarketingConsent::isGranted($current->salesFormSubmission, 'whatsapp')
                    && SalesFormPhone::normalize($current->salesFormSubmission->phone) === '+'.$this->phoneNumber);
        };

        if (! $stillEligible()) {
            return;
        }

        $config = MarketingAutomation::enabledFor($order->environment_id, MarketingAutomation::TRIGGER_ORDER_ABANDONED);
        $academy = ($order->type === Order::TYPE_SALES_FORM || $config?->recipient === MarketingAutomation::RECIPIENT_CUSTOMER)
            ? Environment::find($order->environment_id) : null;
        $response = $this->sendPayment($wachap, $academy, $stillEligible, verifyAdmin: true);

        if ($response?->successful()) {
            $order->forceFill(['abandoned_whatsapp_sent_at' => now()])->saveQuietly();
        }
    }

    private function sendPaymentConfirmation(WachapNotificationService $wachap): void
    {
        $stillEligible = function (): bool {
            $order = Order::withoutGlobalScopes()->with(['user', 'salesFormSubmission'])->find($this->completedPaymentOrderId);
            if (! $order || $order->status !== Order::STATUS_COMPLETED) {
                return false;
            }
            $config = MarketingAutomation::enabledFor($order->environment_id, MarketingAutomation::TRIGGER_PAYMENT_CONFIRMED);
            $phone = $order->phone_number ?: ($order->user?->whatsapp_number ?: $order->salesFormSubmission?->phone);

            return $config?->recipient === MarketingAutomation::RECIPIENT_CUSTOMER
                && $config->usesChannel(MarketingAutomation::CHANNEL_WHATSAPP)
                && preg_replace('/[^0-9]/', '', (string) $phone) === $this->phoneNumber;
        };

        if (! $stillEligible()) {
            return;
        }
        $order = Order::withoutGlobalScopes()->find($this->completedPaymentOrderId);
        $academy = $order ? Environment::find($order->environment_id) : null;
        if ($academy) {
            $this->sendPayment($wachap, $academy, $stillEligible, verifyAdmin: false);
        }
    }

    private function sendPayment(WachapNotificationService $wachap, ?Environment $academy, callable $stillEligible, bool $verifyAdmin): ?Response
    {
        $tenantWhatsApp = app(AcademyWhatsApp::class);
        $tenantSession = $academy ? $tenantWhatsApp->senderFor($academy) : null;
        if ($tenantSession !== null) {
            try {
                return $tenantWhatsApp->send($academy, $tenantSession, $this->phoneNumber, $this->message, $stillEligible);
            } catch (AcademyWhatsAppDeliveryUncertain $exception) {
                // The tenant gateway may have submitted the message already.
                // A retry could otherwise use the platform sender after unlink.
                $this->fail($exception);
                Log::warning('Academy WhatsApp payment message delivery not confirmed.', ['environment_id' => $academy->id]);

                return null;
            }
        }

        if (! WachapNotificationService::isConfigured() || ! $stillEligible()) {
            return null;
        }

        return $verifyAdmin
            ? $wachap->sendVerifiedPaymentReminder($this->phoneNumber, $this->message, $stillEligible)
            : $wachap->sendWhatsApp($this->phoneNumber, $this->message);
    }
}
