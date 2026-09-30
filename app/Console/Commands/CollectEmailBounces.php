<?php

namespace App\Console\Commands;

use App\Models\EmailSuppression;
use App\Support\Mail\BounceMailbox;
use App\Support\Mail\DeliveryStatusReport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Reads bounce reports from the no-reply mailbox and blocks every address that
 * hard-bounced. DropSuppressedRecipients then removes it from all later mail.
 */
class CollectEmailBounces extends Command
{
    protected $signature = 'mail:collect-bounces {--dry-run : List what would be blocked without blocking or moving anything}';

    protected $description = 'Block email addresses that hard-bounced, read from the bounce mailbox';

    public function handle(BounceMailbox $mailbox): int
    {
        if (blank(config('mail.bounce_mailbox.username')) || blank(config('mail.bounce_mailbox.password'))) {
            $this->warn('No bounce mailbox credentials are configured — skipping.');

            return self::SUCCESS;
        }

        $dryRun = (bool) $this->option('dry-run');
        $suppressed = 0;
        $filed = $mailbox->process(function (string $raw) use (&$suppressed, $dryRun): bool {
            if (! DeliveryStatusReport::isDeliveryStatusReport($raw)) {
                return false;
            }

            foreach (DeliveryStatusReport::hardBounces($raw) as $bounce) {
                if ($dryRun) {
                    $this->line("Would block {$bounce->email} ({$bounce->statusCode})");

                    continue;
                }

                $suppression = EmailSuppression::recordHardBounce($bounce->email, $bounce->statusCode, $bounce->diagnostic, 'bounce_mailbox', now());
                $suppressed += $suppression->wasRecentlyCreated ? 1 : 0;
            }

            return ! $dryRun;
        });

        if ($dryRun) {
            $this->comment('Dry run — nothing blocked or moved.');

            return self::SUCCESS;
        }

        Log::info('mail.bounces_collected', ['reports' => $filed, 'newly_suppressed' => $suppressed]);
        $this->info("Read {$filed} bounce report(s); blocked {$suppressed} new address(es).");

        return self::SUCCESS;
    }
}
