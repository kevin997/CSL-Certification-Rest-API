<?php

namespace App\Services;

use App\Models\ActivityCompletion;
use App\Models\Enrollment;
use App\Models\Activity;
use App\Models\Course;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProgressTrackingService extends Service
{
    /**
     * Get activity completion by ID
     *
     * @param int $id
     * @return ActivityCompletion|null
     */
    public function getCompletionById(int $id): ?ActivityCompletion
    {
        return ActivityCompletion::find($id);
    }
    
    /**
     * Get all completions for an enrollment
     *
     * @param int $enrollmentId
     * @return Collection
     */
    public function getEnrollmentCompletions(int $enrollmentId): Collection
    {
        return ActivityCompletion::where('enrollment_id', $enrollmentId)->get();
    }
    
    /**
     * Get completion for a specific activity in an enrollment
     *
     * @param int $enrollmentId
     * @param int $activityId
     * @return ActivityCompletion|null
     */
    public function getActivityCompletion(int $enrollmentId, int $activityId): ?ActivityCompletion
    {
        return ActivityCompletion::where('enrollment_id', $enrollmentId)
            ->where('activity_id', $activityId)
            ->first();
    }
    
    /**
     * Mark an activity as completed
     *
     * @param int $enrollmentId
     * @param int $activityId
     * @param array $data Additional completion data
     * @return ActivityCompletion
     */
    public function markActivityCompleted(int $enrollmentId, int $activityId, array $data = []): ActivityCompletion
    {
        // Set default data
        $completionData = array_merge([
            'enrollment_id' => $enrollmentId,
            'activity_id' => $activityId,
            'status' => 'completed',
            'completed_at' => now()
        ], $data);
        
        // Create or update completion record
        $completion = ActivityCompletion::updateOrCreate(
            [
                'enrollment_id' => $enrollmentId,
                'activity_id' => $activityId
            ],
            $completionData
        );
        
        // Check if all required activities are completed
        $this->checkCourseCompletion($enrollmentId);
        
        return $completion;
    }
    
    /**
     * Mark an activity as in progress
     *
     * @param int $enrollmentId
     * @param int $activityId
     * @param array $data Additional completion data
     * @return ActivityCompletion
     */
    public function markActivityInProgress(int $enrollmentId, int $activityId, array $data = []): ActivityCompletion
    {
        // Set default data
        $completionData = array_merge([
            'enrollment_id' => $enrollmentId,
            'activity_id' => $activityId,
            'status' => 'in_progress',
            'completed_at' => null
        ], $data);
        
        // Create or update completion record
        return ActivityCompletion::updateOrCreate(
            [
                'enrollment_id' => $enrollmentId,
                'activity_id' => $activityId
            ],
            $completionData
        );
    }
    
    /**
     * Mark an activity as failed
     *
     * @param int $enrollmentId
     * @param int $activityId
     * @param array $data Additional completion data
     * @return ActivityCompletion
     */
    public function markActivityFailed(int $enrollmentId, int $activityId, array $data = []): ActivityCompletion
    {
        // Set default data
        $completionData = array_merge([
            'enrollment_id' => $enrollmentId,
            'activity_id' => $activityId,
            'status' => 'failed',
            'completed_at' => null
        ], $data);
        
        // Create or update completion record
        return ActivityCompletion::updateOrCreate(
            [
                'enrollment_id' => $enrollmentId,
                'activity_id' => $activityId
            ],
            $completionData
        );
    }
    
    /**
     * Reset an activity completion
     *
     * @param int $enrollmentId
     * @param int $activityId
     * @return bool
     */
    public function resetActivityCompletion(int $enrollmentId, int $activityId): bool
    {
        return ActivityCompletion::where('enrollment_id', $enrollmentId)
            ->where('activity_id', $activityId)
            ->delete();
    }
    
    /**
     * Check if all required activities are completed and update enrollment status
     *
     * @param int $enrollmentId
     * @return bool
     */
    public function checkCourseCompletion(int $enrollmentId): bool
    {
        $enrollment = Enrollment::with(['course.sections.activities'])->find($enrollmentId);
        
        if (!$enrollment) {
            return false;
        }
        
        $course = $enrollment->course;
        $completions = $this->getEnrollmentCompletions($enrollmentId);
        
        // Get all required activities
        $requiredActivities = collect();
        
        foreach ($course->sections as $section) {
            $requiredActivities = $requiredActivities->merge(
                $section->activities->where('is_required', true)
            );
        }
        
        // Check if all required activities are completed
        $allRequiredCompleted = true;
        
        foreach ($requiredActivities as $activity) {
            $completion = $completions->where('activity_id', $activity->id)->first();
            
            if (!$completion || $completion->status !== 'completed') {
                $allRequiredCompleted = false;
                break;
            }
        }
        
        // Update enrollment status if all required activities are completed
        if ($allRequiredCompleted && $enrollment->status === 'active') {
            $enrollment->update([
                'status' => 'completed',
                'completion_date' => now()
            ]);
            
            return true;
        }
        
        return $allRequiredCompleted;
    }
    
    /**
     * Calculate progress percentage for an enrollment
     *
     * @param int $enrollmentId
     * @return array
     */
    public function calculateProgress(int $enrollmentId): array
    {
        $enrollment = Enrollment::with(['course.sections.activities'])->find($enrollmentId);
        
        if (!$enrollment) {
            return [
                'success' => false,
                'message' => 'Enrollment not found'
            ];
        }
        
        $course = $enrollment->course;
        $completions = $this->getEnrollmentCompletions($enrollmentId);
        
        // Count total activities and required activities
        $totalActivities = 0;
        $totalRequiredActivities = 0;
        $completedActivities = 0;
        $completedRequiredActivities = 0;
        
        foreach ($course->sections as $section) {
            foreach ($section->activities as $activity) {
                $totalActivities++;
                
                if ($activity->is_required) {
                    $totalRequiredActivities++;
                }
                
                $completion = $completions->where('activity_id', $activity->id)->first();
                
                if ($completion && $completion->status === 'completed') {
                    $completedActivities++;
                    
                    if ($activity->is_required) {
                        $completedRequiredActivities++;
                    }
                }
            }
        }
        
        // Calculate percentages
        $overallProgress = $totalActivities > 0 ? 
            round(($completedActivities / $totalActivities) * 100) : 0;
            
        $requiredProgress = $totalRequiredActivities > 0 ? 
            round(($completedRequiredActivities / $totalRequiredActivities) * 100) : 100;
            
        return [
            'success' => true,
            'progress' => [
                'total_activities' => $totalActivities,
                'completed_activities' => $completedActivities,
                'overall_progress' => $overallProgress,
                'required_activities' => $totalRequiredActivities,
                'completed_required_activities' => $completedRequiredActivities,
                'required_progress' => $requiredProgress,
                'is_completed' => $requiredProgress === 100
            ]
        ];
    }
    
    /**
     * Track time spent on an activity
     *
     * @param int $enrollmentId
     * @param int $activityId
     * @param int $seconds
     * @return ActivityCompletion
     */
    public function trackTimeSpent(int $enrollmentId, int $activityId, int $seconds): ActivityCompletion
    {
        $completion = ActivityCompletion::firstOrNew([
            'enrollment_id' => $enrollmentId,
            'activity_id' => $activityId
        ]);
        
        // Update time spent
        $completion->time_spent = ($completion->time_spent ?? 0) + $seconds;
        $completion->save();
        
        return $completion;
    }
    
    /**
     * Get activity analytics for a course
     *
     * @param int $courseId
     * @return array
     */
    public function getCourseActivityAnalytics(int $courseId): array
    {
        $course = Course::with(['sections.activities'])->find($courseId);
        
        if (!$course) {
            return [
                'success' => false,
                'message' => 'Course not found'
            ];
        }
        
        $enrollments = Enrollment::where('course_id', $courseId)->get();
        $enrollmentIds = $enrollments->pluck('id')->toArray();
        
        $activityAnalytics = [];
        
        foreach ($course->sections as $section) {
            foreach ($section->activities as $activity) {
                $completions = ActivityCompletion::whereIn('enrollment_id', $enrollmentIds)
                    ->where('activity_id', $activity->id)
                    ->get();
                
                $totalCompletions = $completions->where('status', 'completed')->count();
                $totalAttempts = $completions->sum('attempts') ?? 0;
                $averageScore = $completions->avg('score') ?? 0;
                $averageTimeSpent = $completions->avg('time_spent') ?? 0;
                
                $activityAnalytics[] = [
                    'activity_id' => $activity->id,
                    'activity_title' => $activity->title,
                    'activity_type' => $activity->type,
                    'section_id' => $section->id,
                    'section_title' => $section->title,
                    'total_completions' => $totalCompletions,
                    'completion_rate' => $enrollments->count() > 0 ? 
                        round(($totalCompletions / $enrollments->count()) * 100, 2) : 0,
                    'total_attempts' => $totalAttempts,
                    'average_attempts' => $completions->count() > 0 ? 
                        round($totalAttempts / $completions->count(), 2) : 0,
                    'average_score' => round($averageScore, 2),
                    'average_time_spent' => round($averageTimeSpent, 2)
                ];
            }
        }
        
        return [
            'success' => true,
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'total_enrollments' => $enrollments->count(),
                'completed_enrollments' => $enrollments->where('status', 'completed')->count(),
                'completion_rate' => $enrollments->count() > 0 ? 
                    round(($enrollments->where('status', 'completed')->count() / $enrollments->count()) * 100, 2) : 0
            ],
            'activities' => $activityAnalytics
        ];
    }
}
