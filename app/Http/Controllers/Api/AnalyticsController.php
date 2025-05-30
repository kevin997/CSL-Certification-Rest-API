<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Environment;
use App\Models\IssuedCertificate;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Response;

class AnalyticsController extends Controller
{
    /**
     * Get analytics overview for the current environment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function overview(Request $request)
    {
        // Get the environment ID from the authenticated user
        $environmentId = session('current_environment_id');
        
        if (!$environmentId) {
            return response()->json([
                'success' => false,
                'message' => 'No environment selected'
            ], 400);
        }

        // Get user statistics
        $userStats = $this->getUserStats($environmentId);

        // Get enrollment statistics
        $enrollmentStats = $this->getEnrollmentStats($environmentId);

        // Get course statistics
        $courseStats = $this->getCourseStats($environmentId);

        // Get certificate statistics
        $certificateStats = $this->getCertificateStats($environmentId);

        // Get enrollment trends
        $enrollmentTrends = $this->getEnrollmentTrends($environmentId);

        // Get completion rates
        $completionRates = $this->getCompletionRates($environmentId);

        return response()->json([
            'success' => true,
            'data' => [
                'user_stats' => $userStats,
                'enrollment_stats' => $enrollmentStats,
                'course_stats' => $courseStats,
                'certificate_stats' => $certificateStats,
                'enrollment_trends' => $enrollmentTrends,
                'completion_rates' => $completionRates
            ]
        ]);
    }

    /**
     * Get user statistics for the environment.
     *
     * @param  int  $environmentId
     * @return array
     */
    private function getUserStats($environmentId)
    {
        $totalUsers = User::whereHas('environments', function ($query) use ($environmentId) {
            $query->where('environments.id', $environmentId);
        })->count();

        $newUsersThisMonth = User::whereHas('environments', function ($query) use ($environmentId) {
            $query->where('environments.id', $environmentId);
        })
        ->whereMonth('created_at', now()->month)
        ->whereYear('created_at', now()->year)
        ->count();

        $activeUsers = User::whereHas('environments', function ($query) use ($environmentId) {
            $query->where('environments.id', $environmentId);
        })
        ->whereHas('enrollments', function ($query) use ($environmentId) {
            $query->where('environment_id', $environmentId)
                  ->where('last_activity_at', '>=', now()->subDays(30));
        })
        ->count();

        // Get monthly new users for the last 12 months
        $monthlyNewUsers = User::whereHas('environments', function ($query) use ($environmentId) {
            $query->where('environments.id', $environmentId);
        })
        ->where('created_at', '>=', now()->subMonths(12))
        ->select(
            DB::raw('YEAR(created_at) as year'),
            DB::raw('MONTH(created_at) as month'),
            DB::raw('COUNT(*) as count')
        )
        ->groupBy('year', 'month')
        ->orderBy('year')
        ->orderBy('month')
        ->get()
        ->map(function ($item) {
            return [
                'month' => $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT),
                'count' => $item->count
            ];
        });

