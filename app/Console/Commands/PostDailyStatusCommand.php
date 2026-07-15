<?php

namespace App\Console\Commands;

use App\Models\MarketingMessage;
use App\Services\WachapNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Posts the oldest pending WhatsApp-Status marketing message. Leaves the
 * message pending on failure so it retries on the next scheduled run.
 */
class PostDailyStatusCommand extends Command
{
    protected $signature = 'kursa:post-daily-status {--dry-run}';

    protected $description = 'Post the next pending marketing message to WhatsApp Status';

    public function handle(WachapNotificationService $wachap): int
    {
        if (! WachapNotificationService::isConfigured()) {
            $this->warn('Wachap is not configured — skipping daily status post.');

            return self::SUCCESS;
        }

        $message = MarketingMessage::nextFor(MarketingMessage::CHANNEL_STATUS);

        if (! $message) {
            $this->info('No pending status to post.');

            return self::SUCCESS;
        }

        if ($this->option('dry-run')) {
            $this->info("[dry-run] Would post status #{$message->id} ({$message->topic})");

            return self::SUCCESS;
        }

        try {
            $wachap->postStatus('text', $message->body, [
                'backgroundColor' => config('services.wachap.status_bg'),
                'font' => config('services.wachap.status_font'),
            ]);

            $message->forceFill(['status' => MarketingMessage::STATUS_SENT, 'sent_at' => now()])->save();

            $this->info("Posted status #{$message->id} ({$message->topic}).");
        } catch (\Throwable $e) {
            Log::error("PostDailyStatusCommand: failed to post message #{$message->id}: {$e->getMessage()}");
            $this->error("Failed to post status #{$message->id} — left pending for retry.");
        }

        return self::SUCCESS;
    }
}
