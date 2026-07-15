<?php

namespace App\Console\Commands;

use App\Mail\MarketingCampaignMail;
use App\Models\MarketingMessage;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Sends the oldest pending email-campaign marketing message to opted-in
 * instructors (users owning at least one environment). Leaves the message
 * pending on failure so it retries on the next scheduled run.
 */
class SendEmailCampaignCommand extends Command
{
    protected $signature = 'kursa:send-email-campaign {--dry-run} {--limit=}';

    protected $description = 'Send the next pending email campaign to opted-in instructors';

    public function handle(): int
    {
        $message = MarketingMessage::nextFor(MarketingMessage::CHANNEL_EMAIL);

        if (! $message) {
            $this->info('No pending email campaign to send.');

            return self::SUCCESS;
        }

        $audience = User::query()
            ->whereHas('ownedEnvironments')
            ->whereNotNull('email_verified_at')
            ->where('marketing_opt_in', true)
            ->distinct();

        $limit = $this->option('limit') !== null ? max(0, (int) $this->option('limit')) : null;

        if ($this->option('dry-run')) {
            $count = $limit !== null ? min($limit, $audience->count()) : $audience->count();
            $this->info("[dry-run] Would send campaign #{$message->id} ({$message->email_subject}) to {$count} recipient(s).");

            return self::SUCCESS;
        }

        $emailHtml = str_replace('{{cta_url}}', (string) config('services.retention.links.panel'), (string) $message->email_html);

        $sent = 0;
        $remaining = $limit;

        try {
            $audience->chunkById(100, function ($users) use ($message, $emailHtml, &$sent, &$remaining) {
                foreach ($users as $user) {
                    if ($remaining !== null && $remaining <= 0) {
                        return false;
                    }

                    Mail::to($user->email)->queue(
                        new MarketingCampaignMail((string) $message->email_subject, $emailHtml, $user)
                    );

                    $sent++;

                    if ($remaining !== null) {
                        $remaining--;
                    }
                }
            });

            $source = (array) ($message->source ?? []);
            $source['recipients_count'] = $sent;

            $message->forceFill([
                'status' => MarketingMessage::STATUS_SENT,
                'sent_at' => now(),
                'source' => $source,
            ])->save();

            $this->info("Queued campaign #{$message->id} ({$message->email_subject}) for {$sent} recipient(s).");
        } catch (\Throwable $e) {
            Log::error("SendEmailCampaignCommand: failed to send campaign #{$message->id}: {$e->getMessage()}");
            $this->error("Failed to send campaign #{$message->id} — left pending for retry.");
        }

        return self::SUCCESS;
    }
}
