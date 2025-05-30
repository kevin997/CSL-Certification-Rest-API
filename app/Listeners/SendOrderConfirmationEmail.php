<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Mail\OrderConfirmation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendOrderConfirmationEmail implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(OrderCompleted $event): void
    {
        try {
            Mail::to($event->order->billing_email)->send(new OrderConfirmation($event->order));
            Log::info("Order confirmation email sent to {$event->order->billing_email} for order {$event->order->id}");
        } catch (\Exception $e) {
            Log::error("Failed to send order confirmation email for order {$event->order->id}: {$e->getMessage()}");
            
            // Rethrow the exception to trigger a retry if needed
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(OrderCompleted $event, \Throwable $exception): void
    {
        Log::error("Failed to send order confirmation email after multiple attempts for order {$event->order->id}: {$exception->getMessage()}");
    }
}
