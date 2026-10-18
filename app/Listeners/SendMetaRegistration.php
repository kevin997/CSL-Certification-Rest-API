<?php

namespace App\Listeners;

use App\Events\SalesFormSubmitted;
use App\Services\MetaConversionsService;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendMetaRegistration implements ShouldQueue
{
    public function handle(SalesFormSubmitted $event): void
    {
        app(MetaConversionsService::class)->registration($event->submission);
    }
}
