<?php

namespace App\Services;

use App\Models\Enrollment;
use App\Models\Course;
use App\Models\User;
use App\Models\ActivityCompletion;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class EnrollmentService extends Service
{
    /**
     * Get all enrollments with optional filtering
     *
     * @param array $filters
     * @return Collection
     */
    public function getAllEnrollments(array $filters = []): Collection
    {
        $query = Enrollment::query();
        
        // Apply filters
        if (isset($filters['course_id'])) {
            $query->where('course_id', $filters['course_id']);
        }
        
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);
        
        return $query->get();
    }
    
    /**
     * Get an enrollment by ID
     *
     * @param int $id
     * @return Enrollment|null
     */
    public function getEnrollmentById(int $id): ?Enrollment
    {
        return Enrollment::with(['user', 'course', 'activityCompletions'])->find($id);
    }
    
    /**
     * Get enrollments for a specific user
     *
     * @param int $userId
     * @param array $filters
     * @return Collection
     */
    public function getUserEnrollments(int $userId, array $filters = []): Collection
    {
        $query = Enrollment::with(['course'])
            ->where('user_id', $userId);
        
        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);
        
        return $query->get();
    }
    
    /**
     * Get enrollments for a specific course
     *
     * @param int $courseId
     * @param array $filters
     * @return Collection
     */
    public function getCourseEnrollments(int $courseId, array $filters = []): Collection
    {
        $query = Enrollment::with(['user'])
            ->where('course_id', $courseId);
        
        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        // Apply sorting
        $sortBy = $filters['sort_by'] ?? 'created_at';
        $sortOrder = $filters['sort_order'] ?? 'desc';
        $query->orderBy($sortBy, $sortOrder);
        
        return $query->get();
    }
    
    /**
     * Enroll a user in a course
     *
     * @param int $userId
     * @param int $courseId
     * @param array $data Additional enrollment data
     * @return Enrollment|array
     */
    public function enrollUser(int $userId, int $courseId, array $data = [])
    {
        try {
            $user = User::findOrFail($userId);
            $course = Course::findOrFail($courseId);
            
            // Check if user is already enrolled
            $existingEnrollment = Enrollment::where('user_id', $userId)
                ->where('course_id', $courseId)
                ->first();
            
            if ($existingEnrollment) {
                return [
                    'success' => false,
                    'message' => 'User is already enrolled in this course',
                    'enrollment' => $existingEnrollment
                ];
            }
            
            // Check enrollment limit
            if ($course->enrollment_limit) {
                $currentEnrollments = Enrollment::where('course_id', $courseId)->count();
                
                if ($currentEnrollments >= $course->enrollment_limit) {
                    return [
                        'success' => false,
                        'message' => 'Course enrollment limit has been reached'
                    ];
                }
            }
            
            // Check course status
            if ($course->status !== 'published') {
                return [
                    'success' => false,
                    'message' => 'Cannot enroll in a course that is not published'
                ];
            }
            
            // Set default data
            $enrollmentData = array_merge([
                'user_id' => $userId,
                'course_id' => $courseId,
                'status' => 'active',
                'enrollment_date' => now()
            ], $data);
            
            // Create enrollment
            $enrollment = Enrollment::create($enrollmentData);
            
            return [
                'success' => true,
                'message' => 'User enrolled successfully',
                'enrollment' => $enrollment
            ];
        } catch (ModelNotFoundException $e) {
            return [
                'success' => false,
                'message' => 'User or course not found'
            ];
        }
    }
    
    /**
     * Update an enrollment
     *
     * @param int $id
     * @param array $data
     * @return Enrollment|null
     */
    public function updateEnrollment(int $id, array $data): ?Enrollment
    {
        $enrollment = Enrollment::find($id);
        
        if (!$enrollment) {
            return null;
        }
        
        $enrollment->update($data);
        
        return $enrollment->fresh();
    }
    
    /**
     * Cancel an enrollment
     *
     * @param int $id
     * @return Enrollment|null
     */
    public function cancelEnrollment(int $id): ?Enrollment
    {
        $enrollment = Enrollment::find($id);
        
        if (!$enrollment) {
            return null;
        }
        
        $enrollment->update([
            'status' => 'cancelled'
        ]);
        
        return $enrollment->fresh();
    }
    
    /**
     * Complete an enrollment
     *
     * @param int $id
     * @return Enrollment|null
     */
    public function completeEnrollment(int $id): ?Enrollment
    {
        $enrollment = Enrollment::find($id);
        
        if (!$enrollment) {
            return null;
        }
        
        $enrollment->update([
            'status' => 'completed',
            'completion_date' => now()
        ]);
        
        return $enrollment->fresh();
    }
    
    /**
     * Check if a user is enrolled in a course
     *
     * @param int $userId
     * @param int $courseId
     * @return bool
     */
    public function isUserEnrolled(int $userId, int $courseId): bool
    {
        return Enrollment::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->where('status', 'active')
            ->exists();
    }
    
    /**
     * Get course progress for an enrollment
     *
     * @param int $enrollmentId
     * @return array
     */
    public function getEnrollmentProgress(int $enrollmentId): array
    {
        $enrollment = Enrollment::with(['course.sections.activities', 'activityCompletions'])
            ->find($enrollmentId);
        
        if (!$enrollment) {
            return [
                'success' => false,
                'message' => 'Enrollment not found'
            ];
        }
        
        $course = $enrollment->course;
        $completions = $enrollment->activityCompletions;
        
        // Count total activities and required activities
        $totalActivities = 0;
        $totalRequiredActivities = 0;
        $completedActivities = 0;
        $completedRequiredActivities = 0;
        
        $sectionProgress = [];
        
        foreach ($course->sections as $section) {
            $sectionTotalActivities = count($section->activities);
            $sectionRequiredActivities = $section->activities->where('is_required', true)->count();
            $sectionCompletedActivities = 0;
            $sectionCompletedRequiredActivities = 0;
            
            $activityProgress = [];
            
            foreach ($section->activities as $activity) {
                $completion = $completions->where('activity_id', $activity->id)->first();
                $isCompleted = $completion && $completion->status === 'completed';
                
                if ($isCompleted) {
                    $completedActivities++;
                    $sectionCompletedActivities++;
                    
                    if ($activity->is_required) {
                        $completedRequiredActivities++;
                        $sectionCompletedRequiredActivities++;
                    }
                }
                
                $activityProgress[] = [
                    'id' => $activity->id,
                    'title' => $activity->title,
                    'type' => $activity->type,
                    'is_required' => $activity->is_required,
                    'is_completed' => $isCompleted,
                    'completion' => $completion ? [
                        'status' => $completion->status,
                        'score' => $completion->score,
                        'completed_at' => $completion->completed_at,
                        'attempts' => $completion->attempts
                    ] : null
                ];
            }
            
            $totalActivities += $sectionTotalActivities;
            $totalRequiredActivities += $sectionRequiredActivities;
            
            $sectionProgress[] = [
                'id' => $section->id,
                'title' => $section->title,
                'total_activities' => $sectionTotalActivities,
                'completed_activities' => $sectionCompletedActivities,
                'progress_percentage' => $sectionTotalActivities > 0 ? 
                    round(($sectionCompletedActivities / $sectionTotalActivities) * 100) : 0,
                'required_activities' => $sectionRequiredActivities,
                'completed_required_activities' => $sectionCompletedRequiredActivities,
                'required_progress_percentage' => $sectionRequiredActivities > 0 ? 
                    round(($sectionCompletedRequiredActivities / $sectionRequiredActivities) * 100) : 100,
                'activities' => $activityProgress
            ];
        }
        
        // Calculate overall progress
        $overallProgress = $totalActivities > 0 ? 
            round(($completedActivities / $totalActivities) * 100) : 0;
            
        $requiredProgress = $totalRequiredActivities > 0 ? 
            round(($completedRequiredActivities / $totalRequiredActivities) * 100) : 100;
            
        // Check if course is completed
        $isCourseCompleted = $totalRequiredActivities > 0 ? 
            $completedRequiredActivities === $totalRequiredActivities : false;
            
        // Auto-complete enrollment if all required activities are completed
        if ($isCourseCompleted && $enrollment->status === 'active') {
            $this->completeEnrollment($enrollmentId);
            $enrollment->refresh();
        }
        
        return [
            'success' => true,
            'enrollment' => [
                'id' => $enrollment->id,
                'status' => $enrollment->status,
                'enrollment_date' => $enrollment->enrollment_date,
                'completion_date' => $enrollment->completion_date
            ],
            'course' => [
                'id' => $course->id,
                'title' => $course->title
            ],
            'progress' => [
                'total_activities' => $totalActivities,
                'completed_activities' => $completedActivities,
                'overall_progress' => $overallProgress,
                'required_activities' => $totalRequiredActivities,
                'completed_required_activities' => $completedRequiredActivities,
                'required_progress' => $requiredProgress,
                'is_completed' => $isCourseCompleted
            ],
            'sections' => $sectionProgress
        ];
    }
}
