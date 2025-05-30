<?php

namespace App\Services\ResourceCounters;

use App\Models\User;
use App\Models\Template;
use Illuminate\Support\Facades\Cache;

class CourseCounter implements ResourceCounterInterface
{
    /**
     * Get the current count of courses for a user
     *
     * @param User $user
     * @param string $resource
     * @return int
     */
    public function getCount(User $user, string $resource): int
    {
        // Cache the count for 5 minutes to improve performance
        return Cache::remember("user_{$user->id}_course_count", 300, function () use ($user) {
            return Template::where('user_id', $user->id)->count();
        });
    }
}
