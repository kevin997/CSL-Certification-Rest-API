<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityCompletion;
use App\Models\Enrollment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Schema(
 *     schema="ActivityCompletion",
 *     required={"enrollment_id", "activity_id", "status"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="enrollment_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="activity_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="status", type="string", enum={"started", "in_progress", "completed", "failed"}, example="completed"),
 *     @OA\Property(property="score", type="number", format="float", example=85, nullable=true),
 *     @OA\Property(property="time_spent", type="integer", example=1200, description="Time spent in seconds", nullable=true),
 *     @OA\Property(property="attempts", type="integer", example=2, nullable=true),
 *     @OA\Property(property="completed_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(
 *         property="activity",
 *         type="object",
 *         @OA\Property(property="id", type="integer", format="int64", example=1),
 *         @OA\Property(property="title", type="string", example="Introduction to Certification"),
 *         @OA\Property(property="type", type="string", example="quiz")
 *     )
 * )
 */

/**
 * @OA\Get(
 *     path="/api/enrollments/{enrollmentId}/activity-completions",
 *     summary="Get all activity completions for an enrollment",
 *     description="Returns a list of all activity completions for a specific enrollment",
 *     operationId="getEnrollmentActivityCompletions",
 *     tags={"Activity Completions"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="enrollmentId",
 *         in="path",
 *         description="Enrollment ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Successful operation",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(
 *                 property="data",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="id", type="integer", format="int64", example=1),
 *                     @OA\Property(property="enrollment_id", type="integer", format="int64", example=1),
 *                     @OA\Property(property="activity_id", type="integer", format="int64", example=1),
 *                     @OA\Property(property="status", type="string", enum={"started", "in_progress", "completed", "failed"}, example="completed"),
 *                     @OA\Property(property="score", type="number", format="float", example=85, nullable=true),
 *                     @OA\Property(property="time_spent", type="integer", example=1200, description="Time spent in seconds", nullable=true),
 *                     @OA\Property(property="attempts", type="integer", example=2, nullable=true),
 *                     @OA\Property(property="completed_at", type="string", format="date-time", nullable=true),
 *                     @OA\Property(property="created_at", type="string", format="date-time"),
 *                     @OA\Property(property="updated_at", type="string", format="date-time"),
 *                     @OA\Property(
 *                         property="activity",
 *                         type="object",
 *                         @OA\Property(property="id", type="integer", format="int64", example=1),
 *                         @OA\Property(property="title", type="string", example="Introduction to Certification"),
 *                         @OA\Property(property="type", type="string", example="quiz")
 *                     )
 *                 )
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
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Enrollment not found"
 *     )
 * )
 *
 * @OA\Put(
 *     path="/api/enrollments/{enrollmentId}/activities/{activityId}/completion",
 *     summary="Update activity completion status",
 *     description="Updates the status of an activity completion for a specific enrollment and activity",
 *     operationId="updateActivityCompletion",
 *     tags={"Activity Completions"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="enrollmentId",
 *         in="path",
 *         description="Enrollment ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="activityId",
 *         in="path",
 *         description="Activity ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             required={"status"},
 *             @OA\Property(property="status", type="string", enum={"started", "in_progress", "completed", "failed"}, example="completed"),
 *             @OA\Property(property="score", type="number", format="float", example=85, nullable=true),
 *             @OA\Property(property="time_spent", type="integer", example=1200, description="Time spent in seconds", nullable=true),
 *             @OA\Property(property="attempts", type="integer", example=2, nullable=true)
 *         )
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Activity completion updated successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Activity completion updated successfully"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="id", type="integer", format="int64", example=1),
 *                 @OA\Property(property="enrollment_id", type="integer", format="int64", example=1),
 *                 @OA\Property(property="activity_id", type="integer", format="int64", example=1),
 *                 @OA\Property(property="status", type="string", enum={"started", "in_progress", "completed", "failed"}, example="completed"),
 *                 @OA\Property(property="score", type="number", format="float", example=85, nullable=true),
 *                 @OA\Property(property="time_spent", type="integer", example=1200, description="Time spent in seconds", nullable=true),
 *                 @OA\Property(property="attempts", type="integer", example=2, nullable=true),
 *                 @OA\Property(property="completed_at", type="string", format="date-time"),
 *                 @OA\Property(property="created_at", type="string", format="date-time"),
 *                 @OA\Property(property="updated_at", type="string", format="date-time")
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
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Enrollment or Activity not found"
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Validation error"
 *     )
 * )
 *
 * @OA\Get(
 *     path="/api/enrollments/{enrollmentId}/progress",
 *     summary="Get enrollment progress",
 *     description="Returns the progress information for a specific enrollment",
 *     operationId="getEnrollmentProgress",
 *     tags={"Activity Completions"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="enrollmentId",
 *         in="path",
 *         description="Enrollment ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Successful operation",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="total_activities", type="integer", example=10),
 *                 @OA\Property(property="completed_activities", type="integer", example=5),
 *                 @OA\Property(property="required_activities", type="integer", example=8),
 *                 @OA\Property(property="completed_required_activities", type="integer", example=4),
 *                 @OA\Property(property="progress_percentage", type="number", format="float", example=50),
 *                 @OA\Property(property="required_progress_percentage", type="number", format="float", example=50)
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
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Enrollment not found"
 *     )
 * )
 *
 * @OA\Post(
 *     path="/api/enrollments/{enrollmentId}/activities/{activityId}/reset",
 *     summary="Reset activity completion",
 *     description="Resets the completion status of a specific activity for an enrollment",
 *     operationId="resetActivityCompletion",
 *     tags={"Activity Completions"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="enrollmentId",
 *         in="path",
 *         description="Enrollment ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Parameter(
 *         name="activityId",
 *         in="path",
 *         description="Activity ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Activity completion reset successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="Activity completion reset successfully"),
 *             @OA\Property(
 *                 property="data",
 *                 type="object",
 *                 @OA\Property(property="id", type="integer", format="int64", example=1),
 *                 @OA\Property(property="enrollment_id", type="integer", format="int64", example=1),
 *                 @OA\Property(property="activity_id", type="integer", format="int64", example=1),
 *                 @OA\Property(property="status", type="string", example="started"),
 *                 @OA\Property(property="score", type="null"),
 *                 @OA\Property(property="time_spent", type="null"),
 *                 @OA\Property(property="attempts", type="integer", example=0),
 *                 @OA\Property(property="completed_at", type="null"),
 *                 @OA\Property(property="created_at", type="string", format="date-time"),
 *                 @OA\Property(property="updated_at", type="string", format="date-time")
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
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Enrollment or Activity not found"
 *     )
 * )
 *
 * @OA\Post(
 *     path="/api/enrollments/{enrollmentId}/reset-all",
 *     summary="Reset all activity completions",
 *     description="Resets all activity completions for a specific enrollment",
 *     operationId="resetAllActivityCompletions",
 *     tags={"Activity Completions"},
 *     security={{"sanctum":{}}},
 *     @OA\Parameter(
 *         name="enrollmentId",
 *         in="path",
 *         description="Enrollment ID",
 *         required=true,
 *         @OA\Schema(type="integer")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="All activity completions reset successfully",
 *         @OA\JsonContent(
 *             @OA\Property(property="status", type="string", example="success"),
 *             @OA\Property(property="message", type="string", example="All activity completions reset successfully"),
 *             @OA\Property(property="data", type="object")
 *         )
 *     ),
 *     @OA\Response(
 *         response=401,
 *         description="Unauthenticated"
 *     ),
 *     @OA\Response(
 *         response=403,
 *         description="Forbidden"
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Enrollment not found"
 *     )
 * )
 */

