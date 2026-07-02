<?php

namespace App\Events;

use App\Models\Order;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired when a storefront order is created (before payment).
 * Deliberately NOT fired for sales-form orders — the form_submitted
 * automation already covers that path.
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
