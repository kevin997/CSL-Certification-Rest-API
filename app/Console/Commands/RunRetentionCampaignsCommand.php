<?php

namespace App\Console\Commands;

use App\Jobs\SendRetentionMessagesJob;
use App\Services\RetentionCampaignService;
use App\Services\WachapNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * The daily retention engine. Evaluates every scenario, builds a de-duplicated,
 * cooldown-aware send list (one message per recipient), and dispatches the
 * sends to the queue in throttled batches.
 *
 *   php artisan kursa:retention-campaigns            # send
 *   php artisan kursa:retention-campaigns --dry-run  # preview, send nothing
 *
 * Ported from shopikat's RunRetentionCampaignsCommand. Unlike shopikat (which
 * aborts entirely when WhatsApp/Wachap isn't configured, since that's its
 * only channel), KURSA always has the email fallback available, so an
 * unconfigured Wachap only downgrades every send to email-only rather than
 * blocking the run.
 */
class RunRetentionCampaignsCommand extends Command
{
    protected $signature = 'kursa:retention-campaigns {--dry-run : Show what would be sent without sending} {--chunk=100 : Recipients per queued batch}';

    protected $description = 'Send behaviour-based retention nudges (WhatsApp, falling back to email) to instructors and learners.';

    public function handle(RetentionCampaignService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && ! WachapNotificationService::isConfigured()) {
            $this->warn('WhatsApp (Wachap) is not configured — every send will fall back to email.');
        }

        $sendList = $service->buildSendList();

        if ($sendList === []) {
            $this->info('No recipients matched any retention scenario today.');

            return self::SUCCESS;
        }

        // Per-scenario breakdown for the operator log / dry-run preview.
        $byScenario = collect($sendList)->countBy('scenario_key')->sortDesc();

        $this->info(sprintf('%d retention message(s) across %d scenario(s):', count($sendList), $byScenario->count()));
        foreach ($byScenario as $scenario => $count) {
            $this->line(sprintf('  %-26s %d', $scenario, $count));
        }

        if ($dryRun) {
            $this->comment('Dry run — nothing was sent.');

            return self::SUCCESS;
        }

        $chunkSize = max(1, (int) $this->option('chunk'));
        $batches = 0;

        foreach (array_chunk($sendList, $chunkSize) as $chunk) {
            SendRetentionMessagesJob::dispatch($chunk);
            $batches++;
        }

        Log::info('Retention engine dispatched', [
            'messages' => count($sendList),
            'batches' => $batches,
            'by_scenario' => $byScenario->all(),
        ]);

        $this->info(sprintf('Queued %d message(s) in %d batch(es).', count($sendList), $batches));

        return self::SUCCESS;
    }
}
