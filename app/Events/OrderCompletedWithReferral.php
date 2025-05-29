<?php

namespace App\Events;

use App\Models\Order;
use App\Models\EnvironmentReferral;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OrderCompletedWithReferral
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The order instance.
     *
     * @var \App\Models\Order
     */
    public $order;

    /**
     * The referral instance.
     *
     * @var \App\Models\EnvironmentReferral
     */
    public $referral;

    /**
     * Create a new event instance.
     *
     * @param \App\Models\Order $order
     * @param \App\Models\EnvironmentReferral $referral
     * @return void
     */
    public function __construct(Order $order, EnvironmentReferral $referral)
    {
        $this->order = $order;
        $this->referral = $referral;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
