<?php

namespace App\Http\Controllers\Api\Learner;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseController extends Controller
{
    /**
     * Get all courses that the authenticated learner is enrolled in
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $environmentId = session('current_environment_id');

        // Optimized query with proper indexing and selective loading
        $courses = Course::select(['id', 'title', 'description', 'created_at', 'environment_id', 'template_id'])
            ->whereHas('enrollments', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('environment_id', $environmentId)
            ->with([
                'enrollments' => function ($query) use ($user) {
                    $query->select(['id', 'course_id', 'user_id', 'enrolled_at', 'progress_percentage'])
                        ->where('user_id', $user->id);
                },
                'template' => function ($query) {
                    $query->select(['id', 'title', 'description']);
                },
            ])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 10));

        return response()->json([
            'status' => 'success',
            'data' => $courses,
        ]);
    }
    
    /**
     * Get a specific course that the learner is enrolled in
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        $user = Auth::user();
        $environmentId = session('current_environment_id');

        // Optimized single query with caching considerations
        $course = Course::select(['id', 'title', 'description', 'environment_id', 'template_id'])
            ->where('id', $id)
            ->where('environment_id', $environmentId)
            ->whereHas('enrollments', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with([
                'enrollments' => function ($query) use ($user) {
                    $query->select(['id', 'course_id', 'user_id', 'enrolled_at', 'progress_percentage'])
                        ->where('user_id', $user->id)
                        ->with([
                            'activityCompletions' => function ($q) {
                                $q->select(['id', 'enrollment_id', 'activity_id', 'completed_at']);
                            }
                        ]);
                },
                'template' => function($query) {
                    $query->select(['id', 'title', 'description'])
                        ->with(['blocks' => function($q) {
                            $q->select(['id', 'template_id', 'title', 'order'])
                                ->orderBy('order')
                                ->with(['activities' => function($a) {
                                    $a->select(['id', 'block_id', 'title', 'type', 'order'])
                                        ->orderBy('order');
                                }]);
                        }]);
                }
            ])
            ->firstOrFail();

        // Optimized progress calculation using collection methods
        $enrollment = $course->enrollments->first();
        $activityCount = 0;
        $completedCount = 0;

        if ($course->template && $course->template->blocks) {
            $activityCount = $course->template->blocks->sum(function ($block) {
                return $block->activities ? $block->activities->count() : 0;
            });
        }

        if ($enrollment && $enrollment->activityCompletions) {
            $completedCount = $enrollment->activityCompletions->count();
        }

        $progressPercentage = $activityCount > 0 ? round(($completedCount / $activityCount) * 100, 2) : 0;
        
        // Update enrollment progress
        if ($enrollment) {
            $enrollment->progress_percentage = $progressPercentage;
            $enrollment->last_activity_at = now();
            $enrollment->save();
        }
        
        $course->progress = [
            'total_activities' => $activityCount,
            'completed_activities' => $completedCount,
            'percentage' => $progressPercentage,
        ];
        
        return response()->json([
            'status' => 'success',
            'data' => $course,
        ]);
    }
}
