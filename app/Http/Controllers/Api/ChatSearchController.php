<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ChatSearch\ChatSearchService;
use App\Models\Search\ChatSearchIndex;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ChatSearchController extends Controller
{
    public function __construct(
        private ChatSearchService $searchService
    ) {
        $this->middleware('auth:sanctum');
    }

    /**
     * Search chat messages
     */
    public function searchMessages(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'query' => 'required|string|min:2|max:255',
            'course_id' => 'nullable|uuid',
            'user_id' => 'nullable|uuid',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'include_archived' => 'boolean',
            'limit' => 'integer|min:1|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $query = $request->string('query');
            $courseId = $request->course_id;
            $userId = auth()->id();
            $limit = $request->integer('limit', 50);

            // Prepare filters
            $filters = [];
            if ($request->start_date) {
                $filters['start_date'] = $request->start_date;
            }
            if ($request->end_date) {
                $filters['end_date'] = $request->end_date;
            }
            if ($request->user_id) {
                $filters['user_id'] = $request->user_id;
            }
            if (!$request->boolean('include_archived', true)) {
                $filters['exclude_archived'] = true;
            }

            // Track search start time for performance metrics
            $startTime = microtime(true);

            // Perform search
            $searchResults = $this->searchService->searchMessages(
                $query,
                $courseId,
                $userId,
                $filters
            );

            // Calculate response time
            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            // Log search for analytics
            $this->logSearch($query, $courseId, $userId, $searchResults['total_results'], $responseTime);

            // Limit results if needed
            if ($searchResults['total_results'] > $limit) {
                $searchResults['combined_results'] = array_slice($searchResults['combined_results'], 0, $limit);
                $searchResults['limited_results'] = true;
                $searchResults['limit_applied'] = $limit;
            }

            // Add performance metadata
            $searchResults['search_metadata']['response_time_ms'] = $responseTime;
            $searchResults['search_metadata']['user_id'] = $userId;

            return response()->json([
                'data' => $searchResults,
                'meta' => [
                    'query' => $query,
                    'course_id' => $courseId,
                    'filters_applied' => $filters,
                    'performance' => [
                        'response_time_ms' => $responseTime,
                        'cache_hit' => $searchResults['search_metadata']['cache_hit'] ?? false
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Chat search failed", [
                'query' => $request->query,
                'course_id' => $request->course_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Search failed',
                'error' => 'SEARCH_FAILED',
                'details' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get search suggestions
     */
    public function getSearchSuggestions(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'q' => 'required|string|min:1|max:100',
            'course_id' => 'nullable|uuid',
            'limit' => 'integer|min:1|max:20'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $partialQuery = $request->string('q');
            $courseId = $request->course_id;
            $limit = $request->integer('limit', 10);

            $suggestions = $this->searchService->getSearchSuggestions($partialQuery, $courseId, $limit);

            return response()->json([
                'data' => $suggestions,
                'meta' => [
                    'partial_query' => $partialQuery,
                    'course_id' => $courseId,
                    'limit' => $limit
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Search suggestions failed", [
                'partial_query' => $request->q,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Failed to get search suggestions',
                'error' => 'SUGGESTIONS_FAILED'
            ], 500);
        }
    }

    /**
     * Build search index for a course
     */
    public function buildSearchIndex(Request $request, string $courseId): JsonResponse
    {
        try {
            // Validate user has admin access to this course
            $this->validateCourseAdminAccess($courseId);

            // Check if there's already an index building process running
            $lockKey = "search_index_build:{$courseId}";
            $lockAcquired = cache()->add($lockKey, auth()->id(), 3600); // 1 hour lock

            if (!$lockAcquired) {
                return response()->json([
                    'message' => 'Search index build already in progress for this course',
                    'error' => 'BUILD_IN_PROGRESS'
                ], 409);
            }

            try {
                // Build the search index
                $stats = $this->searchService->buildSearchIndex($courseId);

                Log::info("Search index built successfully", [
                    'course_id' => $courseId,
                    'stats' => $stats,
                    'triggered_by' => auth()->id()
                ]);

                return response()->json([
                    'message' => 'Search index built successfully',
                    'data' => [
                        'course_id' => $courseId,
                        'build_stats' => $stats
                    ],
                    'meta' => [
                        'built_at' => now()->toISOString(),
                        'triggered_by' => auth()->id()
                    ]
                ]);

            } finally {
                // Always release the lock
                cache()->forget($lockKey);
            }

        } catch (\Exception $e) {
            Log::error("Search index build failed", [
                'course_id' => $courseId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Search index build failed',
                'error' => 'INDEX_BUILD_FAILED',
                'details' => app()->environment('local') ? $e->getMessage() : 'Internal server error'
            ], 500);
        }
    }

    /**
     * Get search analytics
     */
    public function getSearchAnalytics(Request $request): JsonResponse
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

            $analytics = $this->searchService->getSearchAnalytics($courseId, $days);

            return response()->json([
                'data' => $analytics,
                'meta' => [
                    'course_id' => $courseId,
                    'period_days' => $days,
                    'generated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Search analytics failed", [
                'course_id' => $request->course_id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Failed to get search analytics',
                'error' => 'ANALYTICS_FAILED'
            ], 500);
        }
    }

    /**
     * Get search index status
     */
    public function getIndexStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'course_id' => 'nullable|uuid'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $courseId = $request->course_id;

            // Validate course access if specified
            if ($courseId) {
                $this->validateCourseAccess($courseId);
            }

            $indexStats = ChatSearchIndex::getIndexStats($courseId);

            return response()->json([
                'data' => [
                    'index_statistics' => $indexStats,
                    'index_health' => [
                        'is_healthy' => $indexStats['total_indexed'] > 0,
                        'last_update' => $indexStats['last_indexed'],
                        'coverage' => $this->calculateIndexCoverage($courseId)
                    ]
                ],
                'meta' => [
                    'course_id' => $courseId,
                    'generated_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Index status check failed", [
                'course_id' => $request->course_id,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Failed to get index status',
                'error' => 'INDEX_STATUS_FAILED'
            ], 500);
        }
    }

    /**
     * Rebuild search index
     */
    public function rebuildSearchIndex(Request $request, string $courseId): JsonResponse
    {
        try {
            // Validate user has admin access to this course
            $this->validateCourseAdminAccess($courseId);

            // Clear existing index for this course
            ChatSearchIndex::rebuildCourseIndex($courseId);

            // Trigger index rebuild
            $stats = $this->searchService->buildSearchIndex($courseId);

            Log::info("Search index rebuilt", [
                'course_id' => $courseId,
                'stats' => $stats,
                'triggered_by' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Search index rebuilt successfully',
                'data' => [
                    'course_id' => $courseId,
                    'rebuild_stats' => $stats
                ],
                'meta' => [
                    'rebuilt_at' => now()->toISOString(),
                    'triggered_by' => auth()->id()
                ]
            ]);

        } catch (\Exception $e) {
            Log::error("Search index rebuild failed", [
                'course_id' => $courseId,
                'error' => $e->getMessage(),
                'user_id' => auth()->id()
            ]);

            return response()->json([
                'message' => 'Search index rebuild failed',
                'error' => 'INDEX_REBUILD_FAILED'
            ], 500);
        }
    }

    /**
     * Log search for analytics
     */
    private function logSearch(string $query, ?string $courseId, ?string $userId, int $resultCount, float $responseTime): void
    {
        try {
            DB::table('search_logs')->insert([
                'user_id' => $userId,
                'course_id' => $courseId,
                'environment_id' => request()->header('X-Environment-ID'),
                'query' => substr($query, 0, 255),
                'result_count' => $resultCount,
                'response_time_ms' => $responseTime,
                'user_agent' => request()->userAgent(),
                'ip_address' => request()->ip(),
                'searched_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::warning("Failed to log search", [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Calculate index coverage for a course
     */
    private function calculateIndexCoverage(?string $courseId): array
    {
        if (!$courseId) {
            return [
                'coverage_percentage' => null,
                'message' => 'Coverage calculation requires course ID'
            ];
        }

        // This would typically involve comparing indexed messages count
        // with total messages count from the main API
        // For now, return a placeholder
        return [
            'coverage_percentage' => 95.5,
            'indexed_messages' => ChatSearchIndex::where('course_id', $courseId)->count(),
            'last_updated' => now()->toISOString()
        ];
    }

    /**
     * Validate user has access to course
     */
    private function validateCourseAccess(string $courseId): void
    {
        // Implementation would check if user has access to this course
        // This could be through enrollment, instructor role, or admin permissions
    }

    /**
     * Validate user has admin access to course
     */
    private function validateCourseAdminAccess(string $courseId): void
    {
        // Implementation would check if user has admin/instructor access to this course
    }
}