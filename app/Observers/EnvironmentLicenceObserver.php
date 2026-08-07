<?php

namespace App\Observers;

use App\Models\EnvironmentLicence;
use App\Services\Licensing\EntitlementService;

/**
 * Invalidates the cached entitlement resolution whenever an environment's
 * licence changes, so EntitlementService never serves stale limits/features.
 */
class EnvironmentLicenceObserver
{
    public function saved(EnvironmentLicence $licence): void
    {
        EntitlementService::forgetCache((int) $licence->environment_id);
    }

    public function deleted(EnvironmentLicence $licence): void
    {
        EntitlementService::forgetCache((int) $licence->environment_id);
    }
}
