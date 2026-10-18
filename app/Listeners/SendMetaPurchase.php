<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Services\MetaConversionsService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendMetaPurchase implements ShouldQueue
{
    public function handle(OrderCompleted $event): void
    {
        app(MetaConversionsService::class)->purchase($event->order);
    }
}
