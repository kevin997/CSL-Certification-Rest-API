<?php

namespace App\Events\Chat;

use App\Models\DiscussionMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public DiscussionMessage $message,
        public string $courseId
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PresenceChannel("course.{$this->courseId}.discussion"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'message-sent';
    }

    public function broadcastWith(): array
    {
        return [
            'message' => $this->message->load('user'),
            'timestamp' => now()->toISOString(),
        ];
    }
}