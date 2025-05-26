<?php

namespace App\Http\Controllers\Api\Learner;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\ActivityCompletion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard data for the authenticated learner
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $environmentId = $request->header('X-Environment-ID');
        
        // Get course enrollment counts and statuses
        $enrollmentStats = Enrollment::where('user_id', $user->id)
            ->where('environment_id', $environmentId)
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status')
            ->toArray();
            
        // Get recent enrollments
        $recentEnrollments = Enrollment::where('user_id', $user->id)
            ->where('environment_id', $environmentId)
            ->with('course')
            ->orderBy('enrolled_at', 'desc')
            ->limit(5)
            ->get();
            
        // Get in-progress courses
        $inProgressCourses = Enrollment::where('user_id', $user->id)
            ->where('environment_id', $environmentId)
            ->where('status', 'in-progress')
            ->with('course')
            ->orderBy('last_activity_at', 'desc')
            ->limit(5)
            ->get();
            
        // Get recent orders
        $recentOrders = Order::where('user_id', $user->id)
            ->where('environment_id', $environmentId)
            ->with('items.product')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();
            
        // Get overall completion statistics
        $totalActivities = 0;
        $completedActivities = 0;
        
        $enrollments = Enrollment::where('user_id', $user->id)
            ->where('environment_id', $environmentId)
            ->with(['course.template.blocks.activities', 'activityCompletions'])
            ->get();
            
        foreach ($enrollments as $enrollment) {
            $course = $enrollment->course;
            if ($course && $course->template) {
                foreach ($course->template->blocks as $block) {
                    $totalActivities += $block->activities->count();
                }
            }
            
            $completedActivities += $enrollment->activityCompletions->where('completed', true)->count();
        }
        
        $overallProgressPercentage = $totalActivities > 0 ? ($completedActivities / $totalActivities) * 100 : 0;
        
        // Get activity completion timeline (for charts)
        $completionTimeline = ActivityCompletion::whereHas('enrollment', function ($query) use ($user, $environmentId) {
                $query->where('user_id', $user->id)
                    ->where('environment_id', $environmentId);
            })
            ->where('completed', true)
            ->select(DB::raw('DATE(completed_at) as date'), DB::raw('count(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->limit(30)
            ->get();
            
        // Create response data
        $dashboardData = [
            'enrollment_stats' => [
                'total' => array_sum($enrollmentStats),
                'enrolled' => $enrollmentStats['enrolled'] ?? 0,
                'in_progress' => $enrollmentStats['in-progress'] ?? 0,
                'completed' => $enrollmentStats['completed'] ?? 0,
                'dropped' => $enrollmentStats['dropped'] ?? 0,
            ],
            'progress_stats' => [
                'total_activities' => $totalActivities,
                'completed_activities' => $completedActivities,
                'progress_percentage' => $overallProgressPercentage,
            ],
            'recent_enrollments' => $recentEnrollments,
            'in_progress_courses' => $inProgressCourses,
            'recent_orders' => $recentOrders,
            'completion_timeline' => $completionTimeline,
        ];
        
        return response()->json([
            'status' => 'success',
            'data' => $dashboardData,
        ]);
    }
}
