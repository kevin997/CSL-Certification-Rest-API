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
use App\Models\FeedbackSubmission;
use App\Models\FeedbackContent;
use App\Models\IssuedCertificate;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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

        // Optional date-range filtering for windowed stats. Both start_date and
        // end_date must be provided (as YYYY-MM-DD) and valid for the range to
        // apply. If either is missing or invalid, we silently fall back to the
        // existing hardcoded windows (no error response) to preserve backward
        // compatibility.
        [$startDate, $endDate] = $this->resolveDateRange($request);

        return response()->json([
            'success' => true,
            'data' => [
                'learnerStats' => $this->getLearnerStats($environmentId, $startDate, $endDate),
                'courseStats' => $this->getCourseStats($environmentId, $startDate, $endDate),
                'certificateStats' => $this->getCertificateStats($environmentId, $startDate, $endDate),
                'feedbackStats' => $this->getFeedbackStats($environmentId, $startDate, $endDate),
                'enrollmentTrends' => $this->getEnrollmentTrends($environmentId, $startDate, $endDate),
                'coursePerformance' => $this->getCoursePerformance($environmentId, $startDate, $endDate),
                'activityDistribution' => $this->getActivityDistribution($environmentId, $startDate, $endDate),
                'recentActivity' => $this->getRecentActivity($environmentId, $startDate, $endDate),
                'upcomingEvents' => $this->getUpcomingEvents($environmentId),
            ]
        ]);
    }

    /**
     * Resolve the optional start_date/end_date request params into a validated
     * [Carbon|null $startDate, Carbon|null $endDate] pair. Both dates must be
     * provided as YYYY-MM-DD and start_date must be before/equal to end_date,
     * otherwise the range is ignored and [null, null] is returned so callers
     * fall back to their default hardcoded windows.
     */
    private function resolveDateRange(Request $request): array
    {
        $validator = Validator::make($request->all(), [
            'start_date' => 'nullable|date_format:Y-m-d',
            'end_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
        ]);

        if ($validator->fails() || !$request->filled('start_date') || !$request->filled('end_date')) {
            return [null, null];
        }

        $startDate = Carbon::createFromFormat('Y-m-d', $request->input('start_date'))->startOfDay();
        $endDate = Carbon::createFromFormat('Y-m-d', $request->input('end_date'))->endOfDay();

        return [$startDate, $endDate];
    }

    /**
     * Get learner statistics
     */
    private function getLearnerStats($environmentId, $startDate = null, $endDate = null)
    {
        // Get total number of learners (all-time total, not date-ranged)
        $totalLearners = EnvironmentUser::where('environment_id', $environmentId)->count();

        if ($startDate && $endDate) {
            // New learners within the requested range
            $newLearners = EnvironmentUser::where('environment_id', $environmentId)
                ->whereBetween('joined_at', [$startDate, $endDate])
                ->count();

            // Compare against the equal-length period immediately preceding the range
            $periodLengthInSeconds = $startDate->diffInSeconds($endDate);
            $previousPeriodEnd = (clone $startDate)->subSecond();
            $previousPeriodStart = (clone $previousPeriodEnd)->subSeconds($periodLengthInSeconds);

            $previousPeriodLearners = EnvironmentUser::where('environment_id', $environmentId)
                ->whereBetween('joined_at', [$previousPeriodStart, $previousPeriodEnd])
                ->count();
        } else {
            // Get new learners in the last 30 days
            $newLearners = EnvironmentUser::where('environment_id', $environmentId)
                ->where('joined_at', '>=', Carbon::now()->subDays(30))
                ->count();

            // Calculate percentage increase
            $previousPeriodLearners = EnvironmentUser::where('environment_id', $environmentId)
                ->where('joined_at', '>=', Carbon::now()->subDays(60))
                ->where('joined_at', '<', Carbon::now()->subDays(30))
                ->count();
        }

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
    private function getCourseStats($environmentId, $startDate = null, $endDate = null)
    {
        // Get total active courses (all-time total, not date-ranged)
        $activeCourses = Course::where('environment_id', $environmentId)
            ->where('status', 'published')
            ->count();

        if ($startDate && $endDate) {
            // New courses published within the requested range
            $newCourses = Course::where('environment_id', $environmentId)
                ->whereBetween('published_at', [$startDate, $endDate])
                ->count();
        } else {
            // Get new courses in the last 30 days
            $newCourses = Course::where('environment_id', $environmentId)
                ->where('published_at', '>=', Carbon::now()->subDays(30))
                ->count();
        }

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
    private function getCertificateStats($environmentId, $startDate = null, $endDate = null)
    {
        // Get total certificates issued (all-time total, not date-ranged)
        $totalCertificates = IssuedCertificate::where('environment_id', $environmentId)
            ->where('status', 'active')
            ->count();

        if ($startDate && $endDate) {
            // Certificates issued within the requested range
            $recentCertificates = IssuedCertificate::where('environment_id', $environmentId)
                ->whereBetween('issued_date', [$startDate, $endDate])
                ->count();
        } else {
            // Get certificates issued in the last 7 days
            $recentCertificates = IssuedCertificate::where('environment_id', $environmentId)
                ->where('issued_date', '>=', Carbon::now()->subDays(7))
                ->count();
        }

        return [
            'totalCertificates' => $totalCertificates,
            'recentCertificates' => $recentCertificates,
        ];
    }

    /**
     * Get feedback statistics
     */
    private function getFeedbackStats($environmentId, $startDate = null, $endDate = null)
    {
        // Get total feedback submissions for this environment (all-time total, not date-ranged)
        $totalFeedback = FeedbackSubmission::whereHas('feedbackContent.activity.block.template', function ($query) use ($environmentId) {
            $query->where('environment_id', $environmentId);
        })->where('status', 'submitted')->count();

        if ($startDate && $endDate) {
            // Feedback submitted within the requested range
            $recentFeedback = FeedbackSubmission::whereHas('feedbackContent.activity.block.template', function ($query) use ($environmentId) {
                $query->where('environment_id', $environmentId);
            })->where('status', 'submitted')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->count();
        } else {
            // Get feedback in the last 30 days
            $recentFeedback = FeedbackSubmission::whereHas('feedbackContent.activity.block.template', function ($query) use ($environmentId) {
                $query->where('environment_id', $environmentId);
            })->where('status', 'submitted')
                ->where('created_at', '>=', Carbon::now()->subDays(30))
                ->count();
        }

        // Get feedback distribution by template (course)
        $feedbackByTemplate = FeedbackSubmission::whereHas('feedbackContent.activity.block.template', function ($query) use ($environmentId) {
            $query->where('environment_id', $environmentId);
        })->where('status', 'submitted')
            ->with('feedbackContent.activity.block.template:id,title')
            ->get()
            ->groupBy(function ($submission) {
                return $submission->feedbackContent?->activity?->block?->template?->title ?? 'Unknown';
            })
            ->map(function ($group, $templateName) {
                return [
                    'name' => $templateName,
                    'count' => $group->count(),
                ];
            })
            ->values()
            ->take(5)
            ->toArray();

        // Calculate average rating from feedback answers (numeric values)
        $avgRating = DB::table('feedback_answers')
            ->join('feedback_submissions', 'feedback_answers.feedback_submission_id', '=', 'feedback_submissions.id')
            ->join('feedback_contents', 'feedback_submissions.feedback_content_id', '=', 'feedback_contents.id')
            ->join('activities', 'feedback_contents.activity_id', '=', 'activities.id')
            ->join('blocks', 'activities.block_id', '=', 'blocks.id')
            ->join('templates', 'blocks.template_id', '=', 'templates.id')
            ->where('templates.environment_id', $environmentId)
            ->where('feedback_submissions.status', 'submitted')
            ->whereNotNull('feedback_answers.answer_value')
            ->avg('feedback_answers.answer_value');

        // Calculate performance score (percentage of positive ratings, assuming 4+ out of 5 is positive)
        $positiveRatings = DB::table('feedback_answers')
            ->join('feedback_submissions', 'feedback_answers.feedback_submission_id', '=', 'feedback_submissions.id')
            ->join('feedback_contents', 'feedback_submissions.feedback_content_id', '=', 'feedback_contents.id')
            ->join('activities', 'feedback_contents.activity_id', '=', 'activities.id')
            ->join('blocks', 'activities.block_id', '=', 'blocks.id')
            ->join('templates', 'blocks.template_id', '=', 'templates.id')
            ->where('templates.environment_id', $environmentId)
            ->where('feedback_submissions.status', 'submitted')
            ->whereNotNull('feedback_answers.answer_value')
            ->where('feedback_answers.answer_value', '>=', 4)
            ->count();

        $totalRatings = DB::table('feedback_answers')
            ->join('feedback_submissions', 'feedback_answers.feedback_submission_id', '=', 'feedback_submissions.id')
            ->join('feedback_contents', 'feedback_submissions.feedback_content_id', '=', 'feedback_contents.id')
            ->join('activities', 'feedback_contents.activity_id', '=', 'activities.id')
            ->join('blocks', 'activities.block_id', '=', 'blocks.id')
            ->join('templates', 'blocks.template_id', '=', 'templates.id')
            ->where('templates.environment_id', $environmentId)
            ->where('feedback_submissions.status', 'submitted')
            ->whereNotNull('feedback_answers.answer_value')
            ->count();

        $performanceScore = $totalRatings > 0
            ? round(($positiveRatings / $totalRatings) * 100, 1)
            : 0;

        return [
            'totalFeedback' => $totalFeedback,
            'recentFeedback' => $recentFeedback,
            'averageRating' => round($avgRating ?? 0, 2),
            'performanceScore' => $performanceScore,
            'feedbackByTemplate' => $feedbackByTemplate,
        ];
    }

    /**
     * Get enrollment trends (monthly)
     */
    private function getEnrollmentTrends($environmentId, $startDate = null, $endDate = null)
    {
        if ($startDate && $endDate) {
            // Cover the full calendar months spanned by the requested range
            $rangeStart = (clone $startDate)->startOfMonth();
            $rangeEnd = (clone $endDate)->startOfMonth();
        } else {
            // Default: cover the last 6 calendar months
            $rangeEnd = Carbon::now()->startOfMonth();
            $rangeStart = (clone $rangeEnd)->subMonths(5);
        }

        $queryStart = (clone $rangeStart)->startOfMonth();
        $queryEnd = (clone $rangeEnd)->endOfMonth();

        // Get enrollments grouped by month/year within the window
        $enrollments = Enrollment::where('environment_id', $environmentId)
            ->whereBetween('enrolled_at', [$queryStart, $queryEnd])
            ->select(
                DB::raw('MONTH(enrolled_at) as month'),
                DB::raw('YEAR(enrolled_at) as year'),
                DB::raw('COUNT(*) as enrollments')
            )
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get();

        // Index counts by "Y-m" for easy zero-filled lookups
        $countsByMonth = [];
        foreach ($enrollments as $enrollment) {
            $key = sprintf('%04d-%02d', $enrollment->year, $enrollment->month);
            $countsByMonth[$key] = (int) $enrollment->enrollments;
        }

        // Build a continuous, zero-filled sequence of months across the window
        $formattedData = [];
        $cursor = (clone $rangeStart);
        while ($cursor->lte($rangeEnd)) {
            $key = $cursor->format('Y-m');

            $formattedData[] = [
                'name' => $cursor->format('M Y'),
                'date' => $key,
                'enrollments' => $countsByMonth[$key] ?? 0,
            ];

            $cursor->addMonth();
        }

        return $formattedData;
    }

    /**
     * Get course performance data
     */
    private function getCoursePerformance($environmentId, $startDate = null, $endDate = null)
    {
        // Get top 5 courses by enrollment
        $topCourses = Course::where('environment_id', $environmentId)
            ->where('status', 'published')
            ->withCount(['enrollments' => function ($query) use ($startDate, $endDate) {
                $query->where('status', '!=', 'dropped');
                if ($startDate && $endDate) {
                    $query->whereBetween('enrolled_at', [$startDate, $endDate]);
                }
            }])
            ->orderBy('enrollments_count', 'desc')
            ->limit(5)
            ->get();

        $coursePerformance = [];
        foreach ($topCourses as $course) {
            // Get average score for this course
            $avgScoreQuery = ActivityCompletion::whereHas('enrollment', function ($query) use ($course, $environmentId) {
                $query->where('course_id', $course->id)
                    ->where('environment_id', $environmentId);
            });
            if ($startDate && $endDate) {
                $avgScoreQuery->whereBetween('completed_at', [$startDate, $endDate]);
            }
            $avgScore = $avgScoreQuery->avg('score');

            // Get completion rate for this course
            $totalEnrollmentsQuery = Enrollment::where('course_id', $course->id)
                ->where('environment_id', $environmentId);

            $completedEnrollmentsQuery = Enrollment::where('course_id', $course->id)
                ->where('environment_id', $environmentId)
                ->where('status', 'completed');

            if ($startDate && $endDate) {
                $totalEnrollmentsQuery->whereBetween('enrolled_at', [$startDate, $endDate]);
                $completedEnrollmentsQuery->whereBetween('enrolled_at', [$startDate, $endDate]);
            }

            $totalEnrollments = $totalEnrollmentsQuery->count();
            $completedEnrollments = $completedEnrollmentsQuery->count();

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
    private function getActivityDistribution($environmentId, $startDate = null, $endDate = null)
    {
        // Count activities by type
        $activityTypes = [
            'lesson' => ['color' => '#0284c7', 'count' => 0],
            'quiz' => ['color' => '#7c3aed', 'count' => 0],
            'assignment' => ['color' => '#f97316', 'count' => 0],
            'event' => ['color' => '#10b981', 'count' => 0],
            'certificate' => ['color' => '#f59e0b', 'count' => 0],
        ];

        foreach (array_keys($activityTypes) as $type) {
            $query = ActivityCompletion::whereHas('activity', function ($query) use ($type) {
                $query->where('type', $type);
            })->whereHas('enrollment', function ($query) use ($environmentId) {
                $query->where('environment_id', $environmentId);
            });

            if ($startDate && $endDate) {
                $query->whereBetween('completed_at', [$startDate, $endDate]);
            }

            $activityTypes[$type]['count'] = $query->count();
        }

        // Format data for frontend
        $formattedData = [];
        foreach ($activityTypes as $type => $data) {
            $label = $type === 'quiz' ? 'Quizzes' : (ucfirst($type) . 's');

            $formattedData[] = [
                'name' => $label,
                'value' => $data['count'],
                'color' => $data['color'],
            ];
        }

        return $formattedData;
    }

    /**
     * Get recent activity data
     */
    private function getRecentActivity($environmentId, $startDate = null, $endDate = null)
    {
        $recentActivity = [];

        // Get recent course completions
        $completionsQuery = Enrollment::with('user', 'course')
            ->where('environment_id', $environmentId)
            ->where('status', 'completed');

        if ($startDate && $endDate) {
            $completionsQuery->whereBetween('completed_at', [$startDate, $endDate]);
        } else {
            $completionsQuery->where('completed_at', '>=', Carbon::now()->subDays(7));
        }

        $completions = $completionsQuery
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
        $enrollmentsQuery = Enrollment::with('user', 'course')
            ->where('environment_id', $environmentId);

        if ($startDate && $endDate) {
            $enrollmentsQuery->whereBetween('enrolled_at', [$startDate, $endDate]);
        } else {
            $enrollmentsQuery->where('enrolled_at', '>=', Carbon::now()->subDays(7));
        }

        $enrollments = $enrollmentsQuery
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
        $certificatesQuery = IssuedCertificate::with('user', 'course')
            ->where('environment_id', $environmentId);

        if ($startDate && $endDate) {
            $certificatesQuery->whereBetween('issued_date', [$startDate, $endDate]);
        } else {
            $certificatesQuery->where('issued_date', '>=', Carbon::now()->subDays(7));
        }

        $certificates = $certificatesQuery
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
            ->join('activities', function ($join) {
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
