<?php

namespace App\Services\ResourceCounters;

use App\Models\User;

interface ResourceCounterInterface
{
    /**
     * Get the current count of a resource for a user
     *
     * @param User $user
     * @param string $resource
     * @return int
     */
    public function getCount(User $user, string $resource): int;
}
