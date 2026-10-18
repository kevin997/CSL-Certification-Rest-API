<?php

namespace App\Console\Commands;

use App\Models\MarketingAutomation;
use App\Models\Order;
use App\Models\SalesFormSubmission;
use App\Services\MarketingAutomationService;
use App\Services\SalesFormPendingReminderService;
use App\Support\WhatsAppThrottle;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Sends the order_abandoned automation for orders stuck in pending.
 * Idempotent via orders.abandoned_reminder_sent_at (shopikat's
 * followup_sent_at pattern); throttled between sends.
 */
class ProcessAbandonedOrdersCommand extends Command
{
    protected $signature = 'automations:process-abandoned-orders';

    protected $description = 'Send Email/WhatsApp reminders for orders left pending (abandoned checkout)';

    public function handle(MarketingAutomationService $automations, SalesFormPendingReminderService $salesFormReminders): int
    {
        // Environments with the order_abandoned automation enabled
        $enabled = MarketingAutomation::withoutGlobalScopes()
            ->where('trigger', MarketingAutomation::TRIGGER_ORDER_ABANDONED)
            ->where('enabled', true)
            ->get();

        $processed = 0;

        // Sales-form prospects use their submitted contact details and channel consent.
        $salesFormOrders = Order::withoutGlobalScopes()
            ->where('type', Order::TYPE_SALES_FORM)
            ->where('status', Order::STATUS_PENDING)
            ->whereNull('abandoned_reminder_sent_at')
            ->where('created_at', '<=', now()->subHours(24))
            ->where('created_at', '>=', now()->subDays(7))
            ->whereHas('salesFormSubmission', fn ($query) => $query->withoutGlobalScopes()->where('status', SalesFormSubmission::STATUS_PENDING))
            ->orderBy('created_at')
            ->limit(200)
            ->get();

        foreach ($salesFormOrders as $order) {
            try {
                $order->refresh();
                if ($order->status !== Order::STATUS_PENDING) {
                    continue;
                }

                $salesFormReminders->send($order);
                $order->forceFill(['abandoned_reminder_sent_at' => now()])->saveQuietly();
                $processed++;
                WhatsAppThrottle::pause();
            } catch (\Exception $e) {
                Log::error("Sales-form reminder failed for order {$order->id}: {$e->getMessage()}");
            }
        }

        foreach ($enabled as $automation) {
            $delayHours = max(1, (int) $automation->configValue('abandoned_delay_hours', 24));

            $orders = Order::withoutGlobalScopes()
                ->where('environment_id', $automation->environment_id)
                ->where('status', Order::STATUS_PENDING)
                ->where('type', '!=', Order::TYPE_SALES_FORM)
                ->whereNull('abandoned_reminder_sent_at')
                ->where('created_at', '<=', now()->subHours($delayHours))
                // Only remind about reasonably fresh orders — don't blast months-old rows on first rollout
                ->where('created_at', '>=', now()->subDays(7))
                ->orderBy('created_at')
                ->limit(200)
                ->get();

            foreach ($orders as $order) {
                try {
                    $automations->handleOrderTrigger($order, MarketingAutomation::TRIGGER_ORDER_ABANDONED);

                    // Stamp regardless of channel outcome — one reminder per order, ever
                    $order->forceFill(['abandoned_reminder_sent_at' => now()])->saveQuietly();
                    $processed++;

                    WhatsAppThrottle::pause();
                } catch (\Exception $e) {
                    Log::error("Abandoned-order automation failed for order {$order->id}: {$e->getMessage()}");
                }
            }
        }

        $this->info("Processed {$processed} abandoned order(s).");

        return self::SUCCESS;
    }
}
