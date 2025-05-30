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
        
        $courses = Course::whereHas('enrollments', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->where('environment_id', $environmentId)
            ->with([
                'enrollments' => function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                },
                'template:id,title,description',
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
        
        $course = Course::where('id', $id)
            ->where('environment_id', $environmentId)
            ->whereHas('enrollments', function ($query) use ($user) {
                $query->where('user_id', $user->id);
            })
            ->with([
                'enrollments' => function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                },
                'template' => function($query) {
                    $query->with(['blocks' => function($q) {
                        $q->orderBy('order')->with(['activities' => function($a) {
                            $a->orderBy('order');
                        }]);
                    }]);
                }
            ])
            ->firstOrFail();
        
        // Get activity completions for progress tracking
        $enrollment = Enrollment::where('course_id', $id)
            ->where('user_id', $user->id)
            ->with('activityCompletions')
            ->first();
            
        // Calculate progress
        $activityCount = 0;
        $completedCount = 0;
        
        if ($course->template && $course->template->blocks) {
            foreach ($course->template->blocks as $block) {
                if ($block->activities) {
                    $activityCount += count($block->activities);
                }
            }
        }
        
        if ($enrollment && $enrollment->activityCompletions) {
            $completedCount = $enrollment->activityCompletions->count();
        }
        
        $progressPercentage = $activityCount > 0 ? ($completedCount / $activityCount) * 100 : 0;
        
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
