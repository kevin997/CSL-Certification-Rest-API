<?php

namespace App\Services;

use App\Models\User;
use App\Models\Plan;
use Illuminate\Support\Facades\Cache;

class PlanFeatureService
{
    /**
     * Check if a user has access to a specific feature
     *
     * @param User $user
     * @param string $feature
     * @return bool
     */
    public function hasFeature(User $user, string $feature): bool
    {
        // Get the user's current plan
        $plan = $this->getUserPlan($user);
        
        if (!$plan) {
            return false;
        }
        
        // Get features from the plan
        $features = json_decode($plan->features, true) ?? [];
        
        // Check if the feature exists and is enabled
        if (isset($features[$feature])) {
            $value = $features[$feature];
            
            // If the value is boolean, return it directly
            if (is_bool($value)) {
                return $value;
            }
            
            // If the value is "Unlimited" or a numeric value > 0, the feature is available
            if ($value === 'Unlimited' || (is_numeric($value) && $value > 0)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check if a user is within the limits for a specific resource
     *
     * @param User $user
     * @param string $resource
     * @param int $currentCount
     * @return bool
     */
    public function withinLimits(User $user, string $resource, int $currentCount): bool
    {
        // Get the user's current plan
        $plan = $this->getUserPlan($user);
        
        if (!$plan) {
            return false;
        }
        
        // Get limits from the plan
        $limits = json_decode($plan->limits, true) ?? [];
        
        // Check if the resource has a limit
        if (isset($limits[$resource])) {
            $limit = $limits[$resource];
            
            // If the limit is "Unlimited", return true
            if ($limit === 'Unlimited') {
                return true;
            }
            
            // Check if the current count is within the limit
            return is_numeric($limit) && $currentCount < $limit;
        }
        
        // If the resource isn't specified in the limits, default to false
        return false;
    }
    
    /**
     * Get the user's current plan
     *
     * @param User $user
     * @return Plan|null
     */
    public function getUserPlan(User $user): ?Plan
    {
        // Cache the plan to avoid repeated database queries
        return Cache::remember('user_plan_' . $user->id, 60 * 60, function () use ($user) {
            // Get the user's active subscription and related plan
            $subscription = $user->activeSubscription;
            
            if (!$subscription) {
                // If no active subscription, return the free plan
                return Plan::where('type', 'personal_free')
                    ->where('is_active', true)
                    ->first();
            }
            
            return $subscription->plan;
        });
    }
    
    /**
     * Get the value of a specific feature for a user
     *
     * @param User $user
     * @param string $feature
     * @return mixed
     */
    public function getFeatureValue(User $user, string $feature)
    {
        // Get the user's current plan
        $plan = $this->getUserPlan($user);
        
        if (!$plan) {
            return null;
        }
        
        // Get features from the plan
        $features = json_decode($plan->features, true) ?? [];
        
        // Return the feature value if it exists
        return $features[$feature] ?? null;
    }
    
    /**
     * Get the limit value for a specific resource
     *
     * @param User $user
     * @param string $resource
     * @return mixed
     */
    public function getLimitValue(User $user, string $resource)
    {
        // Get the user's current plan
        $plan = $this->getUserPlan($user);
        
        if (!$plan) {
            return null;
        }
        
        // Get limits from the plan
        $limits = json_decode($plan->limits, true) ?? [];
        
        // Return the limit value if it exists
        return $limits[$resource] ?? null;
    }
    
    /**
     * Clear the cached plan for a user
     *
     * @param User $user
     * @return void
     */
    public function clearPlanCache(User $user): void
    {
        Cache::forget('user_plan_' . $user->id);
    }
}
