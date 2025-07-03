<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityCompletion;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\EventContent;
use App\Models\EventRegistration;
use App\Models\IssuedCertificate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Get dashboard data for an environment
     *
     * @OA\Get(
     *     path="/api/dashboard",
     *     summary="Get dashboard data",
     *     description="Returns aggregated dashboard data for the current environment",
     *     operationId="getDashboardData",
     *     tags={"Dashboard"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Dashboard data retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="learnerStats", type="object"),
     *                 @OA\Property(property="courseStats", type="object"),
     *                 @OA\Property(property="certificateStats", type="object"),
     *                 @OA\Property(property="enrollmentTrends", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="coursePerformance", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="activityDistribution", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="recentActivity", type="array", @OA\Items(type="object")),
     *                 @OA\Property(property="upcomingEvents", type="array", @OA\Items(type="object"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden"
     *     )
     * )
     */
    public function getDashboardData(Request $request)
    {
        // Get the environment ID from the authenticated user
        $environmentId = session()->get('current_environment_id');

        return response()->json([
            'success' => true,
            'data' => [
                'learnerStats' => $this->getLearnerStats($environmentId),
                'courseStats' => $this->getCourseStats($environmentId),
                'certificateStats' => $this->getCertificateStats($environmentId),
                'enrollmentTrends' => $this->getEnrollmentTrends($environmentId),
                'coursePerformance' => $this->getCoursePerformance($environmentId),
                'activityDistribution' => $this->getActivityDistribution($environmentId),
                'recentActivity' => $this->getRecentActivity($environmentId),
                'upcomingEvents' => $this->getUpcomingEvents($environmentId),
            ]
        ]);
    }

    /**
     * Get learner statistics
     */
    private function getLearnerStats($environmentId)
    {
        // Get total number of learners
        $totalLearners = EnvironmentUser::where('environment_id', $environmentId)->count();

        // Get new learners in the last 30 days
        $newLearners = EnvironmentUser::where('environment_id', $environmentId)
            ->where('joined_at', '>=', Carbon::now()->subDays(30))
            ->count();

        // Calculate percentage increase
        $previousPeriodLearners = EnvironmentUser::where('environment_id', $environmentId)
            ->where('joined_at', '>=', Carbon::now()->subDays(60))
            ->where('joined_at', '<', Carbon::now()->subDays(30))
            ->count();

        $percentageIncrease = $previousPeriodLearners > 0 
            ? round((($newLearners - $previousPeriodLearners) / $previousPeriodLearners) * 100, 2)
            : 0;

        return [
            'totalLearners' => $totalLearners,
            'newLearners' => $newLearners,
            'percentageIncrease' => $percentageIncrease,
        ];
    }

    /**
     * Get course statistics
     */
    private function getCourseStats($environmentId)
    {
        // Get total active courses
        $activeCourses = Course::where('environment_id', $environmentId)
            ->where('status', 'published')
            ->count();

        // Get new courses in the last 30 days
        $newCourses = Course::where('environment_id', $environmentId)
            ->where('published_at', '>=', Carbon::now()->subDays(30))
            ->count();

        // Calculate average completion rate
        $completionRate = Enrollment::where('environment_id', $environmentId)
            ->where('status', 'completed')
            ->avg('progress_percentage');

        return [
            'activeCourses' => $activeCourses,
            'newCourses' => $newCourses,
            'completionRate' => round($completionRate ?? 0, 2),
        ];
    }

    /**
     * Get certificate statistics
     */
    private function getCertificateStats($environmentId)
    {
        // Get total certificates issued
        $totalCertificates = IssuedCertificate::where('environment_id', $environmentId)
            ->where('status', 'active')
            ->count();

        // Get certificates issued in the last 7 days
        $recentCertificates = IssuedCertificate::where('environment_id', $environmentId)
            ->where('issued_date', '>=', Carbon::now()->subDays(7))
            ->count();

        return [
            'totalCertificates' => $totalCertificates,
            'recentCertificates' => $recentCertificates,
        ];
    }

    /**
     * Get enrollment trends (monthly)
     */
    private function getEnrollmentTrends($environmentId)
    {
        // Get enrollments for the last 6 months
        $enrollments = Enrollment::where('environment_id', $environmentId)
            ->where('enrolled_at', '>=', Carbon::now()->subMonths(6))
            ->select(
                DB::raw('MONTH(enrolled_at) as month'),
                DB::raw('YEAR(enrolled_at) as year'),
                DB::raw('COUNT(*) as enrollments')
            )
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        $monthNames = [
            1 => 'Jan', 2 => 'Feb', 3 => 'Mar', 4 => 'Apr', 5 => 'May', 6 => 'Jun',
            7 => 'Jul', 8 => 'Aug', 9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dec'
        ];

        $formattedData = [];
        foreach ($enrollments as $enrollment) {
            $formattedData[] = [
                'name' => $monthNames[$enrollment->month],
                'enrollments' => $enrollment->enrollments,
            ];
        }

        return $formattedData;
    }

    /**
     * Get course performance data
     */
    private function getCoursePerformance($environmentId)
    {
        // Get top 5 courses by enrollment
        $topCourses = Course::where('environment_id', $environmentId)
            ->where('status', 'published')
            ->withCount(['enrollments' => function ($query) {
                $query->where('status', '!=', 'dropped');
            }])
            ->orderBy('enrollments_count', 'desc')
            ->limit(5)
            ->get();

        $coursePerformance = [];
        foreach ($topCourses as $course) {
            // Get average score for this course
            $avgScore = ActivityCompletion::whereHas('enrollment', function ($query) use ($course, $environmentId) {
                $query->where('course_id', $course->id)
                    ->where('environment_id', $environmentId);
            })->avg('score');

            // Get completion rate for this course
            $totalEnrollments = Enrollment::where('course_id', $course->id)
                ->where('environment_id', $environmentId)
                ->count();
            
            $completedEnrollments = Enrollment::where('course_id', $course->id)
                ->where('environment_id', $environmentId)
                ->where('status', 'completed')
                ->count();
            
            $completionRate = $totalEnrollments > 0 
                ? round(($completedEnrollments / $totalEnrollments) * 100, 2) 
                : 0;

            $coursePerformance[] = [
                'name' => $course->title,
                'avgScore' => round($avgScore ?? 0, 2),
                'completionRate' => $completionRate,
            ];
        }

        return $coursePerformance;
    }

    /**
     * Get activity distribution data
     */
    private function getActivityDistribution($environmentId)
    {
        // Count activities by type
        $activityTypes = [
            'lesson' => ['color' => '#0284c7', 'count' => 0],
            'quiz' => ['color' => '#7c3aed', 'count' => 0],
            'assignment' => ['color' => '#f97316', 'count' => 0],
            'event' => ['color' => '#10b981', 'count' => 0],
            'certificate' => ['color' => '#f59e0b', 'count' => 0],
        ];

        // Count lesson completions
        $activityTypes['lesson']['count'] = ActivityCompletion::whereHas('activity', function ($query) {
            $query->where('type', 'lesson');
        })->whereHas('enrollment', function ($query) use ($environmentId) {
            $query->where('environment_id', $environmentId);
        })->count();

        // Count quiz completions
        $activityTypes['quiz']['count'] = ActivityCompletion::whereHas('activity', function ($query) {
            $query->where('type', 'quiz');
        })->whereHas('enrollment', function ($query) use ($environmentId) {
            $query->where('environment_id', $environmentId);
        })->count();

        // Count assignment completions
        $activityTypes['assignment']['count'] = ActivityCompletion::whereHas('activity', function ($query) {
            $query->where('type', 'assignment');
        })->whereHas('enrollment', function ($query) use ($environmentId) {
            $query->where('environment_id', $environmentId);
        })->count();

        // Count event registrations
        $activityTypes['event']['count'] = ActivityCompletion::whereHas('activity', function ($query) {
            $query->where('type', 'event');
        })->whereHas('enrollment', function ($query) use ($environmentId) {
            $query->where('environment_id', $environmentId);
        })->count();

        // Count certificates issued
        $activityTypes['certificate']['count'] = ActivityCompletion::whereHas('activity', function ($query) {
            $query->where('type', 'certificate');
        })->whereHas('enrollment', function ($query) use ($environmentId) {
            $query->where('environment_id', $environmentId);
        })->count();

        // Format data for frontend
        $formattedData = [];
        foreach ($activityTypes as $type => $data) {
            $formattedData[] = [
                'name' => ucfirst($type) . 's',
                'value' => $data['count'],
                'color' => $data['color'],
            ];
        }

        return $formattedData;
    }

    /**
     * Get recent activity data
     */
    private function getRecentActivity($environmentId)
    {
        $recentActivity = [];

        // Get recent course completions
        $completions = Enrollment::with('user', 'course')
            ->where('environment_id', $environmentId)
            ->where('status', 'completed')
            ->where('completed_at', '>=', Carbon::now()->subDays(7))
            ->orderBy('completed_at', 'desc')
            ->limit(4)
            ->get();

        foreach ($completions as $completion) {
            $recentActivity[] = [
                'type' => 'completion',
                'icon' => 'CheckCircle',
                'iconColor' => 'green',
                'message' => "{$completion->user->name} completed \"{$completion->course->title}\"",
                'timestamp' => $completion->completed_at->diffForHumans(),
                'date' => $completion->completed_at,
            ];
        }

        // Get recent enrollments
        $enrollments = Enrollment::with('user', 'course')
            ->where('environment_id', $environmentId)
            ->where('enrolled_at', '>=', Carbon::now()->subDays(7))
            ->orderBy('enrolled_at', 'desc')
            ->limit(4)
            ->get();

        foreach ($enrollments as $enrollment) {
            $recentActivity[] = [
                'type' => 'enrollment',
                'icon' => 'Users',
                'iconColor' => 'blue',
                'message' => "{$enrollment->user->name} enrolled in \"{$enrollment->course->title}\"",
                'timestamp' => $enrollment->enrolled_at->diffForHumans(),
                'date' => $enrollment->enrolled_at,
            ];
        }

        // Get recent certificates
        $certificates = IssuedCertificate::with('user', 'course')
            ->where('environment_id', $environmentId)
            ->where('issued_date', '>=', Carbon::now()->subDays(7))
            ->orderBy('issued_date', 'desc')
            ->limit(4)
            ->get();

        foreach ($certificates as $certificate) {
            $recentActivity[] = [
                'type' => 'certificate',
                'icon' => 'File',
                'iconColor' => 'yellow',
                'message' => "Certificate issued to {$certificate->user->name} for \"{$certificate->course->title}\"",
                'timestamp' => $certificate->issued_date->diffForHumans(),
                'date' => $certificate->issued_date,
            ];
        }

        // Sort by date (most recent first) and limit to 4 items
        usort($recentActivity, function ($a, $b) {
            return $b['date']->timestamp - $a['date']->timestamp;
        });

        $recentActivity = array_slice($recentActivity, 0, 4);

        // Remove the date field as it's not needed in the frontend
        foreach ($recentActivity as &$activity) {
            unset($activity['date']);
        }

        return $recentActivity;
    }

    /**
     * Get upcoming events data
     */
    private function getUpcomingEvents($environmentId)
    {
        $upcomingEvents = [];

        // Get upcoming webinars/events
        // First, find activities of type 'event' in the environment
        $eventActivities = Activity::where('type', 'event')
            ->whereHas('block.template.courses', function ($query) use ($environmentId) {
                $query->where('environment_id', $environmentId);
            })
            ->get();
            
        // Get the event content for these activities
        $eventContentIds = $eventActivities->pluck('content_id');
        $events = EventContent::whereIn('id', $eventContentIds)
            ->where('start_date', '>=', Carbon::now())
            ->where('start_date', '<=', Carbon::now()->addDays(14))
            ->orderBy('start_date')
            ->limit(4)
            ->get();

        foreach ($events as $event) {
            // Count registrations
            $registrationsCount = EventRegistration::where('event_content_id', $event->id)->count();
            
            $upcomingEvents[] = [
                'type' => 'event',
                'icon' => 'Calendar',
                'iconColor' => $event->is_webinar ? 'purple' : 'green',
                'title' => $event->title,
                'timestamp' => $event->start_date->format('l, g:i A'),
                'details' => $event->is_webinar ? 'Webinar' : 'In-person event',
                'registrations' => $registrationsCount . ' registrations',
            ];
        }

        // Get upcoming assignment deadlines
        $assignmentDeadlines = DB::table('assignment_contents')
            ->join('activities', function($join) {
                $join->on('activities.content_id', '=', 'assignment_contents.id')
                     ->where('activities.content_type', '=', 'App\\Models\\AssignmentContent');
            })
            ->join('course_section_items', 'course_section_items.activity_id', '=', 'activities.id')
            ->join('course_sections', 'course_sections.id', '=', 'course_section_items.course_section_id')
            ->join('courses', 'courses.id', '=', 'course_sections.course_id')
            ->where('courses.environment_id', $environmentId)
            ->where('assignment_contents.due_date', '>=', Carbon::now())
            ->where('assignment_contents.due_date', '<=', Carbon::now()->addDays(14))
            ->select(
                'activities.title',
                'assignment_contents.due_date',
                'courses.title as course_title',
                'courses.id as course_id'
            )
            ->orderBy('assignment_contents.due_date')
            ->limit(4)
            ->get();

        foreach ($assignmentDeadlines as $deadline) {
            // Count pending submissions
            $pendingSubmissions = DB::table('enrollments')
                ->where('course_id', $deadline->course_id)
                ->where('status', '!=', 'dropped')
                ->count();
            
            $upcomingEvents[] = [
                'type' => 'deadline',
                'icon' => 'Clock',
                'iconColor' => 'orange',
                'title' => "\"{$deadline->title}\" Assignment Due",
                'timestamp' => Carbon::parse($deadline->due_date)->diffForHumans(),
                'details' => "For course: {$deadline->course_title}",
                'pendingSubmissions' => $pendingSubmissions . ' submissions pending',
            ];
        }

        // Sort by date (soonest first) and limit to 4 items
        usort($upcomingEvents, function ($a, $b) {
            return strtotime($a['timestamp']) - strtotime($b['timestamp']);
        });

        return array_slice($upcomingEvents, 0, 4);
    }
}
