<?php

namespace App\Listeners;

use App\Events\SalesFormSubmitted;
use App\Services\MarketingAutomationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class ProcessFormSubmittedAutomation implements ShouldQueue
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
    public function handle(SalesFormSubmitted $event): void
    {
        try {
            app(MarketingAutomationService::class)->handleFormSubmitted($event->submission);
        } catch (\Exception $e) {
            Log::error("Form-submitted automation failed for submission {$event->submission->id}: {$e->getMessage()}");

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(SalesFormSubmitted $event, \Throwable $exception): void
    {
        Log::error("Form-submitted automation failed after multiple attempts for submission {$event->submission->id}: {$exception->getMessage()}");
    }
}
