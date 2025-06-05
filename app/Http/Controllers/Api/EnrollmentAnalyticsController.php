<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\EnrollmentAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class EnrollmentAnalyticsController extends Controller
{
    /**
     * Store or update analytics data for an activity
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function trackActivityAnalytics(Request $request): JsonResponse
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'enrollment_id' => 'required|exists:enrollments,id',
            'activity_id' => 'required|string',
            'activity_type' => 'required|string',
            'metrics' => 'required|array',
            'metrics.time_spent' => 'sometimes|integer|min:0',
            'metrics.active_time' => 'sometimes|integer|min:0',
            'metrics.idle_time' => 'sometimes|integer|min:0',
            'metrics.session_duration' => 'sometimes|integer|min:0',
            'metrics.click_count' => 'sometimes|integer|min:0',
            'metrics.scroll_count' => 'sometimes|integer|min:0',
            'metrics.scroll_depth' => 'sometimes|integer|min:0|max:100',
            'metrics.focus_events' => 'sometimes|integer|min:0',
            'metrics.pause_resume_events' => 'sometimes|integer|min:0',
            'metrics.retry_attempts' => 'sometimes|integer|min:0',
            'metrics.navigation_events' => 'sometimes|integer|min:0',
            'metrics.completion_percentage' => 'sometimes|numeric|min:0|max:100',
            'metrics.completed' => 'sometimes|boolean',
            'metrics.events' => 'sometimes|array',
            'metrics.device_info' => 'sometimes|array',
            'metrics.performance_data' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Find or create analytics record
        $analytics = EnrollmentAnalytics::firstOrNew([
            'enrollment_id' => $request->enrollment_id,
            'activity_id' => $request->activity_id,
            'activity_type' => $request->activity_type,
        ]);

        // Update metrics
        $analytics->updateSessionMetrics($request->metrics);

        // If this is a completion event, update the enrollment's last_activity_at
        if (isset($request->metrics['completed']) && $request->metrics['completed']) {
            $enrollment = Enrollment::find($request->enrollment_id);
            if ($enrollment) {
                $enrollment->last_activity_at = now();
                
                // Optionally update progress percentage based on all activities
                $this->updateEnrollmentProgress($enrollment);
                
                $enrollment->save();
            }
        }

        return response()->json([
            'message' => 'Analytics data tracked successfully',
            'data' => $analytics
        ], 200);
    }

    /**
     * Get analytics data for a specific activity
     * 
     * @param Request $request
     * @param int $enrollmentId
     * @param string $activityId
     * @return JsonResponse
     */
    public function getActivityAnalytics(Request $request, int $enrollmentId, string $activityId): JsonResponse
    {
        $analytics = EnrollmentAnalytics::where('enrollment_id', $enrollmentId)
            ->where('activity_id', $activityId)
            ->first();

        if (!$analytics) {
            return response()->json([
                'message' => 'Analytics data not found'
            ], 404);
        }

        return response()->json([
            'data' => $analytics
        ]);
    }

    /**
     * Get all analytics data for an enrollment
     * 
     * @param Request $request
     * @param int $enrollmentId
     * @return JsonResponse
     */
    public function getEnrollmentAnalytics(Request $request, int $enrollmentId): JsonResponse
    {
        $analytics = EnrollmentAnalytics::where('enrollment_id', $enrollmentId)
            ->orderBy('engagement_score', 'desc')
            ->get();

        return response()->json([
            'data' => $analytics
        ]);
    }

    /**
     * Get summary of analytics across all enrollments for a user
     * 
     * @param Request $request
     * @param int $userId
     * @return JsonResponse
     */
    public function getUserAnalyticsSummary(Request $request, int $userId): JsonResponse
    {
        // Get user's enrollments
        $enrollments = Enrollment::where('user_id', $userId)->pluck('id');

        // Get analytics summary
        $analytics = EnrollmentAnalytics::whereIn('enrollment_id', $enrollments)
            ->select(
                DB::raw('SUM(time_spent) as total_time_spent'),
                DB::raw('SUM(active_time) as total_active_time'),
                DB::raw('AVG(engagement_score) as average_engagement'),
                DB::raw('SUM(CASE WHEN completed_at IS NOT NULL THEN 1 ELSE 0 END) as completed_activities'),
                DB::raw('COUNT(*) as total_activities'),
                DB::raw('SUM(click_count) as total_clicks'),
                DB::raw('SUM(scroll_count) as total_scrolls'),
                DB::raw('COUNT(DISTINCT activity_type) as activity_types_engaged')
            )
            ->first();

        // Get recent activities
        $recentActivities = EnrollmentAnalytics::whereIn('enrollment_id', $enrollments)
            ->orderBy('last_accessed_at', 'desc')
            ->take(5)
            ->get();

        // Get activity type breakdown
        $activityTypeBreakdown = EnrollmentAnalytics::whereIn('enrollment_id', $enrollments)
            ->select('activity_type', DB::raw('COUNT(*) as count'), DB::raw('AVG(engagement_score) as average_engagement'))
            ->groupBy('activity_type')
            ->get();

        return response()->json([
            'data' => [
                'summary' => $analytics,
                'recent_activities' => $recentActivities,
                'activity_type_breakdown' => $activityTypeBreakdown,
                'completion_rate' => $analytics->total_activities > 0 
                    ? ($analytics->completed_activities / $analytics->total_activities) * 100 
                    : 0
            ]
        ]);
    }
    
    /**
     * Update enrollment progress percentage based on completed activities
     * 
     * @param Enrollment $enrollment
     * @return void
     */
    private function updateEnrollmentProgress(Enrollment $enrollment): void
    {
        $courseId = $enrollment->course_id;
        
        // Get total activities for course
        $totalActivities = DB::table('activities')
            ->where('course_id', $courseId)
            ->count();
        
        if ($totalActivities === 0) {
            return;
        }
        
        // Get completed activities for this enrollment
        $completedActivities = DB::table('activity_completions')
            ->where('enrollment_id', $enrollment->id)
            ->where('completed', true)
            ->count();
        
        // Calculate progress percentage
        $progressPercentage = ($completedActivities / $totalActivities) * 100;
        
        // Update enrollment
        $enrollment->progress_percentage = $progressPercentage;
        
        // If all activities are completed, mark enrollment as completed
        if ($completedActivities === $totalActivities) {
            $enrollment->status = 'completed';
            $enrollment->completed_at = now();
        } else {
            $enrollment->status = 'in-progress';
        }
    }
    
    /**
     * Get engagement over time for a course
     * 
     * @param Request $request
     * @param int $courseId
     * @return JsonResponse
     */
    public function getCourseEngagementOverTime(Request $request, int $courseId): JsonResponse
    {
        // Default to last 30 days if not specified
        $startDate = $request->input('start_date') 
            ? Carbon::parse($request->input('start_date')) 
            : Carbon::now()->subDays(30);
        $endDate = $request->input('end_date') 
            ? Carbon::parse($request->input('end_date')) 
            : Carbon::now();

        // Get enrollments for course
        $enrollmentIds = Enrollment::where('course_id', $courseId)
            ->pluck('id');

        // Get daily engagement metrics
        $metrics = DB::table('enrollment_analytics')
            ->join('enrollments', 'enrollment_analytics.enrollment_id', '=', 'enrollments.id')
            ->whereIn('enrollment_analytics.enrollment_id', $enrollmentIds)
            ->whereBetween('enrollment_analytics.last_accessed_at', [$startDate, $endDate])
            ->select(
                DB::raw('DATE(enrollment_analytics.last_accessed_at) as date'),
                DB::raw('COUNT(DISTINCT enrollments.user_id) as unique_users'),
                DB::raw('SUM(enrollment_analytics.time_spent) as total_time_spent'),
                DB::raw('AVG(enrollment_analytics.engagement_score) as average_engagement')
            )
            ->groupBy(DB::raw('DATE(enrollment_analytics.last_accessed_at)'))
            ->orderBy('date')
            ->get();

        return response()->json([
            'data' => $metrics
        ]);
    }
    
    /**
     * Get activity engagement leaderboard
     * 
     * @param Request $request
     * @param int $courseId
     * @return JsonResponse
     */
    public function getActivityEngagementLeaderboard(Request $request, int $courseId): JsonResponse
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'limit' => 'sometimes|integer|min:1|max:100',
            'activity_type' => 'sometimes|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $limit = $request->input('limit', 10);
        
        // Build query
        $query = EnrollmentAnalytics::join('enrollments', 'enrollment_analytics.enrollment_id', '=', 'enrollments.id')
            ->where('enrollments.course_id', $courseId);
            
        // Filter by activity type if provided
        if ($request->has('activity_type')) {
            $query->where('enrollment_analytics.activity_type', $request->activity_type);
        }
        
        // Get top activities by engagement score
        $leaderboard = $query->select(
                'enrollment_analytics.activity_id',
                'enrollment_analytics.activity_type',
                DB::raw('AVG(enrollment_analytics.engagement_score) as average_engagement'),
                DB::raw('COUNT(DISTINCT enrollments.user_id) as unique_users'),
                DB::raw('AVG(enrollment_analytics.time_spent) as average_time_spent'),
                DB::raw('SUM(CASE WHEN enrollment_analytics.completed_at IS NOT NULL THEN 1 ELSE 0 END) as completion_count')
            )
            ->groupBy('enrollment_analytics.activity_id', 'enrollment_analytics.activity_type')
            ->orderBy('average_engagement', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => $leaderboard
        ]);
    }
}
