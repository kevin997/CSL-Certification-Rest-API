<?php

namespace App\Http\Controllers\Api\Learner;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TemplateController extends Controller
{
    /**
     * Get templates for courses that the learner is enrolled in
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $environmentId = $request->header('X-Environment-ID');
        
        // Get courses the user is enrolled in
        $enrolledCourseIds = Enrollment::where('user_id', $user->id)
            ->where('environment_id', $environmentId)
            ->pluck('course_id');
            
        // Get templates associated with those courses
        $templates = Template::whereHas('courses', function ($query) use ($enrolledCourseIds) {
                $query->whereIn('id', $enrolledCourseIds);
            })
            ->where('environment_id', $environmentId)
            ->with(['blocks' => function($query) {
                $query->orderBy('order');
                $query->with(['activities' => function($q) {
                    $q->orderBy('order');
                }]);
            }])
            ->orderBy('created_at', 'desc')
            ->paginate($request->input('per_page', 10));
        
        return response()->json([
            'status' => 'success',
            'data' => $templates,
        ]);
    }
    
    /**
     * Get a specific template
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        $user = Auth::user();
        $environmentId = $request->header('X-Environment-ID');
        
        // Check if user is enrolled in a course that uses this template
        $isEnrolled = Enrollment::where('user_id', $user->id)
            ->where('environment_id', $environmentId)
            ->whereHas('course', function ($query) use ($id) {
                $query->where('template_id', $id);
            })
            ->exists();
            
        if (!$isEnrolled) {
            return response()->json([
                'status' => 'error',
                'message' => 'You are not enrolled in a course that uses this template',
            ], 403);
        }
        
        $template = Template::where('id', $id)
            ->where('environment_id', $environmentId)
            ->with([
                'blocks' => function($query) {
                    $query->orderBy('order');
                    $query->with(['activities' => function($q) {
                        $q->orderBy('order');
                    }]);
                },
            ])
            ->firstOrFail();
        
        // Get enrollment and activity completions for progress tracking
        $enrollment = Enrollment::where('user_id', $user->id)
            ->whereHas('course', function ($query) use ($id) {
                $query->where('template_id', $id);
            })
            ->with('activityCompletions')
            ->first();
            
        if ($enrollment) {
            // Attach completion status to activities
            $completedActivityIds = $enrollment->activityCompletions
                ->where('completed', true)
                ->pluck('activity_id')
                ->toArray();
                
            foreach ($template->blocks as $block) {
                foreach ($block->activities as $activity) {
                    $activity->is_completed = in_array($activity->id, $completedActivityIds);
                    
                    // Find the specific completion record for additional data like score
                    $completionRecord = $enrollment->activityCompletions
                        ->where('activity_id', $activity->id)
                        ->first();
                        
                    if ($completionRecord) {
                        $activity->completion_data = $completionRecord;
                    }
                }
            }
        }
        
        return response()->json([
            'status' => 'success',
            'data' => $template,
        ]);
    }
}
