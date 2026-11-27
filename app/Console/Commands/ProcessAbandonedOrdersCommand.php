<?php

namespace App\Console\Commands;

use App\Models\MarketingAutomation;
use App\Models\Order;
use App\Models\SalesFormSubmission;
use App\Services\MarketingAutomationService;
use App\Services\SalesFormPendingReminderService;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessAbandonedOrdersCommand extends Command
{
    protected $signature = 'automations:process-abandoned-orders';

    protected $description = 'Send Email/WhatsApp reminders for orders left pending (abandoned checkout)';

    public function handle(MarketingAutomationService $automations, SalesFormPendingReminderService $salesFormReminders): int
    {
        $enabled = MarketingAutomation::withoutGlobalScopes()
            ->where('trigger', MarketingAutomation::TRIGGER_ORDER_ABANDONED)
            ->where('enabled', true)->get();

        $whatsAppEnvironments = $enabled->filter(fn (MarketingAutomation $automation) => $automation->usesChannel(MarketingAutomation::CHANNEL_WHATSAPP))
            ->pluck('environment_id');
        foreach ([MarketingAutomation::CHANNEL_WHATSAPP, MarketingAutomation::CHANNEL_EMAIL] as $channel) {
            $this->process(
                fn () => $this->pending($channel, 24 * 60)->where('type', Order::TYPE_SALES_FORM)
                    ->when($channel === MarketingAutomation::CHANNEL_WHATSAPP, fn (Builder $query) => $query->whereIn('environment_id', $whatsAppEnvironments))
                    ->whereHas('salesFormSubmission', fn ($query) => $query->withoutGlobalScopes()
                        ->where('status', SalesFormSubmission::STATUS_PENDING)),
                $channel,
                fn (Order $order) => $salesFormReminders->send($order, $channel),
            );

            foreach ($enabled as $automation) {
                if (! $automation->usesChannel($channel)) {
                    continue;
                }

                $this->process(
                    fn () => $this->pending($channel, max(1, (int) $automation->configValue('abandoned_delay_hours', 24)) * 60)
                        ->where('environment_id', $automation->environment_id)
                        ->whereIn('type', [Order::TYPE_STOREFRONT, Order::TYPE_SUBSCRIPTION_PRODUCT]),
                    $channel,
                    fn (Order $order) => $automations->handleOrderTrigger($order, MarketingAutomation::TRIGGER_ORDER_ABANDONED, $channel),
                );
            }
        }

        return self::SUCCESS;
    }

    private function pending(string $channel, int $emailDelayMinutes): Builder
    {
        $now = now();

        return Order::withoutGlobalScopes()
            ->where('status', Order::STATUS_PENDING)
            ->where('total_amount', '>', 0)
            ->whereNull($this->marker($channel))
            ->where('created_at', '<=', $now->copy()->subMinutes($channel === MarketingAutomation::CHANNEL_WHATSAPP ? 30 : $emailDelayMinutes))
            ->where('created_at', '>', $now->copy()->subDays(7));
    }

    private function marker(string $channel): string
    {
        return $channel === MarketingAutomation::CHANNEL_WHATSAPP
            ? 'abandoned_whatsapp_attempted_at'
            : 'abandoned_reminder_sent_at';
    }

    private function process(callable $candidates, string $channel, callable $dispatch): void
    {
        ($candidates)()->select('id')->chunkById(100, function ($orders) use ($candidates, $channel, $dispatch): void {
            foreach ($orders as $candidate) {
                try {
                    DB::transaction(function () use ($candidates, $candidate, $channel, $dispatch): void {
                        $order = ($candidates)()->lockForUpdate()->find($candidate->id);
                        if ($order && $dispatch($order)) {
                            $order->forceFill([$this->marker($channel) => now()])->saveQuietly();
                        }
                    });
                } catch (\Throwable $error) {
                    Log::error('Pending order reminder failed', [
                        'order_id' => $candidate->id,
                        'channel' => $channel,
                        'exception' => $error::class,
                    ]);
                }
            }
        });
    }
}
