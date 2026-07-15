<?php

namespace App\Console\Commands;

use App\Models\MarketingMessage;
use App\Services\WachapNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Sends the oldest pending group-tip marketing message to the KURSA support
 * WhatsApp group. Leaves the message pending on failure so it retries on the
 * next scheduled run.
 */
class BroadcastGroupTipCommand extends Command
{
    protected $signature = 'kursa:broadcast-group-tip {--dry-run}';

    protected $description = 'Broadcast the next pending group tip to the KURSA WhatsApp support group';

    public function handle(WachapNotificationService $wachap): int
    {
        if (! WachapNotificationService::isConfigured()) {
            $this->warn('Wachap is not configured — skipping group tip broadcast.');

            return self::SUCCESS;
        }

        $message = MarketingMessage::nextFor(MarketingMessage::CHANNEL_GROUP_TIP);

        if (! $message) {
            $this->info('No pending group tip to broadcast.');

            return self::SUCCESS;
        }

        $jid = config('services.wachap.groups.support');

        if (blank($jid)) {
            $this->warn('services.wachap.groups.support is not configured — skipping group tip broadcast.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("[dry-run] Would broadcast group tip #{$message->id} ({$message->topic}) to {$jid}");

            return self::SUCCESS;
        }

        try {
            $wachap->sendGroupMessage($jid, $message->body);

            $message->forceFill(['status' => MarketingMessage::STATUS_SENT, 'sent_at' => now()])->save();

            $this->info("Broadcast group tip #{$message->id} ({$message->topic}).");
        } catch (\Throwable $e) {
            Log::error("BroadcastGroupTipCommand: failed to send message #{$message->id}: {$e->getMessage()}");
            $this->error("Failed to broadcast group tip #{$message->id} — left pending for retry.");
        }

        return self::SUCCESS;
    }
}
