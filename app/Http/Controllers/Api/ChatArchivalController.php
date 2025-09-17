<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChatArchival\MessageArchivalService;
use App\Models\Archival\ArchivalJob;
use App\Models\Archival\ArchivedChatMessage;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ChatArchivalController extends Controller implements HasMiddleware
{
    public function __construct(
        private MessageArchivalService $archivalService
    ) {}

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            'auth:sanctum',
        ];
    }

    /**
     * Get archival status for a course
     */
    public function getArchivalStatus(Request $request, string $courseId): JsonResponse
    {
        try {
            // Validate user has access to this course
            $this->validateCourseAccess($courseId);

            $status = $this->archivalService->getArchivalStatus($courseId);

            return response()->json([
                'data' => $status,
                'meta' => [
                    'course_id' => $courseId,
                    'requested_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to get archival status", [
                'course_id' => $courseId,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Failed to retrieve archival status',
                'error' => 'ARCHIVAL_STATUS_FAILED'
            ], 500);
        }
    }

    /**
     * Trigger archival process for a specific course
     */
    public function triggerArchival(Request $request, string $courseId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'cutoff_date' => 'nullable|date|before:today',
            'force' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Validate user has admin access to this course
            $this->validateCourseAdminAccess($courseId);

            // Check if there's already a running archival job for this course
            $runningJob = ArchivalJob::where('course_id', $courseId)
                ->where('status', 'processing')
                ->first();

            if ($runningJob && !$request->boolean('force')) {
                return response()->json([
                    'message' => 'Archival job already in progress for this course',
                    'data' => [
                        'job_id' => $runningJob->id,
                        'progress' => $runningJob->progress,
                        'started_at' => $runningJob->started_at
                    ]
                ], 409);
            }

            $cutoffDate = $request->cutoff_date
                ? Carbon::parse($request->cutoff_date)
                : Carbon::now()->subDays(config('chat.archival.threshold_days', 90));

            // Start archival process
            $stats = $this->archivalService->archiveCourseMessages($courseId, $cutoffDate);

            Log::info("Manual archival triggered", [
                'course_id' => $courseId,
                'cutoff_date' => $cutoffDate->toISOString(),
                'triggered_by' => auth()->id(),
                'stats' => $stats
            ]);

            return response()->json([
                'message' => 'Archival process completed successfully',
                'data' => [
                    'course_id' => $courseId,
                    'cutoff_date' => $cutoffDate->toISOString(),
                    'stats' => $stats
                ],
                'meta' => [
                    'triggered_at' => now()->toISOString(),
                    'triggered_by' => auth()->id()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Manual archival failed", [
                'course_id' => $courseId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Archival process failed',
                'error' => 'ARCHIVAL_FAILED',
                'details' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Restore archived messages for a date range
     */
    public function restoreMessages(Request $request, string $courseId): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'preview' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Validate user has access to this course
            $this->validateCourseAccess($courseId);

            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);

            // Validate date range (max 1 year)
            if ($startDate->diffInDays($endDate) > 365) {
                return response()->json([
                    'message' => 'Date range cannot exceed 365 days',
                    'error' => 'DATE_RANGE_TOO_LARGE'
                ], 400);
            }

            if ($request->boolean('preview')) {
                // Just show what archives would be affected
                $archiveFiles = ArchivedChatMessage::where('course_id', $courseId)
                    ->dateRange($startDate, $endDate)
                    ->get(['id', 'archive_path', 'message_count', 'start_date', 'end_date', 'storage_size_mb']);

                return response()->json([
                    'message' => 'Archive preview retrieved',
                    'data' => [
                        'affected_archives' => $archiveFiles->map(fn($archive) => [
                            'archive_id' => $archive->id,
                            'message_count' => $archive->message_count,
                            'date_range' => [
                                'start' => $archive->start_date->toISOString(),
                                'end' => $archive->end_date->toISOString()
                            ],
                            'storage_size_mb' => $archive->storage_size_mb
                        ]),
                        'total_archives' => $archiveFiles->count(),
                        'total_messages' => $archiveFiles->sum('message_count'),
                        'total_storage_mb' => round($archiveFiles->sum('storage_size_mb'), 2)
                    ],
                    'meta' => [
                        'course_id' => $courseId,
                        'requested_range' => [
                            'start_date' => $startDate->toISOString(),
                            'end_date' => $endDate->toISOString()
                        ],
                        'preview_only' => true
                    ]
                ]);
            }

            // Restore messages from archives
            $restoredMessages = $this->archivalService->restoreArchivedMessages($courseId, $startDate, $endDate);

            Log::info("Messages restored from archive", [
                'course_id' => $courseId,
                'date_range' => [$startDate->toISOString(), $endDate->toISOString()],
                'message_count' => count($restoredMessages),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Messages restored successfully',
                'data' => [
                    'restored_messages' => $restoredMessages,
                    'total_restored' => count($restoredMessages)
                ],
                'meta' => [
                    'course_id' => $courseId,
                    'date_range' => [
                        'start_date' => $startDate->toISOString(),
                        'end_date' => $endDate->toISOString()
                    ],
                    'restored_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Message restoration failed", [
                'course_id' => $courseId,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Message restoration failed',
                'error' => 'RESTORATION_FAILED'
            ], 500);
        }
    }

    /**
     * Get archival statistics
     */
    public function getArchivalStatistics(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'course_id' => 'nullable|uuid',
            'days' => 'integer|min:1|max:365'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $courseId = $request->course_id;
            $days = $request->integer('days', 30);

            // Validate course access if specified
            if ($courseId) {
                $this->validateCourseAccess($courseId);
            }

            // Get job statistics
            $jobStats = ArchivalJob::getJobStats($days);

            // Get storage statistics
            $storageStats = ArchivedChatMessage::when($courseId, fn($q) => $q->where('course_id', $courseId))
                ->selectRaw('
                    COUNT(*) as total_archives,
                    SUM(message_count) as total_archived_messages,
                    SUM(storage_size_mb) as total_storage_mb,
                    COUNT(DISTINCT course_id) as archived_courses,
                    MIN(start_date) as earliest_archive,
                    MAX(end_date) as latest_archive
                ')
                ->first();

            return response()->json([
                'data' => [
                    'job_statistics' => $jobStats,
                    'storage_statistics' => [
                        'total_archives' => $storageStats->total_archives ?? 0,
                        'total_archived_messages' => $storageStats->total_archived_messages ?? 0,
                        'total_storage_mb' => round($storageStats->total_storage_mb ?? 0, 2),
                        'archived_courses' => $storageStats->archived_courses ?? 0,
                        'earliest_archive' => $storageStats->earliest_archive,
                        'latest_archive' => $storageStats->latest_archive
                    ]
                ],
                'meta' => [
                    'course_id' => $courseId,
                    'period_days' => $days,
                    'generated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to get archival statistics", [
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Failed to retrieve archival statistics',
                'error' => 'STATISTICS_FAILED'
            ], 500);
        }
    }

    /**
     * Cancel a running archival job
     */
    public function cancelArchivalJob(Request $request, string $jobId): JsonResponse
    {
        try {
            $job = ArchivalJob::findOrFail($jobId);

            // Validate user has admin access to the course
            $this->validateCourseAdminAccess($job->course_id);

            if (!$job->isInProgress()) {
                return response()->json([
                    'message' => 'Archival job is not in progress and cannot be cancelled',
                    'data' => [
                        'job_id' => $jobId,
                        'current_status' => $job->status
                    ]
                ], 400);
            }

            // Update job status to failed with cancellation message
            $job->update([
                'status' => 'failed',
                'error_message' => 'Job cancelled by user: ' . auth()->id(),
                'completed_at' => now()
            ]);

            Log::info("Archival job cancelled", [
                'job_id' => $jobId,
                'course_id' => $job->course_id,
                'cancelled_by' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Archival job cancelled successfully',
                'data' => [
                    'job_id' => $jobId,
                    'status' => $job->status,
                    'cancelled_at' => $job->completed_at
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Failed to cancel archival job", [
                'job_id' => $jobId,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Failed to cancel archival job',
                'error' => 'CANCELLATION_FAILED'
            ], 500);
        }
    }

    /**
     * Validate user has access to course
     */
    private function validateCourseAccess(string $courseId): void
    {
        // Implementation would check if user has access to this course
        // This could be through enrollment, instructor role, or admin permissions
        // For now, we'll assume authentication is sufficient
    }

    /**
     * Validate user has admin access to course
     */
    private function validateCourseAdminAccess(string $courseId): void
    {
        // Implementation would check if user has admin/instructor access to this course
        // For now, we'll assume authentication is sufficient
    }
}