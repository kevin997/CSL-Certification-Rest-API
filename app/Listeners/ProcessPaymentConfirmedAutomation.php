<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Models\MarketingAutomation;
use App\Services\MarketingAutomationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProcessPaymentConfirmedAutomation implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Handle the event.
     */
    public function handle(OrderCompleted $event): void
    {
        try {
            app(MarketingAutomationService::class)
                ->handleOrderTrigger($event->order, MarketingAutomation::TRIGGER_PAYMENT_CONFIRMED);
        } catch (\Exception $e) {
            Log::error("Payment-confirmed automation failed for order {$event->order->id}: {$e->getMessage()}");

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(OrderCompleted $event, \Throwable $exception): void
    {
        Log::error("Payment-confirmed automation failed after multiple attempts for order {$event->order->id}: {$exception->getMessage()}");
    }
}
