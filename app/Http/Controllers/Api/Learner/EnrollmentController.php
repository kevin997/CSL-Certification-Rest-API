<?php

namespace App\Http\Controllers\Api\Learner;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\ActivityCompletion;
use App\Models\Activity;
use App\Models\VideoContent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnrollmentController extends Controller
{
    /**
     * Get all enrollments for the authenticated learner
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $environmentId = session('current_environment_id');
        
        $enrollments = Enrollment::where('user_id', $user->id)
            ->where('environment_id', $environmentId)
            ->with(['course', 'activityCompletions'])
            ->orderBy('enrolled_at', 'desc')
            ->paginate($request->input('per_page', 10));
        
        return response()->json([
            'status' => 'success',
            'data' => $enrollments,
        ]);
    }
    
    /**
     * Get a specific enrollment
     * 
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        $user = Auth::user();
        $environmentId = session('current_environment_id');
        
        $enrollment = Enrollment::where('id', $id)
            ->where('user_id', $user->id)
            ->where('environment_id', $environmentId)
            ->with(['course', 'activityCompletions'])
            ->firstOrFail();
        
        return response()->json([
            'status' => 'success',
            'data' => $enrollment,
        ]);
    }
    
    /**
     * Update activity completion status
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateActivityCompletion(Request $request)
    {
        $user = Auth::user();
        $environmentId = session('current_environment_id');
        
        $request->validate([
            'enrollment_id' => 'required|exists:enrollments,id',
            'activity_id' => 'required|exists:activities,id',
            'completed' => 'required|boolean',
            'score' => 'nullable|numeric|min:0|max:100',
            'time_spent' => 'nullable|numeric|min:0',
            'video_content_id' => 'nullable|integer|exists:video_contents,id',
            'submission_data' => 'nullable|json',
        ]);
        
        // Verify that the enrollment belongs to the user
        $enrollment = Enrollment::where('id', $request->input('enrollment_id'))
            ->where('user_id', $user->id)
            ->where('environment_id', $environmentId)
            ->firstOrFail();
        
        $activity = Activity::findOrFail($request->input('activity_id'));
        $progressPercentage = $request->input('completed') ? 100 : 0;
        $completionData = null;
        $isVideoSegmentUpdate = $activity->type->value === 'video' && $request->filled('video_content_id');

        if ($isVideoSegmentUpdate) {
            $video = VideoContent::where('activity_id', $activity->id)
                ->findOrFail($request->input('video_content_id'));

            $existingCompletion = ActivityCompletion::where('enrollment_id', $enrollment->id)
                ->where('activity_id', $activity->id)
                ->first();

            $completionData = $existingCompletion?->completion_data ?? [];
            $completedVideoIds = collect($completionData['completed_video_ids'] ?? [])
                ->push($video->id)
                ->unique()
                ->values()
                ->all();

            $totalVideos = max(VideoContent::where('activity_id', $activity->id)->count(), 1);
            $progressPercentage = round((count($completedVideoIds) / $totalVideos) * 100, 2);
            $completionData = [
                'completed_video_ids' => $completedVideoIds,
                'total_videos' => $totalVideos,
                'completed_videos' => count($completedVideoIds),
            ];
        }

        $isCompleted = $isVideoSegmentUpdate
            ? $progressPercentage >= 100
            : $request->input('completed');

        // Find or create activity completion record
        $activityCompletion = ActivityCompletion::updateOrCreate(
            [
                'enrollment_id' => $enrollment->id,
                'activity_id' => $request->input('activity_id'),
            ],
            [
                'status' => $isCompleted ? 'completed' : 'in-progress',
                'progress_percentage' => $progressPercentage,
                'completion_data' => $completionData,
                'score' => $request->input('score'),
                'time_spent' => $request->input('time_spent'),
                //'submission_data' => $request->input('submission_data'),
                'completed_at' => $isCompleted ? now() : null,
            ]
        );
        
        // Update enrollment progress
        $this->updateEnrollmentProgress($enrollment);
        
        return response()->json([
            'status' => 'success',
            'data' => $activityCompletion,
            'enrollment' => $enrollment->fresh(),
        ]);
    }
    
    /**
     * Get activity completions for a specific enrollment
     * 
     * @param Request $request
     * @param int $enrollmentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActivityCompletions(Request $request, $enrollmentId)
    {
        $user = Auth::user();
        $environmentId = session('current_environment_id');
        
        // Verify that the enrollment belongs to the user
        $enrollment = Enrollment::where('id', $enrollmentId)
            ->where('user_id', $user->id)
            ->where('environment_id', $environmentId)
            ->firstOrFail();
        
        $activityCompletions = ActivityCompletion::where('enrollment_id', $enrollment->id)
            ->get();
        
        return response()->json([
            'status' => 'success',
            'data' => $activityCompletions,
        ]);
    }

    /**
     * Get a specific activity completion for an enrollment
     * 
     * @param Request $request
     * @param int $enrollmentId
     * @param int $activityId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getActivityCompletion(Request $request, $enrollmentId, $activityId)
    {
        $user = Auth::user();
        $environmentId = session('current_environment_id');
        
        // Verify that the enrollment belongs to the user
        $enrollment = Enrollment::where('id', $enrollmentId)
            ->where('user_id', $user->id)
            ->where('environment_id', $environmentId)
            ->firstOrFail();
        
        // Find the activity completion record
        $activityCompletion = ActivityCompletion::where('enrollment_id', $enrollment->id)
            ->where('activity_id', $activityId)
            ->first();
        
        if (!$activityCompletion) {
            // Return empty completion data if not found
            return response()->json([
                'status' => 'success',
                'data' => [
                    'enrollment_id' => $enrollmentId,
                    'activity_id' => $activityId,
                    'completed' => false,
                    'score' => 0,
                    'time_spent' => 0,
                    'completed_at' => null,
                ],
            ]);
        }
        
        return response()->json([
            'status' => 'success',
            'data' => $activityCompletion,
        ]);
    }
    
    /**
     * Reset a specific activity completion for an enrollment
     * 
     * @param Request $request
     * @param int $enrollmentId
     * @param int $activityId
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetActivityCompletion(Request $request, $enrollmentId, $activityId)
    {
        $user = Auth::user();
        $environmentId = session('current_environment_id');
        
        // Verify that the enrollment belongs to the user
        $enrollment = Enrollment::where('id', $enrollmentId)
            ->where('user_id', $user->id)
            ->where('environment_id', $environmentId)
            ->firstOrFail();
        
        // Find and delete the activity completion record
        $activityCompletion = ActivityCompletion::where('enrollment_id', $enrollment->id)
            ->where('activity_id', $activityId)
            ->first();
        
        if ($activityCompletion) {
            // Reset the completion status
            $activityCompletion->update([
                'status' => 'in-progress',
                'progress_percentage' => 0,
                'completion_data' => null,
                'score' => 0,
                'time_spent' => 0,
                'completed_at' => null,
            ]);
            
            // Update enrollment progress
            $this->updateEnrollmentProgress($enrollment);
            
            return response()->json([
                'status' => 'success',
                'message' => 'Activity completion reset successfully',
                'data' => $activityCompletion,
                'enrollment' => $enrollment->fresh(),
            ]);
        }
        
        return response()->json([
            'status' => 'success',
            'message' => 'No activity completion found to reset',
        ]);
    }
    
    
    /**
     * Update enrollment progress based on activity completions
     * 
     * @param Enrollment $enrollment
     * @return void
     */
    private function updateEnrollmentProgress(Enrollment $enrollment)
    {
        // Get all activities in the course
        $course = $enrollment->course;
        $template = $course->template;
        
        if (!$template) {
            return;
        }
        
        $activityCount = 0;
        $template->load(['blocks.activities']);
        
        foreach ($template->blocks as $block) {
            $activityCount += $block->activities->count();
        }
        
        // Get completed activities
        $completedCount = ActivityCompletion::where('enrollment_id', $enrollment->id)
            ->get()
            ->sum(function (ActivityCompletion $completion) {
                if ($completion->status === 'completed') {
                    return 1;
                }

                return min(max((float) $completion->progress_percentage, 0), 100) / 100;
            });
        
        // Calculate progress percentage
        $progressPercentage = $activityCount > 0 ? ($completedCount / $activityCount) * 100 : 0;
        
        // Update enrollment
        $enrollment->progress_percentage = $progressPercentage;
        $enrollment->last_activity_at = now();
        
        // If all activities are completed, mark the course as completed
        if ($progressPercentage >= 100) {
            $enrollment->status = 'completed';
            $enrollment->completed_at = now();
        } else {
            $enrollment->status = 'in-progress';
        }
        
        $enrollment->save();
    }
}
