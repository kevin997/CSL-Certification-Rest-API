<?php

namespace App\Console\Commands;

use App\Services\ChatSearch\ChatSearchService;
use App\Models\Search\ChatSearchIndex;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BuildChatSearchIndex extends Command
{
    protected $signature = 'chat:build-search-index
                            {--course-id= : Specific course ID to index}
                            {--rebuild : Rebuild existing index entries}
                            {--dry-run : Show what would be indexed without actually building}
                            {--batch-size=1000 : Number of messages to process in each batch}
                            {--force : Force index building even if another job is running}';

    protected $description = 'Build or rebuild chat message search index';

    public function __construct(
        private ChatSearchService $searchService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('🔍 Starting chat search index building process...');
        $this->newLine();

        try {
            // Parse options
            $courseId = $this->option('course-id');
            $rebuild = $this->option('rebuild');
            $dryRun = $this->option('dry-run');
            $batchSize = (int) $this->option('batch-size');
            $force = $this->option('force');

            if ($dryRun) {
                $this->warn('🔍 DRY RUN MODE - No actual indexing will be performed');
                $this->newLine();
            }

            // Check for running index build processes
            if (!$force && $courseId) {
                $lockKey = "search_index_build:{$courseId}";
                $runningBuild = cache()->get($lockKey);

                if ($runningBuild) {
                    $this->error("❌ Index build already running for course {$courseId}");
                    $this->error("Started by user: {$runningBuild}");
                    $this->info("Use --force to override or wait for completion");
                    return self::FAILURE;
                }
            }

            // Show current configuration
            $this->displayConfiguration($courseId, $rebuild, $batchSize);

            // Confirm if not forced
            if (!$force && !$this->confirm('Do you want to continue with the index building process?')) {
                $this->info('Index building cancelled by user');
                return self::SUCCESS;
            }

            $this->newLine();
            $startTime = now();

            if ($courseId) {
                // Build index for specific course
                $stats = $this->buildCourseIndex($courseId, $rebuild, $dryRun, $batchSize);
            } else {
                // Build index for all courses
                $stats = $this->buildAllIndexes($rebuild, $dryRun, $batchSize);
            }

            $duration = $startTime->diffForHumans(now(), true);

            if ($dryRun) {
                $this->displayDryRunResults($stats, $duration);
            } else {
                $this->displayIndexBuildResults($stats, $duration);
            }

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Index building failed: " . $e->getMessage());

            if ($this->option('verbose')) {
                $this->error($e->getTraceAsString());
            }

            return self::FAILURE;
        }
    }

    private function buildCourseIndex(string $courseId, bool $rebuild, bool $dryRun, int $batchSize): array
    {
        $this->info("📁 Processing course: {$courseId}");

        if ($rebuild) {
            $this->warn("🔄 Rebuilding existing index entries");
        }

        if ($dryRun) {
            // Get current index stats for preview
            $currentStats = ChatSearchIndex::getIndexStats($courseId);

            return [
                'courses_processed' => 1,
                'messages_indexed' => 0, // Would fetch from API
                'index_size_mb' => 0,
                'processing_time_seconds' => 0,
                'batches_processed' => 0,
                'existing_entries' => $currentStats['total_indexed'],
                'dry_run' => true
            ];
        }

        // Acquire lock
        $lockKey = "search_index_build:{$courseId}";
        cache()->put($lockKey, 'console_command', 3600); // 1 hour lock

        try {
            if ($rebuild) {
                ChatSearchIndex::rebuildCourseIndex($courseId);
            }

            $stats = $this->searchService->buildSearchIndex($courseId, $batchSize);

            return [
                'courses_processed' => 1,
                'messages_indexed' => $stats['indexed_count'],
                'index_size_mb' => $stats['index_size_mb'],
                'processing_time_seconds' => $stats['processing_time_seconds'],
                'batches_processed' => $stats['batches_processed'],
                'existing_entries' => $stats['skipped_existing'] ?? 0,
                'dry_run' => false
            ];

        } finally {
            // Always release the lock
            cache()->forget($lockKey);
        }
    }

    private function buildAllIndexes(bool $rebuild, bool $dryRun, int $batchSize): array
    {
        $this->info("📚 Processing all courses...");

        if ($dryRun) {
            $currentStats = ChatSearchIndex::getIndexStats();

            $this->warn("DRY RUN: Would normally process all courses needing indexing");
            return [
                'courses_processed' => 0,
                'messages_indexed' => 0,
                'index_size_mb' => 0,
                'processing_time_seconds' => 0,
                'batches_processed' => 0,
                'existing_entries' => $currentStats['total_indexed'],
                'dry_run' => true
            ];
        }

        // For all courses, we would typically get a list of courses from the main API
        // and process each one. For now, return summary stats
        $totalStats = [
            'courses_processed' => 0,
            'messages_indexed' => 0,
            'index_size_mb' => 0,
            'processing_time_seconds' => 0,
            'batches_processed' => 0,
            'existing_entries' => 0
        ];

        // This would be implemented to iterate through all courses
        // and call buildCourseIndex for each one
        $this->warn("⚠️ Bulk index building not yet implemented");
        $this->info("💡 Use --course-id to build index for specific courses");

        return $totalStats;
    }

    private function displayConfiguration(string $courseId = null, bool $rebuild = false, int $batchSize = 1000): void
    {
        $this->info('📋 Configuration:');
        $this->line("  • Batch size: {$batchSize} messages");
        $this->line("  • Operation: " . ($rebuild ? 'Rebuild (replace existing)' : 'Build (skip existing)'));

        if ($courseId) {
            $this->line("  • Target course: {$courseId}");
        } else {
            $this->line("  • Target: All courses");
        }

        // Show current index statistics
        $currentStats = ChatSearchIndex::getIndexStats($courseId);
        $this->line("  • Current index size: " . number_format($currentStats['total_indexed']) . " messages");
        $this->line("  • Index coverage: " . number_format($currentStats['content_size_mb'], 2) . " MB");

        if ($currentStats['last_indexed']) {
            $this->line("  • Last indexed: " . Carbon::parse($currentStats['last_indexed'])->diffForHumans());
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
            ['Current Index Size', number_format($stats['existing_entries']) . ' messages'],
            ['Messages to Index', 'Would fetch from API'],
            ['Estimated Storage', 'Would calculate during indexing'],
            ['Status', 'Simulation completed successfully']
        ]);

        $this->newLine();
        $this->info("🔸 No actual indexing was performed");
        $this->info("🔸 Run without --dry-run to perform actual index building");
    }

    private function displayIndexBuildResults(array $stats, string $duration): void
    {
        $this->newLine();
        $this->info("✅ INDEX BUILD COMPLETED");
        $this->line("═══════════════════════════");

        $tableData = [
            ['Duration', $duration],
            ['Courses Processed', number_format($stats['courses_processed'])],
            ['Messages Indexed', number_format($stats['messages_indexed'])],
            ['Index Size (MB)', number_format($stats['index_size_mb'], 2)],
            ['Batches Processed', number_format($stats['batches_processed'])]
        ];

        if ($stats['existing_entries'] > 0) {
            $tableData[] = ['Existing Entries Skipped', number_format($stats['existing_entries'])];
        }

        $this->table(['Metric', 'Value'], $tableData);

        // Show performance metrics
        $this->newLine();
        $this->info("📊 Performance Metrics:");

        if ($stats['messages_indexed'] > 0) {
            $messagesPerSecond = $stats['messages_indexed'] / max(1, $stats['processing_time_seconds']);
            $this->line("  • Indexing Speed: " . number_format($messagesPerSecond, 1) . " messages/second");

            $compressionRatio = $stats['messages_indexed'] / max(1, $stats['index_size_mb']);
            $this->line("  • Index Density: " . number_format($compressionRatio, 1) . " messages/MB");

            if ($stats['batches_processed'] > 0) {
                $messagesPerBatch = $stats['messages_indexed'] / $stats['batches_processed'];
                $this->line("  • Average Batch Size: " . number_format($messagesPerBatch, 1) . " messages/batch");
            }
        }

        $this->newLine();

        // Show index health status
        $currentStats = ChatSearchIndex::getIndexStats();
        $healthStatus = $currentStats['total_indexed'] > 0 ? 'Healthy' : 'Empty';
        $this->info("🏥 Index Health: {$healthStatus}");

        if ($currentStats['total_indexed'] > 0) {
            $this->line("  • Total indexed messages: " . number_format($currentStats['total_indexed']));
            $this->line("  • Indexed courses: " . number_format($currentStats['indexed_courses']));

            if ($currentStats['last_indexed']) {
                $this->line("  • Last update: " . Carbon::parse($currentStats['last_indexed'])->diffForHumans());
            }
        }

        $this->newLine();
        $this->info("🎉 Index building completed successfully!");
    }
}