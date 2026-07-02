<?php

namespace App\Listeners;

use App\Events\OrderPlaced;
use App\Models\MarketingAutomation;
use App\Services\MarketingAutomationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProcessOrderPlacedAutomation implements ShouldQueue
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
    public function handle(OrderPlaced $event): void
    {
        try {
            app(MarketingAutomationService::class)
                ->handleOrderTrigger($event->order, MarketingAutomation::TRIGGER_ORDER_PLACED);
        } catch (\Exception $e) {
            Log::error("Order-placed automation failed for order {$event->order->id}: {$e->getMessage()}");

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(OrderPlaced $event, \Throwable $exception): void
    {
        Log::error("Order-placed automation failed after multiple attempts for order {$event->order->id}: {$exception->getMessage()}");
    }
}
