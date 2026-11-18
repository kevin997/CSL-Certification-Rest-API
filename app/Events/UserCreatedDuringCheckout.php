<?php

namespace App\Events;

use App\Models\Environment;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class UserCreatedDuringCheckout
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  User  $user  The user that was created or found
     * @param  Environment  $environment  The environment the user is being added to
     * @param  bool  $isNewUser  Whether the user was newly created or already existed
     */
    public function __construct(
        public User $user,
        public Environment $environment,
        public bool $isNewUser = false,
        public bool $passwordSetupRequired = false
    ) {}

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('channel-name'),
        ];
    }
}
