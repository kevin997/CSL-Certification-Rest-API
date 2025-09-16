<?php

namespace App\Console\Commands;

use App\Services\ChatArchival\MessageArchivalService;
use App\Models\Archival\ArchivalJob;
use Illuminate\Console\Command;
use Carbon\Carbon;

class ArchiveChatMessages extends Command
{
    protected $signature = 'chat:archive
                            {--course-id= : Specific course ID to archive}
                            {--cutoff-date= : Custom cutoff date (YYYY-MM-DD)}
                            {--dry-run : Show what would be archived without actually archiving}
                            {--force : Force archival even if another job is running}';

    protected $description = 'Archive old chat messages to S3 storage';

    public function __construct(
        private MessageArchivalService $archivalService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🗄️  Starting chat message archival process...');
        $this->newLine();

        try {
            // Parse options
            $courseId = $this->option('course-id');
            $cutoffDate = $this->option('cutoff-date')
                ? Carbon::parse($this->option('cutoff-date'))
                : null;
            $dryRun = $this->option('dry-run');
            $force = $this->option('force');

            if ($dryRun) {
                $this->warn('🔍 DRY RUN MODE - No actual archival will be performed');
                $this->newLine();
            }

            // Check for running jobs
            if (!$force && $courseId) {
                $runningJob = ArchivalJob::where('course_id', $courseId)
                    ->where('status', 'processing')
                    ->first();

                if ($runningJob) {
                    $this->error("❌ Archival job already running for course {$courseId}");
                    $this->error("Job ID: {$runningJob->id}");
                    $this->error("Started at: {$runningJob->started_at}");
                    $this->info("Use --force to override or wait for completion");
                    return self::FAILURE;
                }
            }

            // Show current configuration
            $this->displayConfiguration($cutoffDate);

            // Confirm if not forced
            if (!$force && !$this->confirm('Do you want to continue with the archival process?')) {
                $this->info('Archival cancelled by user');
                return self::SUCCESS;
            }

            $this->newLine();
            $startTime = now();

            if ($courseId) {
                // Archive specific course
                $stats = $this->archiveSpecificCourse($courseId, $cutoffDate, $dryRun);
            } else {
                // Archive all eligible courses
                $stats = $this->archiveAllCourses($dryRun);
            }

            $duration = $startTime->diffForHumans(now(), true);

            if ($dryRun) {
                $this->displayDryRunResults($stats, $duration);
            } else {
                $this->displayArchivalResults($stats, $duration);
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Archival failed: " . $e->getMessage());

            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    private function archiveSpecificCourse(?string $courseId, ?Carbon $cutoffDate, bool $dryRun): array
    {
        $this->info("📁 Processing course: {$courseId}");

        $actualCutoffDate = $cutoffDate ?? Carbon::now()->subDays(
            config('chat.archival.threshold_days', 90)
        );

        if ($dryRun) {
            // Simulate what would be archived
            return [
                'total_processed' => 1,
                'archived_messages' => 0, // Would need to fetch from API
                'failed_messages' => 0,
                'courses_processed' => 1,
                'storage_size_mb' => 0,
                'start_time' => now(),
                'end_time' => now(),
                'dry_run' => true
            ];
        }

        $courseStats = $this->archivalService->archiveCourseMessages($courseId, $actualCutoffDate);

        return [
            'total_processed' => $courseStats['processed'],
            'archived_messages' => $courseStats['archived'],
            'failed_messages' => $courseStats['failed'],
            'courses_processed' => 1,
            'storage_size_mb' => $courseStats['storage_size_mb'],
            'start_time' => now(),
            'end_time' => now()
        ];
    }

    private function archiveAllCourses(bool $dryRun): array
    {
        $this->info("📚 Processing all eligible courses...");

        if ($dryRun) {
            $this->warn("DRY RUN: Would normally process all courses needing archival");
            return [
                'total_processed' => 0,
                'archived_messages' => 0,
                'failed_messages' => 0,
                'courses_processed' => 0,
                'storage_size_mb' => 0,
                'start_time' => now(),
                'end_time' => now(),
                'dry_run' => true
            ];
        }

        return $this->archivalService->processArchival();
    }

    private function displayConfiguration(?Carbon $cutoffDate): void
    {
        $this->info('📋 Configuration:');
        $this->line("  • Archive threshold: " . ($cutoffDate ? $cutoffDate->toDateString() : 'Default (' . config('chat.archival.threshold_days', 90) . ' days)'));
        $this->line("  • Batch size: " . config('chat.archival.batch_size', 1000));
        $this->line("  • Storage: S3 (" . config('filesystems.disks.s3.bucket') . ")");

        if ($this->option('course-id')) {
            $this->line("  • Target course: " . $this->option('course-id'));
        } else {
            $this->line("  • Target: All eligible courses");
        }

        $this->newLine();
    }

    private function displayDryRunResults(array $stats, string $duration): void
    {
        $this->newLine();
        $this->info("🔍 DRY RUN RESULTS");
        $this->line("═══════════════════");

        $this->table(['Metric', 'Value'], [
            ['Duration', $duration],
            ['Courses to Process', $stats['courses_processed']],
            ['Messages to Archive', 'Would fetch from API'],
            ['Estimated Storage', 'Would calculate during archival'],
            ['Status', 'Simulation completed successfully']
        ]);

        $this->newLine();
        $this->info("🔸 No actual archival was performed");
        $this->info("🔸 Run without --dry-run to perform actual archival");
    }

    private function displayArchivalResults(array $stats, string $duration): void
    {
        $this->newLine();

        if ($stats['failed_messages'] > 0) {
            $this->warn("⚠️  ARCHIVAL COMPLETED WITH WARNINGS");
        } else {
            $this->info("✅ ARCHIVAL COMPLETED SUCCESSFULLY");
        }

        $this->line("═══════════════════════════════════");

        $tableData = [
            ['Duration', $duration],
            ['Courses Processed', number_format($stats['courses_processed'])],
            ['Total Messages Processed', number_format($stats['total_processed'])],
            ['Messages Archived', number_format($stats['archived_messages'])],
            ['Storage Used (MB)', number_format($stats['storage_size_mb'], 2)]
        ];

        if ($stats['failed_messages'] > 0) {
            $tableData[] = ['❌ Failed Messages', number_format($stats['failed_messages'])];
        }

        $this->table(['Metric', 'Value'], $tableData);

        // Show efficiency metrics
        $this->newLine();
        $this->info("📊 Performance Metrics:");

        if ($stats['total_processed'] > 0) {
            $successRate = (($stats['archived_messages'] / $stats['total_processed']) * 100);
            $this->line("  • Success Rate: " . number_format($successRate, 1) . "%");

            $messagesPerSecond = $stats['total_processed'] / max(1, $stats['start_time']->diffInSeconds($stats['end_time']));
            $this->line("  • Processing Speed: " . number_format($messagesPerSecond, 1) . " messages/second");
        }

        if ($stats['storage_size_mb'] > 0) {
            $compressionRatio = $stats['total_processed'] / max(1, $stats['storage_size_mb']);
            $this->line("  • Compression Ratio: " . number_format($compressionRatio, 1) . " messages/MB");
        }

        $this->newLine();

        if ($stats['failed_messages'] > 0) {
            $this->warn("⚠️  Some messages failed to archive. Check logs for details.");
        }

        $this->info("🎉 Archival process completed!");
    }
}