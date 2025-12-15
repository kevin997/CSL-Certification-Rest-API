<?php

namespace App\Console;

use App\Console\Commands\ArchiveChatMessages;
use App\Console\Commands\BuildChatSearchIndex;
use App\Console\Commands\SendProductSubscriptionReminders;
use App\Mail\ArchivalSummaryReport;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;
use Illuminate\Support\Facades\Mail;

class Kernel extends ConsoleKernel
{
    /**
     * The Artisan commands provided by your application.
     *
     * @var array
     */
    protected $commands = [
        ArchiveChatMessages::class,
        BuildChatSearchIndex::class,
        SendProductSubscriptionReminders::class,
    ];

    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Note: Sales database backup is scheduled in routes/console.php at 4:00 AM

        // Archive chat messages daily at 2:00 AM
        $schedule->command('chat:archive')
            ->dailyAt('02:00')
            ->withoutOverlapping(3600) // Prevent overlapping runs with 1-hour timeout
            ->runInBackground()
            ->emailOutputOnFailure(config('chat.archival.admin_email'))
            ->description('Archive old chat messages to S3 storage');

        // Build search index daily at 3:00 AM (after archival completes)
        $schedule->command('chat:build-search-index')
            ->dailyAt('03:00')
            ->withoutOverlapping(1800) // Prevent overlapping runs with 30-minute timeout
            ->runInBackground()
            ->emailOutputOnFailure(config('chat.archival.admin_email'))
            ->description('Build/update chat message search index');

        // Cleanup old search index entries weekly on Sunday at 4:00 AM
        $schedule->call(function () {
            \App\Models\Search\ChatSearchIndex::cleanupOldEntries(
                config('chat.search.cleanup_days', 365)
            );
        })->weeklyOn(0, '04:00')
            ->name('cleanup-old-search-index')
            ->withoutOverlapping()
            ->description('Cleanup old search index entries');

        // Update search performance metrics daily at 4:30 AM
        // TODO: Implement updatePerformanceMetrics() method in ChatSearchService
        // $schedule->call(function () {
        //     \App\Services\ChatSearch\ChatSearchService::updatePerformanceMetrics();
        // })->dailyAt('04:30')
        //   ->name('update-search-metrics')
        //   ->withoutOverlapping()
        //   ->description('Update search performance metrics');

        // Send archival summary report weekly on Monday at 9:00 AM
        $schedule->call(function () {
            $this->sendArchivalSummaryReport();
        })->weeklyOn(1, '09:00')
            ->name('archival-summary-report')
            ->environments(['production'])
            ->description('Send weekly archival summary report to administrators');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }

    /**
     * Send weekly archival summary report
     */
    private function sendArchivalSummaryReport(): void
    {
        try {
            $stats = \App\Models\Archival\ArchivalJob::getJobStats(7); // Last 7 days
            $storageStats = \App\Models\Archival\ArchivedChatMessage::selectRaw('
                COUNT(*) as total_archives,
                SUM(message_count) as total_archived_messages,
                SUM(storage_size_mb) as total_storage_mb
            ')->first();

            $searchStats = \App\Models\Search\ChatSearchIndex::getIndexStats();

            $adminEmail = "kevinliboire@gmail.com";
            if ($adminEmail && ($stats['total_jobs'] > 0 || $searchStats['total_indexed'] > 0)) {
                // Here you would send an email with the summary
                Mail::to($adminEmail)->send(new ArchivalSummaryReport($stats, $searchStats, $storageStats));

                \Illuminate\Support\Facades\Log::info('Archival summary report email sent', [
                    'admin_email' => $adminEmail,
                    'archival_jobs' => $stats['total_jobs'],
                    'archived_messages' => $storageStats->total_archived_messages ?? 0,
                    'storage_used_mb' => round($storageStats->total_storage_mb ?? 0, 2),
                    'indexed_messages' => $searchStats['total_indexed'],
                    'period' => 'Last 7 days'
                ]);
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Failed to generate archival summary report', [
                'error' => $e->getMessage()
            ]);
        }
    }
}
