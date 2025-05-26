<?php

namespace App\Events;

use App\Models\Environment;
use App\Models\User;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserCreatedDuringCheckout
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     * 
     * @param \App\Models\User $user The user that was created or found
     * @param \App\Models\Environment $environment The environment the user is being added to
     * @param bool $isNewUser Whether the user was newly created or already existed
     */
    public function __construct(
        public User $user,
        public Environment $environment,
        public bool $isNewUser = false
    ) {}


    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new \Illuminate\Broadcasting\PrivateChannel('channel-name'),
        ];
    }
}
