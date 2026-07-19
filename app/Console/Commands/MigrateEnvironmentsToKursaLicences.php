<?php

namespace App\Console\Commands;

use App\Models\Environment;
use App\Models\EnvironmentLicence;
use App\Models\Plan;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * KURSA licensing transition — Phase 8 launch migration (doc §13 Phase 8).
 *
 * A one-off, idempotent, manually-run command that moves every pre-existing
 * Environment onto the new EnvironmentLicence model, derived from its most
 * recent legacy `subscriptions` row (if any). Environments that already carry
 * an EnvironmentLicence are left untouched — safe to re-run.
 *
 * Mapping (doc §13 Phase 8):
 *  - No subscription, or plan type standalone/personal_free, or a demo
 *    subscription whose trial window has already elapsed/is absent (and not
 *    actively trialing) → free_forever / free_active (starts_at = now,
 *    ends_at = null).
 *  - A demo subscription that is trial/active AND still inside its trial
 *    window (or has no dates at all but status = trial, treated as an active
 *    demo) → white_label_annual / trialing, trial_ends_at = now + 14 days,
 *    trial_used_at = now.
 *  - supported / personal_plus / personal_pro / business / individual_teacher /
 *    business_legacy, subscription status = active → white_label_annual /
 *    white_label_active. ends_at is derived, in priority order:
 *      1. next_payment_at, if it is in the future
 *      2. last_payment_at + one billing-cycle period, if that lands in the
 *         future
 *      3. now + 1 year, flagged WARN for manual review
 *  - The same legacy types with status canceled/expired/pending →
 *    free_forever / free_active.
 *
 * Every migrated licence's price_snapshot records
 * {migrated_from, migrated_at, derivation} — how the row was derived.
 *
 * --dry-run computes and prints the exact same report but writes nothing
 * (the whole run happens inside a transaction that is rolled back).
 */
class MigrateEnvironmentsToKursaLicences extends Command
{
    protected $signature = 'licences:migrate-environments
        {--dry-run : Compute and print the migration without writing anything}
        {--environment-ids= : Comma-separated list of environment IDs to restrict the run to}
        {--deactivate-legacy-plans : Set is_active=false on all legacy plan rows (ignored on --dry-run)}';

    protected $description = 'One-off migration of pre-existing environments onto EnvironmentLicence rows (KURSA Phase 8).';

    /**
     * Legacy plan `type` codes predating the KURSA catalogue (doc §13 Phase 8).
     */
    private const LEGACY_PLAN_TYPES = [
        'demo',
        'standalone',
        'supported',
        'personal_free',
        'personal_plus',
        'personal_pro',
        'business',
        'individual_teacher',
        'business_legacy',
    ];

    /** Legacy types that map straight to Free Forever regardless of status. */
    private const FREE_TYPES = ['standalone', 'personal_free'];

