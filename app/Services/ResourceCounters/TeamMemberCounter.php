<?php

namespace App\Services\ResourceCounters;

use App\Models\User;
use App\Models\TeamMember;
use App\Models\EnvironmentUser;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Request;

class TeamMemberCounter implements ResourceCounterInterface
{
    /**
     * Get the current count of team members for a user
     *
     * @param User $user
     * @param string $resource
     * @return int
     */
    public function getCount(User $user, string $resource): int
    {
        // Get the current environment ID from the request
        $environmentId = Request::input('environment_id') ?? session('current_environment_id');
        
        if (!$environmentId) {
            return 0; // No environment context available
        }
        
        // Cache the count for 5 minutes to improve performance
        $cacheKey = "user_{$user->id}_env_{$environmentId}_team_member_count";
        
        return Cache::remember($cacheKey, 300, function () use ($user, $environmentId) {
            // Get the environment user relationship
            $environmentUser = EnvironmentUser::where('user_id', $user->id)
                ->where('environment_id', $environmentId)
                ->first();
                
            if (!$environmentUser) {
                return 0; // User is not part of this environment
            }
            
            // Find the team associated with this environment user
            $teamMember = TeamMember::where('environment_user_id', $environmentUser->id)->first();
            
            if (!$teamMember) {
                return 0; // No team associated with this environment user
            }
            
            // Count team members for this team
            return TeamMember::where('team_id', $teamMember->team_id)
                ->where('environment_id', $environmentId)
                ->count();
        });
    }
}
