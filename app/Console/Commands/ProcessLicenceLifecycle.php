<?php

namespace App\Console\Commands;

use App\Models\EnvironmentLicence;
use App\Services\Licensing\LicenceService;
use Illuminate\Console\Command;

/**
 * KURSA licensing (Phase 4). Drives environment-licence lifecycle transitions
 * (doc §5, §12). Runs every 15 minutes.
 *
 *  - Trialing past trial_ends_at              → downgrade to Free
 *  - cancel_at_period_end past ends_at         → downgrade to Free
 *  - Active paid past ends_at                  → past-due + 7-day grace window
 *  - past-due / grace past grace_ends_at       → downgrade to Free
 *
 * Customer data is never touched — only the licence row transitions.
 */
class ProcessLicenceLifecycle extends Command
{
    protected $signature = 'licences:process-lifecycle';

    protected $description = 'Process environment-licence lifecycle transitions (trial/paid expiry, grace, downgrade).';

    public function handle(LicenceService $service): int
    {
        $now = now();
        $downgraded = 0;
        $pastDue = 0;

        // 1) Trial expired → Free Forever.
        EnvironmentLicence::query()
            ->where('status', EnvironmentLicence::STATUS_TRIALING)
            ->whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<=', $now)
            ->get()
            ->each(function (EnvironmentLicence $licence) use ($service, &$downgraded) {
                $service->downgradeToFree($licence);
                $downgraded++;
            });

        // 2) Cancelled at period end and the period has ended. A scheduled
        //    White Label → Creator change applies the Creator plan (past-due,
        //    payment required — doc §10); a plain cancel goes to Free Forever.
        $scheduled = 0;
        EnvironmentLicence::query()
            ->where('status', EnvironmentLicence::STATUS_CANCEL_AT_PERIOD_END)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $now)
            ->get()
            ->each(function (EnvironmentLicence $licence) use ($service, &$downgraded, &$scheduled) {
                if ($licence->pending_plan_type === EnvironmentLicence::PLAN_CREATOR) {
                    $service->applyScheduledCreatorChange($licence);
                    $scheduled++;
                } else {
                    $service->downgradeToFree($licence);
                    $downgraded++;
                }
            });

        // 3) Active paid licence lapsed (renewal not paid) → past-due + grace window.
        EnvironmentLicence::query()
            ->whereIn('status', [
                EnvironmentLicence::STATUS_CREATOR_ACTIVE,
                EnvironmentLicence::STATUS_WHITE_LABEL_ACTIVE,
            ])
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $now)
            ->get()
            ->each(function (EnvironmentLicence $licence) use ($service, &$pastDue) {
                $service->markPastDue($licence);
                $pastDue++;
            });

        // 4) Grace window elapsed → Free Forever.
        EnvironmentLicence::query()
            ->whereIn('status', [
                EnvironmentLicence::STATUS_PAST_DUE,
                EnvironmentLicence::STATUS_GRACE,
            ])
            ->whereNotNull('grace_ends_at')
            ->where('grace_ends_at', '<=', $now)
            ->get()
            ->each(function (EnvironmentLicence $licence) use ($service, &$downgraded) {
                $service->downgradeToFree($licence);
                $downgraded++;
            });

        $this->info("Licence lifecycle processed: {$pastDue} moved to past-due, {$scheduled} scheduled Creator changes applied, {$downgraded} downgraded to Free.");

        return self::SUCCESS;
    }
}
