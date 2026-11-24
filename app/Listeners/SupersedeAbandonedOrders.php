<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Services\Payments\AbandonedOrderSupersession;

/**
 * Runs synchronously with the completion event so abandoned attempts are
 * voided inside the same settlement transaction, before any queued listener
 * can fulfil or report the paid order.
 */
class SupersedeAbandonedOrders
{
    public function __construct(private readonly AbandonedOrderSupersession $supersession) {}

    public function handle(OrderCompleted $event): void
    {
        $this->supersession->supersedeFor($event->order);
    }
}