    /** Legacy paid types whose ACTIVE subscriptions map to White Label. */
    private const PAID_LEGACY_TYPES = [
        'supported',
        'personal_plus',
        'personal_pro',
        'business',
        'individual_teacher',
        'business_legacy',
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $deactivateLegacyPlans = (bool) $this->option('deactivate-legacy-plans');

        $environmentIdsOption = $this->option('environment-ids');
        $environmentIds = $environmentIdsOption
            ? array_values(array_filter(array_map('trim', explode(',', $environmentIdsOption)), fn ($v) => $v !== ''))
            : null;

        $counts = [
            'free_forever' => 0,
            'white_label_trialing' => 0,
            'white_label_active' => 0,
            'skipped_already_licenced' => 0,
        ];
        $warnings = [];
        $rows = [];

        DB::beginTransaction();

        try {
            $query = Environment::query();

            if ($environmentIds) {
                $query->whereIn('id', $environmentIds);
            }

            foreach ($query->get() as $environment) {
                $this->migrateEnvironment($environment, $counts, $warnings, $rows);
            }

            if ($deactivateLegacyPlans && ! $dryRun) {
                Plan::whereIn('type', self::LEGACY_PLAN_TYPES)->update(['is_active' => false]);
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        $this->renderReport($rows, $counts, $warnings, $dryRun);

        return self::SUCCESS;
    }

    private function migrateEnvironment(Environment $environment, array &$counts, array &$warnings, array &$rows): void
    {
        if (EnvironmentLicence::where('environment_id', $environment->id)->exists()) {
            $counts['skipped_already_licenced']++;
            $rows[] = [
                'id' => $environment->id,
                'name' => $environment->name,
                'from' => 'n/a',
                'to' => 'already licenced — skipped',
                'ends_at' => 'n/a',
                'flag' => '',
            ];

            return;
        }

        $subscription = Subscription::where('environment_id', $environment->id)
            ->orderByDesc('created_at')
            ->first();

        $legacyType = $subscription?->plan?->type;
        $now = now();

        $decision = $this->decide($subscription, $legacyType, $now);

        $licence = new EnvironmentLicence();
        $licence->environment_id = $environment->id;
        $licence->plan_type = $decision['plan_type'];
        $licence->plan_id = Plan::where('type', $decision['plan_type'])->value('id');
        $licence->status = $decision['status'];
        $licence->starts_at = $decision['starts_at'];
        $licence->ends_at = $decision['ends_at'];
        $licence->trial_ends_at = $decision['trial_ends_at'];
        $licence->trial_used_at = $decision['trial_used_at'];
        $licence->cancel_at_period_end = false;
        $licence->grace_ends_at = null;
        $licence->price_snapshot = [
            'migrated_from' => $legacyType ?? 'none',
            'migrated_at' => $now->toIso8601String(),
            'derivation' => $decision['derivation'],
        ];
        $licence->save();

        if ($decision['plan_type'] === EnvironmentLicence::PLAN_FREE) {
            $counts['free_forever']++;
        } elseif ($decision['status'] === EnvironmentLicence::STATUS_TRIALING) {
            $counts['white_label_trialing']++;
        } else {
            $counts['white_label_active']++;
        }

        if ($decision['warn']) {
            $warnings[] = "Environment #{$environment->id} ({$environment->name}): {$decision['derivation']}";
        }

        $rows[] = [
            'id' => $environment->id,
            'name' => $environment->name,
            'from' => ($legacyType ?? 'none') . ' / ' . ($subscription?->status ?? 'n/a'),
            'to' => $decision['plan_type'] . ' / ' . $decision['status'],
            'ends_at' => optional($decision['ends_at'])->toDateString() ?? optional($decision['trial_ends_at'])->toDateString() ?? 'null',
            'flag' => $decision['warn'] ? 'WARN' : '',
        ];
    }

    /**
     * @return array{plan_type:string,status:string,starts_at:Carbon,ends_at:?Carbon,trial_ends_at:?Carbon,trial_used_at:?Carbon,derivation:string,warn:bool}
     */
    private function decide(?Subscription $subscription, ?string $legacyType, Carbon $now): array
    {
        // No subscription at all → Free Forever.
        if (! $subscription) {
            return $this->freeDecision('no subscription found');
        }

        // standalone / personal_free → always Free Forever, regardless of status.
        if (in_array($legacyType, self::FREE_TYPES, true)) {
            return $this->freeDecision("legacy plan type '{$legacyType}' maps directly to free_forever");
        }

        if ($legacyType === 'demo') {
            return $this->decideDemo($subscription, $now);
        }

        if (in_array($legacyType, self::PAID_LEGACY_TYPES, true)) {
            return $this->decidePaidLegacy($subscription, $legacyType, $now);
        }

        // Unrecognised/unknown/missing plan type — safe default is Free
        // Forever, flagged for manual review.
        return $this->freeDecision(
            "unrecognised legacy plan type '" . ($legacyType ?? 'null') . "' — defaulted to free_forever",
            warn: true
        );
    }

    private function decideDemo(Subscription $subscription, Carbon $now): array
    {
        $status = $subscription->status;

        if (in_array($status, [Subscription::STATUS_CANCELED, Subscription::STATUS_EXPIRED], true)) {
            return $this->freeDecision("demo subscription status '{$status}' — trial over");
        }

        if (in_array($status, [Subscription::STATUS_TRIAL, Subscription::STATUS_ACTIVE], true)) {
            $trialEndsAt = $subscription->trial_ends_at;
            $endsAt = $subscription->ends_at;
            $hasFutureWindow = ($trialEndsAt && $trialEndsAt->isFuture()) || ($endsAt && $endsAt->isFuture());
            $noDatesAtAll = ! $trialEndsAt && ! $endsAt;

            if ($hasFutureWindow || ($noDatesAtAll && $status === Subscription::STATUS_TRIAL)) {
                return [
                    'plan_type' => EnvironmentLicence::PLAN_WHITE_LABEL,
                    'status' => EnvironmentLicence::STATUS_TRIALING,
                    'starts_at' => $now,
                    'ends_at' => null,
                    'trial_ends_at' => $now->copy()->addDays(14),
                    'trial_used_at' => $now,
                    'derivation' => "demo subscription status '{$status}' with an active/no trial window — started a fresh 14-day White Label trial",
                    'warn' => false,
                ];
            }

            return $this->freeDecision("demo subscription status '{$status}' but trial window elapsed/absent");
        }

        // pending or any other status — safe default is Free Forever.
        return $this->freeDecision("demo subscription status '{$status}' not trial/active/canceled/expired — defaulted to free_forever");
    }

    private function decidePaidLegacy(Subscription $subscription, string $legacyType, Carbon $now): array
    {
        $status = $subscription->status;

        if ($status !== Subscription::STATUS_ACTIVE) {
            return $this->freeDecision("legacy plan '{$legacyType}' subscription status '{$status}' — not active");
        }

        [$endsAt, $derivation, $warn] = $this->deriveEndsAt($subscription, $now);

        return [
            'plan_type' => EnvironmentLicence::PLAN_WHITE_LABEL,
            'status' => EnvironmentLicence::STATUS_WHITE_LABEL_ACTIVE,
            'starts_at' => $subscription->starts_at ?? $now,
            'ends_at' => $endsAt,
            'trial_ends_at' => null,
            'trial_used_at' => null,
            'derivation' => "legacy plan '{$legacyType}' active — {$derivation}",
            'warn' => $warn,
        ];
    }

    /**
     * @return array{0:Carbon,1:string,2:bool}
     */
    private function deriveEndsAt(Subscription $subscription, Carbon $now): array
    {
        if ($subscription->next_payment_at && $subscription->next_payment_at->isFuture()) {
            return [$subscription->next_payment_at->copy(), 'ends_at = next_payment_at (future)', false];
        }

        if ($subscription->last_payment_at) {
            $period = $subscription->billing_cycle === 'annual' ? 'addYear' : 'addMonth';
            $candidate = $subscription->last_payment_at->copy()->{$period}();

            if ($candidate->isFuture()) {
                return [$candidate, "ends_at = last_payment_at + 1 {$subscription->billing_cycle} period (future)", false];
            }
        }

        return [
            $now->copy()->addYear(),
            'ends_at = now + 1 year — no derivable period end, flagged for manual review',
            true,
        ];
    }

    private function freeDecision(string $derivation, bool $warn = false): array
    {
        $now = now();

        return [
            'plan_type' => EnvironmentLicence::PLAN_FREE,
            'status' => EnvironmentLicence::STATUS_FREE_ACTIVE,
            'starts_at' => $now,
            'ends_at' => null,
            'trial_ends_at' => null,
            'trial_used_at' => null,
            'derivation' => $derivation,
            'warn' => $warn,
        ];
    }

    private function renderReport(array $rows, array $counts, array $warnings, bool $dryRun): void
    {
        if ($dryRun) {
            $this->warn('DRY RUN — no changes were written.');
        }

        $this->table(
            ['Env ID', 'Name', 'Legacy plan/status', 'New plan/status', 'Ends at', 'Flag'],
            array_map(fn ($r) => [$r['id'], $r['name'], $r['from'], $r['to'], $r['ends_at'], $r['flag']], $rows)
        );

        $this->info('Summary:');
        $this->line("  free_forever / free_active:              {$counts['free_forever']}");
        $this->line("  white_label_annual / trialing:            {$counts['white_label_trialing']}");
        $this->line("  white_label_annual / white_label_active:  {$counts['white_label_active']}");
        $this->line("  already licenced (skipped):               {$counts['skipped_already_licenced']}");

        if (count($warnings)) {
            $this->warn('WARN — flagged for manual review:');
            foreach ($warnings as $warning) {
                $this->line("  - {$warning}");
            }
        }
    }
}