class ActivityCompletionController extends Controller
{
    /**
     * Display a listing of activity completions for a specific enrollment.
     *
     * @param  int  $enrollmentId
     * @return \Illuminate\Http\Response
     */
    public function index($enrollmentId)
    {
        $enrollment = Enrollment::findOrFail($enrollmentId);
        
        // Check if user has permission to view this enrollment's activity completions
        if ($enrollment->user_id !== Auth::id() && !Auth::user()->is_admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to view activity completions for this enrollment',
            ], Response::HTTP_FORBIDDEN);
        }

        $activityCompletions = ActivityCompletion::where('enrollment_id', $enrollmentId)
            ->with('activity')
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $activityCompletions,
        ]);
    }

    /**
     * Update the specified activity completion status.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $enrollmentId
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $enrollmentId, $activityId)
    {
        $enrollment = Enrollment::findOrFail($enrollmentId);
        
        // Check if user has permission to update this enrollment's activity completion
        if ($enrollment->user_id !== Auth::id() && !Auth::user()->is_admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to update activity completions for this enrollment',
            ], Response::HTTP_FORBIDDEN);
        }

        // Validate the activity exists
        $activity = Activity::findOrFail($activityId);
        
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:started,in_progress,completed,failed',
            'score' => 'nullable|numeric|min:0',
            'time_spent' => 'nullable|integer|min:0',
            'attempts' => 'nullable|integer|min:1',
            'completed_at' => 'nullable|date',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Find or create activity completion record
        $activityCompletion = ActivityCompletion::firstOrNew([
            'enrollment_id' => $enrollmentId,
            'activity_id' => $activityId,
        ]);

        // Update activity completion fields
        $activityCompletion->status = $request->status;
        
        if ($request->has('score')) {
            $activityCompletion->score = $request->score;
        }
        
        if ($request->has('time_spent')) {
            $activityCompletion->time_spent = $request->time_spent;
        }
        
        if ($request->has('attempts')) {
            $activityCompletion->attempts = $request->attempts;
        }
        
        if ($request->status === 'completed' && !$activityCompletion->completed_at) {
            $activityCompletion->completed_at = now();
        } elseif ($request->has('completed_at')) {
            $activityCompletion->completed_at = $request->completed_at;
        }
        
        $activityCompletion->save();

        // Check if all required activities are completed to update enrollment status
        $this->checkCourseCompletion($enrollment);

        return response()->json([
            'status' => 'success',
            'message' => 'Activity completion updated successfully',
            'data' => $activityCompletion,
        ]);
    }

    /**
     * Get activity completion progress for a specific enrollment.
     *
     * @param  int  $enrollmentId
     * @return \Illuminate\Http\Response
     */
    public function progress($enrollmentId)
    {
        $enrollment = Enrollment::findOrFail($enrollmentId);
        
        // Check if user has permission to view this enrollment's progress
        if ($enrollment->user_id !== Auth::id() && !Auth::user()->is_admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to view progress for this enrollment',
            ], Response::HTTP_FORBIDDEN);
        }

        // Get course and its activities
        $course = $enrollment->course;
        
        // Get all activities for the course
        $courseActivities = Activity::whereHas('block', function ($query) use ($course) {
            $query->whereHas('template', function ($q) use ($course) {
                $q->where('id', $course->template_id);
            });
        })->get();
        
        $totalActivities = $courseActivities->count();
        $completedActivities = 0;
        $totalPoints = 0;
        $earnedPoints = 0;

        // Get all activity completions for this enrollment
        $completions = ActivityCompletion::where('enrollment_id', $enrollmentId)->get();
        
        foreach ($courseActivities as $activity) {
            // Add points if available
            $totalPoints += $activity->points ?? 0;
            
            // Check if activity is completed
            $completion = $completions->firstWhere('activity_id', $activity->id);
            
            if ($completion && $completion->status === 'completed') {
                $completedActivities++;
                $earnedPoints += $activity->points ?? 0;
            }
        }

        // Calculate progress percentages
        $overallProgress = $totalActivities > 0 ? ($completedActivities / $totalActivities) * 100 : 0;
        $pointsProgress = $totalPoints > 0 ? ($earnedPoints / $totalPoints) * 100 : 0;

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_activities' => $totalActivities,
                'completed_activities' => $completedActivities,
                'overall_progress' => round($overallProgress, 2),
                'total_points' => $totalPoints,
                'earned_points' => $earnedPoints,
                'points_progress' => round($pointsProgress, 2),
                'enrollment_status' => $enrollment->status,
                'last_activity_at' => $completions->max('updated_at'),
            ],
        ]);
    }

    /**
     * Reset activity completion for a specific activity.
     *
     * @param  int  $enrollmentId
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     */
    public function reset($enrollmentId, $activityId)
    {
        $enrollment = Enrollment::findOrFail($enrollmentId);
        
        // Check if user has permission to reset this enrollment's activity completion
        if (!Auth::user()->is_admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to reset activity completions',
            ], Response::HTTP_FORBIDDEN);
        }

        // Find activity completion record
        $activityCompletion = ActivityCompletion::where('enrollment_id', $enrollmentId)
            ->where('activity_id', $activityId)
            ->firstOrFail();

        // Reset activity completion
        $activityCompletion->status = 'started';
        $activityCompletion->score = null;
        $activityCompletion->completed_at = null;
        $activityCompletion->save();

        // Update enrollment status if needed
        if ($enrollment->status === 'completed') {
            $enrollment->status = 'active';
            $enrollment->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Activity completion reset successfully',
            'data' => $activityCompletion,
        ]);
    }

    /**
     * Reset all activity completions for a specific enrollment.
     *
     * @param  int  $enrollmentId
     * @return \Illuminate\Http\Response
     */
    public function resetAll($enrollmentId)
    {
        $enrollment = Enrollment::findOrFail($enrollmentId);
        
        // Check if user has permission to reset all activity completions
        if (!Auth::user()->is_admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to reset all activity completions',
            ], Response::HTTP_FORBIDDEN);
        }

        // Reset all activity completions
        ActivityCompletion::where('enrollment_id', $enrollmentId)
            ->update([
                'status' => 'started',
                'score' => null,
                'completed_at' => null,
            ]);

        // Update enrollment status
        $enrollment->status = 'active';
        $enrollment->save();

        return response()->json([
            'status' => 'success',
            'message' => 'All activity completions reset successfully',
        ]);
    }

    /**
     * Check if all required activities are completed to update enrollment status.
     *
     * @param  \App\Models\Enrollment  $enrollment
     * @return void
     */
    private function checkCourseCompletion($enrollment)
    {
        $course = $enrollment->course;
        
        // Get all activities for the course
        $courseActivities = Activity::whereHas('block', function ($query) use ($course) {
            $query->whereHas('template', function ($q) use ($course) {
                $q->where('id', $course->template_id);
            });
        })->where('is_required', true)->get();
        
        // If there are no required activities, don't update enrollment status
        if ($courseActivities->isEmpty()) {
            return;
        }
        
        $allRequiredCompleted = true;
        
        // Get all activity completions for this enrollment
        $completions = ActivityCompletion::where('enrollment_id', $enrollment->id)->get();
        
        foreach ($courseActivities as $activity) {
            $completion = $completions->firstWhere('activity_id', $activity->id);
            
            if (!$completion || $completion->status !== 'completed') {
                $allRequiredCompleted = false;
                break;
            }
        }

        // Update enrollment status if all required activities are completed
        if ($allRequiredCompleted && $enrollment->status === 'active') {
            $enrollment->status = 'completed';
            $enrollment->save();
        }
    }
}
