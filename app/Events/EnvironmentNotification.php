<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class EnvironmentNotification implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * The environment ID this notification targets.
     */
    public int $environmentId;

    /**
     * Optional target user ID within the environment.
     */
    public ?int $userId;

    /**
     * A short type identifier for client-side handling (e.g. info, warning, invoice.updated)
     */
    public string $type;

    /**
     * Short title for the notification.
     */
    public string $title;

    /**
     * Message body.
     */
    public string $message;

    /**
     * Arbitrary extra data payload to send to the client.
     * @var array<string, mixed>
     */
    public array $data;

    /**
     * Create a new event instance.
     *
     * @param int $environmentId
     * @param string $type
     * @param string $title
     * @param string $message
     * @param array<string, mixed> $data
     * @param int|null $userId
     */
    public function __construct(
        int $environmentId,
        string $type,
        string $title,
        string $message,
        array $data = [],
        ?int $userId = null,
    ) {
        $this->environmentId = $environmentId;
        $this->type = $type;
        $this->title = $title;
        $this->message = $message;
        $this->data = $data;
        $this->userId = $userId;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        if ($this->userId !== null) {
            return [new PrivateChannel("env.{$this->environmentId}.users.{$this->userId}")];
        }

        return [new PrivateChannel("env.{$this->environmentId}.notifications")];
    }

    /**
     * Customize the event name on the client.
     */
    public function broadcastAs(): string
    {
        return 'EnvironmentNotification';
    }

    /**
     * Data payload for the broadcast.
     *
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'environment_id' => $this->environmentId,
            'user_id' => $this->userId,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'data' => $this->data,
        ];
    }
}
