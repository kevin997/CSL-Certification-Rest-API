<?php

namespace App\Services\ChatArchival;

use App\Models\Archival\ArchivedChatMessage;
use App\Models\Archival\ArchivalJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class MessageArchivalService
{
    private const BATCH_SIZE = 1000;
    private const ARCHIVAL_THRESHOLD_DAYS = 90;

    public function processArchival(): array
    {
        Log::info("Starting chat message archival process");

        $cutoffDate = Carbon::now()->subDays(config('chat.archival.threshold_days', self::ARCHIVAL_THRESHOLD_DAYS));

        $stats = [
            'total_processed' => 0,
            'archived_messages' => 0,
            'failed_messages' => 0,
            'courses_processed' => 0,
            'storage_size_mb' => 0,
            'start_time' => now(),
            'end_time' => null
        ];

        try {
            $coursesToArchive = $this->getCoursesNeedingArchival($cutoffDate);
            $stats['courses_processed'] = count($coursesToArchive);

            foreach ($coursesToArchive as $courseData) {
                $courseStats = $this->archiveCourseMessages(
                    $courseData['course_id'],
                    $cutoffDate
                );

                $stats['total_processed'] += $courseStats['processed'];
                $stats['archived_messages'] += $courseStats['archived'];
                $stats['failed_messages'] += $courseStats['failed'];
                $stats['storage_size_mb'] += $courseStats['storage_size_mb'];
            }

        } catch (\Exception $e) {
            Log::error("Archival process failed", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        } finally {
            $stats['end_time'] = now();
        }

        Log::info("Chat message archival completed", $stats);
        return $stats;
    }

    public function archiveCourseMessages(string $courseId, Carbon $cutoffDate): array
    {
        $stats = ['processed' => 0, 'archived' => 0, 'failed' => 0, 'storage_size_mb' => 0];

        $job = ArchivalJob::create([
            'course_id' => $courseId,
            'cutoff_date' => $cutoffDate,
            'status' => 'processing',
            'started_at' => now()
        ]);

        try {
            $messages = $this->fetchMessagesForArchival($courseId, $cutoffDate);
            $stats['processed'] = count($messages);

            if (empty($messages)) {
                $job->update(['status' => 'completed', 'completed_at' => now()]);
                return $stats;
            }

            // Process messages in batches
            $batches = array_chunk($messages, self::BATCH_SIZE);

            foreach ($batches as $batchIndex => $batch) {
                $batchResult = $this->processBatch($courseId, $batch, $batchIndex);

                $stats['archived'] += $batchResult['archived'];
                $stats['failed'] += $batchResult['failed'];
                $stats['storage_size_mb'] += $batchResult['storage_size_mb'];

                // Update job progress
                $progress = (($batchIndex + 1) / count($batches)) * 100;
                $job->update(['progress' => round($progress)]);
            }

            // Clean up archived messages from main system
            if ($stats['archived'] > 0) {
                $this->cleanupArchivedMessages($courseId, $cutoffDate);
            }

            $job->update([
                'status' => 'completed',
                'completed_at' => now(),
                'messages_archived' => $stats['archived'],
                'storage_size_mb' => $stats['storage_size_mb']
            ]);

        } catch (\Exception $e) {
            $job->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'completed_at' => now()
            ]);
            throw $e;
        }

        return $stats;
    }

    public function restoreArchivedMessages(string $courseId, Carbon $startDate, Carbon $endDate): array
    {
        $archiveFiles = $this->getArchiveFilesForDateRange($courseId, $startDate, $endDate);
        $restoredMessages = [];

        foreach ($archiveFiles as $file) {
            $content = Storage::disk('s3')->get($file['path']);
            $messages = json_decode($content, true);

            if ($messages && isset($messages['messages'])) {
                foreach ($messages['messages'] as $message) {
                    $messageDate = Carbon::parse($message['created_at']);
                    if ($messageDate->between($startDate, $endDate)) {
                        $restoredMessages[] = $message;
                    }
                }
            }
        }

        return $restoredMessages;
    }

    public function getArchivalStatus(string $courseId): array
    {
        $latestJob = ArchivalJob::where('course_id', $courseId)
            ->orderBy('started_at', 'desc')
            ->first();

        $archiveStats = ArchivedChatMessage::where('course_id', $courseId)
            ->selectRaw('
                COUNT(*) as total_archives,
                SUM(message_count) as total_archived_messages,
                SUM(storage_size_mb) as total_storage_mb,
                MIN(start_date) as earliest_archive,
                MAX(end_date) as latest_archive
            ')
            ->first();

        return [
            'course_id' => $courseId,
            'latest_job' => $latestJob ? [
                'id' => $latestJob->id,
                'status' => $latestJob->status,
                'progress' => $latestJob->progress,
                'messages_archived' => $latestJob->messages_archived,
                'storage_size_mb' => $latestJob->storage_size_mb,
                'started_at' => $latestJob->started_at,
                'completed_at' => $latestJob->completed_at,
                'error_message' => $latestJob->error_message
            ] : null,
            'archive_summary' => [
                'total_archives' => $archiveStats->total_archives ?? 0,
                'total_archived_messages' => $archiveStats->total_archived_messages ?? 0,
                'total_storage_mb' => round($archiveStats->total_storage_mb ?? 0, 2),
                'earliest_archive' => $archiveStats->earliest_archive,
                'latest_archive' => $archiveStats->latest_archive,
                'last_updated' => now()
            ]
        ];
    }

    private function fetchMessagesForArchival(string $courseId, Carbon $cutoffDate): array
    {
        $response = Http::withToken(config('chat.main_api.token'))
            ->timeout(120) // 2 minute timeout for large datasets
            ->get(config('chat.main_api.url') . "/api/v1/chat/archival/messages", [
                'course_id' => $courseId,
                'cutoff_date' => $cutoffDate->toISOString(),
                'include_metadata' => true,
                'batch_size' => self::BATCH_SIZE
            ]);

        if ($response->failed()) {
            throw new \Exception("Failed to fetch messages for archival: " . $response->body());
        }

        return $response->json('messages', []);
    }

    private function processBatch(string $courseId, array $messages, int $batchIndex): array
    {
        $archived = 0;
        $failed = 0;
        $storageSizeMb = 0;

        // Prepare archive data with metadata
        $archiveData = [
            'course_id' => $courseId,
            'batch_index' => $batchIndex,
            'archived_at' => now()->toISOString(),
            'message_count' => count($messages),
            'archival_version' => '1.0',
            'compression' => 'none',
            'checksum' => md5(json_encode($messages)),
            'messages' => $messages
        ];

        // Generate archive file path with date partitioning
        $archiveDate = now()->format('Y/m/d');
        $timestamp = now()->format('His');
        $fileName = "chat-archive/{$archiveDate}/{$courseId}/batch-{$batchIndex}-{$timestamp}.json";

        try {
            // Store to S3 with metadata
            $content = json_encode($archiveData, JSON_PRETTY_PRINT);
            $storageSizeMb = strlen($content) / (1024 * 1024); // Convert to MB

            $success = Storage::disk('s3')->put($fileName, $content, [
                'visibility' => 'private',
                'metadata' => [
                    'course_id' => $courseId,
                    'batch_index' => (string)$batchIndex,
                    'message_count' => (string)count($messages),
                    'archived_date' => now()->toDateString(),
                    'service_version' => 'chat-archival-v1.0'
                ]
            ]);

            if (!$success) {
                throw new \Exception("Failed to store archive file to S3");
            }

            // Verify upload
            if (!Storage::disk('s3')->exists($fileName)) {
                throw new \Exception("Archive file verification failed - file not found in S3");
            }

            // Record archive entry in database
            $archiveEntry = ArchivedChatMessage::create([
                'course_id' => $courseId,
                'environment_id' => $messages[0]['environment_id'] ?? null,
                'archive_path' => $fileName,
                'message_count' => count($messages),
                'storage_size_mb' => $storageSizeMb,
                'archived_date' => now(),
                'start_date' => Carbon::parse($messages[0]['created_at']),
                'end_date' => Carbon::parse(end($messages)['created_at']),
                'checksum' => $archiveData['checksum'],
                'batch_index' => $batchIndex
            ]);

            $archived = count($messages);

            Log::info("Archived message batch successfully", [
                'course_id' => $courseId,
                'batch_index' => $batchIndex,
                'message_count' => count($messages),
                'storage_size_mb' => round($storageSizeMb, 3),
                'archive_id' => $archiveEntry->id,
                's3_path' => $fileName
            ]);

        } catch (\Exception $e) {
            $failed = count($messages);

            Log::error("Failed to archive message batch", [
                'course_id' => $courseId,
                'batch_index' => $batchIndex,
                'message_count' => count($messages),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Cleanup partial files
            try {
                if (Storage::disk('s3')->exists($fileName)) {
                    Storage::disk('s3')->delete($fileName);
                }
            } catch (\Exception $cleanupError) {
                Log::warning("Failed to cleanup partial archive file", [
                    'file' => $fileName,
                    'error' => $cleanupError->getMessage()
                ]);
            }
        }

        return [
            'archived' => $archived,
            'failed' => $failed,
            'storage_size_mb' => $storageSizeMb
        ];
    }

    private function getCoursesNeedingArchival(Carbon $cutoffDate): array
    {
        $response = Http::withToken(config('chat.main_api.token'))
            ->timeout(60)
            ->get(config('chat.main_api.url') . "/api/v1/chat/archival/courses-needing-archival", [
                'cutoff_date' => $cutoffDate->toISOString(),
                'min_messages' => config('chat.archival.min_messages_threshold', 100)
            ]);

        if ($response->failed()) {
            throw new \Exception("Failed to fetch courses needing archival: " . $response->body());
        }

        return $response->json('courses', []);
    }

    private function cleanupArchivedMessages(string $courseId, Carbon $cutoffDate): void
    {
        try {
            $response = Http::withToken(config('chat.main_api.token'))
                ->timeout(60)
                ->delete(config('chat.main_api.url') . "/api/v1/chat/archival/cleanup", [
                    'course_id' => $courseId,
                    'cutoff_date' => $cutoffDate->toISOString(),
                    'verify_archived' => true
                ]);

            if ($response->failed()) {
                Log::warning("Failed to cleanup archived messages from main system", [
                    'course_id' => $courseId,
                    'cutoff_date' => $cutoffDate->toISOString(),
                    'response_status' => $response->status(),
                    'response_body' => $response->body()
                ]);
            } else {
                Log::info("Successfully cleaned up archived messages from main system", [
                    'course_id' => $courseId,
                    'deleted_count' => $response->json('deleted_count', 0)
                ]);
            }
        } catch (\Exception $e) {
            Log::error("Exception during cleanup of archived messages", [
                'course_id' => $courseId,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function getArchiveFilesForDateRange(string $courseId, Carbon $startDate, Carbon $endDate): array
    {
        return ArchivedChatMessage::where('course_id', $courseId)
            ->where(function($query) use ($startDate, $endDate) {
                $query->whereBetween('start_date', [$startDate, $endDate])
                      ->orWhereBetween('end_date', [$startDate, $endDate])
                      ->orWhere(function($q) use ($startDate, $endDate) {
                          $q->where('start_date', '<=', $startDate)
                            ->where('end_date', '>=', $endDate);
                      });
            })
            ->orderBy('start_date')
            ->get(['archive_path', 'checksum', 'message_count'])
            ->map(fn($record) => [
                'path' => $record->archive_path,
                'checksum' => $record->checksum,
                'message_count' => $record->message_count
            ])
            ->toArray();
    }
}