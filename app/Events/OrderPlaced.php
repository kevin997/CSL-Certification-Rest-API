<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when an order is created and still awaiting payment.
 *
 * Storefront checkout fires it for new pending orders. The sales-form
 * submission controller fires it for each pending paid order too, so the
 * order_placed automation can send the buyer a continue-payment link; the
 * form_submitted automation remains the instructor-facing lead notification.
 */
class OrderPlaced
{
    use Dispatchable, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(
        public Order $order,
    ) {}
}