        return [
            'total' => $totalUsers,
            'new_this_month' => $newUsersThisMonth,
            'active' => $activeUsers,
            'monthly_new_users' => $monthlyNewUsers
        ];
    }

    /**
     * Get enrollment statistics for the environment.
     *
     * @param  int  $environmentId
     * @return array
     */
    private function getEnrollmentStats($environmentId)
    {
        $totalEnrollments = Enrollment::where('environment_id', $environmentId)->count();
        
        $activeEnrollments = Enrollment::where('environment_id', $environmentId)
            ->where('status', 'in-progress')
            ->count();
        
        $completedEnrollments = Enrollment::where('environment_id', $environmentId)
            ->where('status', 'completed')
            ->count();
        
        $newEnrollmentsThisMonth = Enrollment::where('environment_id', $environmentId)
            ->whereMonth('enrolled_at', now()->month)
            ->whereYear('enrolled_at', now()->year)
            ->count();

        $completionRate = $totalEnrollments > 0 
            ? round(($completedEnrollments / $totalEnrollments) * 100, 2) 
            : 0;

        return [
            'total' => $totalEnrollments,
            'active' => $activeEnrollments,
            'completed' => $completedEnrollments,
            'new_this_month' => $newEnrollmentsThisMonth,
            'completion_rate' => $completionRate
        ];
    }

    /**
     * Get course statistics for the environment.
     *
     * @param  int  $environmentId
     * @return array
     */
    private function getCourseStats($environmentId)
    {
        $totalCourses = Course::where('environment_id', $environmentId)->count();
        
        $publishedCourses = Course::where('environment_id', $environmentId)
            ->where('status', 'published')
            ->count();
        
        $draftCourses = Course::where('environment_id', $environmentId)
            ->where('status', 'draft')
            ->count();
        
        $archivedCourses = Course::where('environment_id', $environmentId)
            ->where('status', 'archived')
            ->count();

        // Get most popular courses by enrollment count
        $popularCourses = Course::where('courses.environment_id', $environmentId)
            ->where('courses.status', 'published')
            ->select(
                'courses.id',
                'courses.title',
                'courses.slug',
                'courses.thumbnail_url',
                DB::raw('COUNT(enrollments.id) as enrollment_count')
            )
            ->leftJoin('enrollments', function ($join) use ($environmentId) {
                $join->on('courses.id', '=', 'enrollments.course_id')
                     ->where('enrollments.environment_id', '=', $environmentId);
            })
            ->groupBy('courses.id', 'courses.title', 'courses.slug', 'courses.thumbnail_url')
            ->orderBy('enrollment_count', 'desc')
            ->limit(5)
            ->get();

        return [
            'total' => $totalCourses,
            'published' => $publishedCourses,
            'draft' => $draftCourses,
            'archived' => $archivedCourses,
            'popular_courses' => $popularCourses
        ];
    }

    /**
     * Get certificate statistics for the environment.
     *
     * @param  int  $environmentId
     * @return array
     */
    private function getCertificateStats($environmentId)
    {
        $totalCertificates = IssuedCertificate::where('environment_id', $environmentId)->count();
        
        $activeCertificates = IssuedCertificate::where('environment_id', $environmentId)
            ->where('status', 'active')
            ->count();
        
        $expiredCertificates = IssuedCertificate::where('environment_id', $environmentId)
            ->where('status', 'expired')
            ->count();
        
        $revokedCertificates = IssuedCertificate::where('environment_id', $environmentId)
            ->where('status', 'revoked')
            ->count();

        $issuedThisMonth = IssuedCertificate::where('environment_id', $environmentId)
            ->whereMonth('issued_date', now()->month)
            ->whereYear('issued_date', now()->year)
            ->count();

        return [
            'total' => $totalCertificates,
            'active' => $activeCertificates,
            'expired' => $expiredCertificates,
            'revoked' => $revokedCertificates,
            'issued_this_month' => $issuedThisMonth
        ];
    }

    /**
     * Get enrollment trends for the environment.
     *
     * @param  int  $environmentId
     * @return array
     */
    private function getEnrollmentTrends($environmentId)
    {
        // Get monthly enrollments for the last 12 months
        $monthlyEnrollments = Enrollment::where('environment_id', $environmentId)
            ->where('enrolled_at', '>=', now()->subMonths(12))
            ->select(
                DB::raw('YEAR(enrolled_at) as year'),
                DB::raw('MONTH(enrolled_at) as month'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT),
                    'count' => $item->count
                ];
            });

        // Get monthly completions for the last 12 months
        $monthlyCompletions = Enrollment::where('environment_id', $environmentId)
            ->where('completed_at', '>=', now()->subMonths(12))
            ->whereNotNull('completed_at')
            ->select(
                DB::raw('YEAR(completed_at) as year'),
                DB::raw('MONTH(completed_at) as month'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT),
                    'count' => $item->count
                ];
            });

        return [
            'monthly_enrollments' => $monthlyEnrollments,
            'monthly_completions' => $monthlyCompletions
        ];
    }

    /**
     * Get completion rates by course for the environment.
     *
     * @param  int  $environmentId
     * @return array
     */
    private function getCompletionRates($environmentId)
    {
        $courseCompletionRates = Course::where('courses.environment_id', $environmentId)
            ->where('courses.status', 'published')
            ->select(
                'courses.id',
                'courses.title',
                DB::raw('COUNT(enrollments.id) as total_enrollments'),
                DB::raw('SUM(CASE WHEN enrollments.status = "completed" THEN 1 ELSE 0 END) as completed_enrollments')
            )
            ->leftJoin('enrollments', function ($join) use ($environmentId) {
                $join->on('courses.id', '=', 'enrollments.course_id')
                     ->where('enrollments.environment_id', '=', $environmentId);
            })
            ->groupBy('courses.id', 'courses.title')
            ->having('total_enrollments', '>', 0)
            ->get()
            ->map(function ($course) {
                $completionRate = round(($course->completed_enrollments / $course->total_enrollments) * 100, 2);
                return [
                    'id' => $course->id,
                    'title' => $course->title,
                    'total_enrollments' => $course->total_enrollments,
                    'completed_enrollments' => $course->completed_enrollments,
                    'completion_rate' => $completionRate
                ];
            })
            ->sortByDesc('completion_rate')
            ->values()
            ->take(10);

        // Convert collection to array to match the declared return type
    return $courseCompletionRates->toArray();
    }

    /**
     * Get user engagement analytics.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function userEngagement(Request $request)
    {
        // Get the environment ID from the authenticated user
        $environmentId = session('current_environment_id');
        
        if (!$environmentId) {
            return response()->json([
                'success' => false,
                'message' => 'No environment selected'
            ], 400);
        }

        // Get active users by day of week
        $activeUsersByDayOfWeek = Enrollment::where('environment_id', $environmentId)
            ->whereNotNull('last_activity_at')
            ->where('last_activity_at', '>=', now()->subDays(30))
            ->select(
                DB::raw('DAYOFWEEK(last_activity_at) as day_of_week'),
                DB::raw('COUNT(DISTINCT user_id) as user_count')
            )
            ->groupBy('day_of_week')
            ->orderBy('day_of_week')
            ->get()
            ->map(function ($item) {
                $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                return [
                    'day' => $dayNames[$item->day_of_week - 1],
                    'count' => $item->user_count
                ];
            });

        // Get active users by hour of day
        $activeUsersByHourOfDay = Enrollment::where('environment_id', $environmentId)
            ->whereNotNull('last_activity_at')
            ->where('last_activity_at', '>=', now()->subDays(30))
            ->select(
                DB::raw('HOUR(last_activity_at) as hour_of_day'),
                DB::raw('COUNT(DISTINCT user_id) as user_count')
            )
            ->groupBy('hour_of_day')
            ->orderBy('hour_of_day')
            ->get()
            ->map(function ($item) {
                return [
                    'hour' => $item->hour_of_day,
                    'count' => $item->user_count
                ];
            });

        // Get user retention data (users who enrolled and were active in the last 30 days)
        $retentionData = DB::table('enrollments')
            ->where('enrollments.environment_id', $environmentId)
            ->select(
                DB::raw('DATEDIFF(MAX(last_activity_at), enrolled_at) as days_active'),
                DB::raw('COUNT(DISTINCT user_id) as user_count')
            )
            ->whereNotNull('last_activity_at')
            ->where('enrolled_at', '<=', now()->subDays(30))
            ->groupBy(DB::raw('FLOOR(DATEDIFF(MAX(last_activity_at), enrolled_at) / 30)'))
            ->orderBy(DB::raw('FLOOR(DATEDIFF(MAX(last_activity_at), enrolled_at) / 30)'))
            ->get()
            ->map(function ($item) {
                $monthRange = floor($item->days_active / 30);
                return [
                    'month_range' => $monthRange == 0 ? '0-1 month' : 
                                    ($monthRange == 1 ? '1-2 months' : 
                                    ($monthRange == 2 ? '2-3 months' : '3+ months')),
                    'user_count' => $item->user_count
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'active_users_by_day' => $activeUsersByDayOfWeek,
                'active_users_by_hour' => $activeUsersByHourOfDay,
                'retention_data' => $retentionData
            ]
        ]);
    }

    /**
     * Get course analytics.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function courseAnalytics(Request $request)
    {
        // Get the environment ID from the authenticated user
        $environmentId = session('current_environment_id');
        
        if (!$environmentId) {
            return response()->json([
                'success' => false,
                'message' => 'No environment selected'
            ], 400);
        }

        // Get course ID from request if provided
        $courseId = $request->input('course_id');
        
        if ($courseId) {
            // Get analytics for a specific course
            $course = Course::where('id', $courseId)
                ->where('environment_id', $environmentId)
                ->first();
                
            if (!$course) {
                return response()->json([
                    'success' => false,
                    'message' => 'Course not found or does not belong to this environment'
                ], 404);
            }
            
            // Get enrollment statistics for this course
            $enrollmentStats = Enrollment::where('course_id', $courseId)
                ->where('environment_id', $environmentId)
                ->select(
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN status = "in-progress" THEN 1 ELSE 0 END) as active'),
                    DB::raw('SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed'),
                    DB::raw('AVG(progress_percentage) as avg_progress')
                )
                ->first();
                
            // Get average completion time in days
            $avgCompletionTime = Enrollment::where('course_id', $courseId)
                ->where('environment_id', $environmentId)
                ->whereNotNull('completed_at')
                ->select(DB::raw('AVG(DATEDIFF(completed_at, enrolled_at)) as avg_days'))
                ->first();
                
            // Get monthly enrollments for this course
            $monthlyEnrollments = Enrollment::where('course_id', $courseId)
                ->where('environment_id', $environmentId)
                ->where('enrolled_at', '>=', now()->subMonths(12))
                ->select(
                    DB::raw('YEAR(enrolled_at) as year'),
                    DB::raw('MONTH(enrolled_at) as month'),
                    DB::raw('COUNT(*) as count')
                )
                ->groupBy('year', 'month')
                ->orderBy('year')
                ->orderBy('month')
                ->get()
                ->map(function ($item) {
                    return [
                        'month' => $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT),
                        'count' => $item->count
                    ];
                });
                
            return response()->json([
                'success' => true,
                'data' => [
                    'course' => $course,
                    'enrollment_stats' => $enrollmentStats,
                    'avg_completion_time' => $avgCompletionTime ? $avgCompletionTime->avg_days : null,
                    'monthly_enrollments' => $monthlyEnrollments
                ]
            ]);
        } else {
            // Get analytics for all courses
            $courseAnalytics = Course::where('courses.environment_id', $environmentId)
                ->select(
                    'courses.id',
                    'courses.title',
                    'courses.status',
                    DB::raw('COUNT(enrollments.id) as enrollment_count'),
                    DB::raw('SUM(CASE WHEN enrollments.status = "completed" THEN 1 ELSE 0 END) as completion_count'),
                    DB::raw('AVG(enrollments.progress_percentage) as avg_progress')
                )
                ->leftJoin('enrollments', function ($join) use ($environmentId) {
                    $join->on('courses.id', '=', 'enrollments.course_id')
                         ->where('enrollments.environment_id', '=', $environmentId);
                })
                ->groupBy('courses.id', 'courses.title', 'courses.status')
                ->orderBy('enrollment_count', 'desc')
                ->get()
                ->map(function ($course) {
                    $completionRate = $course->enrollment_count > 0 
                        ? round(($course->completion_count / $course->enrollment_count) * 100, 2) 
                        : 0;
                    
                    return [
                        'id' => $course->id,
                        'title' => $course->title,
                        'status' => $course->status,
                        'enrollment_count' => $course->enrollment_count,
                        'completion_count' => $course->completion_count,
                        'completion_rate' => $completionRate,
                        'avg_progress' => round($course->avg_progress ?? 0, 2)
                    ];
                });
                
            return response()->json([
                'success' => true,
                'data' => $courseAnalytics
            ]);
        }
    }

    /**
     * Get certificate analytics.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function certificateAnalytics(Request $request)
    {
        // Get the environment ID from the authenticated user
        $environmentId = session('current_environment_id');
        
        if (!$environmentId) {
            return response()->json([
                'success' => false,
                'message' => 'No environment selected'
            ], 400);
        }

        // Get certificates issued by month
        $certificatesByMonth = IssuedCertificate::where('environment_id', $environmentId)
            ->where('issued_date', '>=', now()->subMonths(12))
            ->select(
                DB::raw('YEAR(issued_date) as year'),
                DB::raw('MONTH(issued_date) as month'),
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('year', 'month')
            ->orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(function ($item) {
                return [
                    'month' => $item->year . '-' . str_pad($item->month, 2, '0', STR_PAD_LEFT),
                    'count' => $item->count
                ];
            });

        // Get certificates by status
        $certificatesByStatus = IssuedCertificate::where('environment_id', $environmentId)
            ->select(
                'status',
                DB::raw('COUNT(*) as count')
            )
            ->groupBy('status')
            ->get();

        // Get top courses by certificates issued
        $topCoursesByCertificates = Course::where('courses.environment_id', $environmentId)
            ->select(
                'courses.id',
                'courses.title',
                DB::raw('COUNT(issued_certificates.id) as certificate_count')
            )
            ->leftJoin('issued_certificates', function ($join) use ($environmentId) {
                $join->on('courses.id', '=', 'issued_certificates.course_id')
                     ->where('issued_certificates.environment_id', '=', $environmentId);
            })
            ->groupBy('courses.id', 'courses.title')
            ->orderBy('certificate_count', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'certificates_by_month' => $certificatesByMonth,
                'certificates_by_status' => $certificatesByStatus,
                'top_courses_by_certificates' => $topCoursesByCertificates
            ]
        ]);
    }
}
