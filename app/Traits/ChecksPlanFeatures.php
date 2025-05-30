<?php

namespace App\Traits;

use App\Services\PlanFeatureService;
use Illuminate\Support\Facades\Auth;

trait ChecksPlanFeatures
{
    /**
     * Check if the current user has access to a specific feature
     *
     * @param string $feature
     * @return bool
     */
    protected function userHasFeature(string $feature): bool
    {
        $user = Auth::user();
        
        if (!$user) {
            return false;
        }
        
        $planFeatureService = app(PlanFeatureService::class);
        return $planFeatureService->hasFeature($user, $feature);
    }
    
    /**
     * Check if the current user is within the limits for a specific resource
     *
     * @param string $resource
     * @param int $currentCount
     * @return bool
     */
    protected function userWithinLimits(string $resource, int $currentCount): bool
    {
        $user = Auth::user();
        
        if (!$user) {
            return false;
        }
        
        $planFeatureService = app(PlanFeatureService::class);
        return $planFeatureService->withinLimits($user, $resource, $currentCount);
    }
    
    /**
     * Get the value of a specific feature for the current user
     *
     * @param string $feature
     * @return mixed
     */
    protected function getFeatureValue(string $feature)
    {
        $user = Auth::user();
        
        if (!$user) {
            return null;
        }
        
        $planFeatureService = app(PlanFeatureService::class);
        return $planFeatureService->getFeatureValue($user, $feature);
    }
    
    /**
     * Get the limit value for a specific resource for the current user
     *
     * @param string $resource
     * @return mixed
     */
    protected function getLimitValue(string $resource)
    {
        $user = Auth::user();
        
        if (!$user) {
            return null;
        }
        
        $planFeatureService = app(PlanFeatureService::class);
        return $planFeatureService->getLimitValue($user, $resource);
    }
}
