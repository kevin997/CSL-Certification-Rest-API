<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Services\MetaConversionsService;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;

class SendMetaPurchase implements ShouldQueueAfterCommit
{
    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public function handle(OrderCompleted $event): void
    {
        app(MetaConversionsService::class)->purchase($event->order);
    }
}
