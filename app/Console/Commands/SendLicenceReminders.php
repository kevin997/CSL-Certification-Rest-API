<?php

namespace App\Console\Commands;

use App\Mail\Licensing\LicenceRenewalWarningMail;
use App\Mail\Licensing\TrialReminderMail;
use App\Models\EnvironmentLicence;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * KURSA licensing (Phase 4). Daily lifecycle reminders (doc §5):
 *  - trial days 0, 7, 12, 14 (owner-facing countdown)
 *  - day-17 recovery message after a lapsed trial
 *  - renewal/grace warnings while a paid licence is past-due or in grace
 *
 * Each reminder is recorded in the licence `reminders_sent` JSON array so it is
 * sent at most once (no duplicates).
 */
class SendLicenceReminders extends Command
{
    protected $signature = 'licences:send-reminders';

    protected $description = 'Send environment-licence trial and renewal reminders (deduplicated).';

    /** Trial day markers to fire, keyed by whole days elapsed since trial start. */
    private const TRIAL_DAYS = [0, 7, 12, 14];

    public function handle(): int
    {
        $sent = 0;

        $sent += $this->sendTrialReminders();
        $sent += $this->sendRecoveryReminders();
        $sent += $this->sendRenewalWarnings();

        $this->info("Licence reminders sent: {$sent}.");

        return self::SUCCESS;
    }

    private function sendTrialReminders(): int
    {
        $count = 0;
        $trialDays = (int) config('licensing.trial_days', 14);

        EnvironmentLicence::query()
            ->with('environment')
            ->where('status', EnvironmentLicence::STATUS_TRIALING)
            ->whereNotNull('trial_ends_at')
            ->get()
            ->each(function (EnvironmentLicence $licence) use (&$count, $trialDays) {
                $start = $licence->trial_ends_at->copy()->subDays($trialDays);
                $daysElapsed = (int) floor($start->diffInDays(now(), false));

                foreach (self::TRIAL_DAYS as $threshold) {
                    if ($daysElapsed >= $threshold) {
                        $marker = "trial_day_{$threshold}";
                        if ($this->fireOnce($licence, $marker, fn () => new TrialReminderMail($licence, $marker))) {
                            $count++;
                        }
                    }
                }
            });

        return $count;
    }

    private function sendRecoveryReminders(): int
    {
        $count = 0;

        EnvironmentLicence::query()
            ->with('environment')
            ->where('plan_type', EnvironmentLicence::PLAN_FREE)
            ->whereNotNull('trial_used_at')
            ->where('trial_used_at', '<=', now()->subDays(17))
            ->get()
            ->each(function (EnvironmentLicence $licence) use (&$count) {
                if ($this->fireOnce($licence, 'trial_day_17', fn () => new TrialReminderMail($licence, 'trial_day_17'))) {
                    $count++;
                }
            });

        return $count;
    }

    private function sendRenewalWarnings(): int
    {
        $count = 0;

        EnvironmentLicence::query()
            ->with('environment')
            ->whereIn('status', [
                EnvironmentLicence::STATUS_PAST_DUE,
                EnvironmentLicence::STATUS_GRACE,
            ])
            ->get()
            ->each(function (EnvironmentLicence $licence) use (&$count) {
                if ($this->fireOnce($licence, 'renewal_warning', fn () => new LicenceRenewalWarningMail($licence))) {
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Send a reminder at most once, recording the marker in reminders_sent.
     */
    private function fireOnce(EnvironmentLicence $licence, string $marker, callable $mailableFactory): bool
    {
        $sentMarkers = (array) ($licence->reminders_sent ?? []);
        if (in_array($marker, $sentMarkers, true)) {
            return false;
        }

        $recipient = optional($licence->environment?->owner)->email
            ?? optional($licence->environment)->primary_domain;

        if (! $recipient || ! filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            // No deliverable address (e.g. domain only) — record so we don't retry forever.
            $sentMarkers[] = $marker;
            $licence->reminders_sent = $sentMarkers;
            $licence->saveQuietly();

            return false;
        }

        try {
            Mail::to($recipient)->send($mailableFactory());
        } catch (\Throwable $e) {
            Log::warning("Licence reminder '{$marker}' failed for env {$licence->environment_id}: " . $e->getMessage());

            return false;
        }

        $sentMarkers[] = $marker;
        $licence->reminders_sent = $sentMarkers;
        $licence->saveQuietly();

        return true;
    }
}
