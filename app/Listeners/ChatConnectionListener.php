<?php

namespace App\Listeners;

use App\Events\Chat\UserJoinedDiscussion;
use App\Events\Chat\UserLeftDiscussion;
use App\Services\Chat\ConnectionMonitoringService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class ChatConnectionListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Create the event listener.
     */
    public function __construct(
        private ConnectionMonitoringService $connectionMonitoring
    ) {}

    /**
     * Handle user joined discussion events.
     */
    public function handleUserJoined(UserJoinedDiscussion $event): void
    {
        $this->connectionMonitoring->trackConnection(
            (string) $event->user->id,
            $event->courseId
        );
    }

    /**
     * Handle user left discussion events.
     */
    public function handleUserLeft(UserLeftDiscussion $event): void
    {
        $this->connectionMonitoring->removeConnection(
            (string) $event->user->id,
            $event->courseId
        );
    }

    /**
     * Handle general events (fallback method).
     */
    public function handle(object $event): void
    {
        match (get_class($event)) {
            UserJoinedDiscussion::class => $this->handleUserJoined($event),
            UserLeftDiscussion::class => $this->handleUserLeft($event),
            default => null,
        };
    }
}
