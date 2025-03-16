<?php

namespace App\Services;

use App\Models\AssignmentContent;
use App\Models\ActivityCompletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AssignmentService extends ContentService
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->activityType = 'assignment';
        $this->modelClass = AssignmentContent::class;
        
        $this->validationRules = [
            'activity_id' => 'required|integer|exists:activities,id',
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'instructions' => 'required|string',
            'due_date' => 'nullable|date',
            'points' => 'nullable|integer|min:0',
            'passing_score' => 'nullable|integer|min:0',
            'submission_type' => 'required|string|in:text,file,link,code,multiple',
            'allowed_file_types' => 'nullable|array',
            'allowed_file_types.*' => 'string',
            'max_file_size' => 'nullable|integer|min:1',
            'max_submissions' => 'nullable|integer|min:1',
            'rubric' => 'nullable|array',
            'rubric.*.criteria' => 'required|string',
            'rubric.*.description' => 'nullable|string',
            'rubric.*.points' => 'required|integer|min:0',
            'rubric.*.levels' => 'nullable|array',
            'allow_late_submissions' => 'boolean',
            'late_submission_penalty' => 'nullable|integer|min:0|max:100',
            'resources' => 'nullable|array',
            'resources.*.title' => 'required|string',
            'resources.*.type' => 'required|string',
            'resources.*.url' => 'required|string',
            'group_assignment' => 'boolean',
            'max_group_size' => 'nullable|integer|min:2',
            'metadata' => 'nullable|array'
        ];
    }
    
    /**
     * Process data before saving to the database
     * Encode arrays to JSON
     *
     * @param array $data
     * @return array
     */
    protected function processDataBeforeSave(array $data): array
    {
        if (isset($data['allowed_file_types']) && is_array($data['allowed_file_types'])) {
            $data['allowed_file_types'] = json_encode($data['allowed_file_types']);
        }
        
        if (isset($data['rubric']) && is_array($data['rubric'])) {
            $data['rubric'] = json_encode($data['rubric']);
        }
        
        if (isset($data['resources']) && is_array($data['resources'])) {
            $data['resources'] = json_encode($data['resources']);
        }
        
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
        }
        
        return $data;
    }
    
    /**
     * Process data after retrieving from the database
     * Decode JSON to arrays
     *
     * @param Model $model
     * @return Model
     */
    protected function processDataAfterRetrieve(Model $model): Model
    {
        if (isset($model->allowed_file_types) && is_string($model->allowed_file_types)) {
            $model->allowed_file_types = json_decode($model->allowed_file_types, true);
        }
        
        if (isset($model->rubric) && is_string($model->rubric)) {
            $model->rubric = json_decode($model->rubric, true);
        }
        
        if (isset($model->resources) && is_string($model->resources)) {
            $model->resources = json_decode($model->resources, true);
        }
        
        if (isset($model->metadata) && is_string($model->metadata)) {
            $model->metadata = json_decode($model->metadata, true);
        }
        
        return $model;
    }
    
    /**
     * Get assignment content by ID with decoded data
     *
     * @param int $id
     * @return Model|null
     */
    public function getAssignment(int $id): ?Model
    {
        $content = $this->getById($id);
        
        if ($content) {
            return $this->processDataAfterRetrieve($content);
        }
        
        return null;
    }
    
    /**
     * Submit an assignment
     *
     * @param int $assignmentId
     * @param int $enrollmentId
     * @param array $submissionData
     * @return array
     */
    public function submitAssignment(int $assignmentId, int $enrollmentId, array $submissionData): array
    {
        $assignment = $this->getAssignment($assignmentId);
        
        if (!$assignment) {
            return [
                'success' => false,
                'message' => 'Assignment not found'
            ];
        }
        
        // Check if submission is allowed
        $submissionAllowed = $this->isSubmissionAllowed($assignment, $enrollmentId);
        
        if (!$submissionAllowed['allowed']) {
            return [
                'success' => false,
                'message' => $submissionAllowed['message']
            ];
        }
        
        // Validate submission data
        $validationResult = $this->validateSubmission($assignment, $submissionData);
        
        if (!$validationResult['valid']) {
            return [
                'success' => false,
                'message' => $validationResult['message']
            ];
        }
        
        // Process submission
        $submission = [
            'content' => $submissionData['content'] ?? null,
            'files' => $submissionData['files'] ?? [],
            'links' => $submissionData['links'] ?? [],
            'code' => $submissionData['code'] ?? null,
            'comments' => $submissionData['comments'] ?? null,
            'submitted_at' => now(),
            'is_late' => $this->isLateSubmission($assignment)
        ];
        
        // Get activity ID
        $activityId = $assignment->activity_id;
        
        // Get existing completion record if any
        $completion = ActivityCompletion::where('enrollment_id', $enrollmentId)
            ->where('activity_id', $activityId)
            ->first();
        
        // Create or update submission data
        if ($completion) {
            $submissions = json_decode($completion->data ?? '[]', true);
            $submissions[] = $submission;
            
            $completion->update([
                'status' => 'submitted',
                'data' => json_encode($submissions),
                'attempts' => DB::raw('COALESCE(attempts, 0) + 1')
            ]);
        } else {
            $completion = ActivityCompletion::create([
                'enrollment_id' => $enrollmentId,
                'activity_id' => $activityId,
                'status' => 'submitted',
                'data' => json_encode([$submission]),
                'attempts' => 1
            ]);
        }
        
        return [
            'success' => true,
            'message' => 'Assignment submitted successfully',
            'submission' => $submission,
            'completion' => $completion
        ];
    }
    
    /**
     * Check if submission is allowed
     *
     * @param Model $assignment
     * @param int $enrollmentId
     * @return array
     */
    protected function isSubmissionAllowed(Model $assignment, int $enrollmentId): array
    {
        // Check if due date has passed and late submissions are not allowed
        if (isset($assignment->due_date) && 
            !$assignment->allow_late_submissions && 
            strtotime($assignment->due_date) < time()) {
            return [
                'allowed' => false,
                'message' => 'Submission deadline has passed and late submissions are not allowed'
            ];
        }
        
        // Check if max submissions limit has been reached
        if (isset($assignment->max_submissions)) {
            $activityId = $assignment->activity_id;
            
            $completion = ActivityCompletion::where('enrollment_id', $enrollmentId)
                ->where('activity_id', $activityId)
                ->first();
            
            if ($completion && $completion->attempts >= $assignment->max_submissions) {
                return [
                    'allowed' => false,
                    'message' => 'Maximum number of submissions reached'
                ];
            }
        }
        
        return [
            'allowed' => true,
            'message' => 'Submission allowed'
        ];
    }
    
    /**
     * Validate submission data
     *
     * @param Model $assignment
     * @param array $submissionData
     * @return array
     */
    protected function validateSubmission(Model $assignment, array $submissionData): array
    {
        $submissionType = $assignment->submission_type;
        
        // Validate based on submission type
        switch ($submissionType) {
            case 'text':
                if (!isset($submissionData['content']) || empty($submissionData['content'])) {
                    return [
                        'valid' => false,
                        'message' => 'Text content is required for this assignment'
                    ];
                }
                break;
                
            case 'file':
                if (!isset($submissionData['files']) || empty($submissionData['files'])) {
                    return [
                        'valid' => false,
                        'message' => 'File submission is required for this assignment'
                    ];
                }
                
                // Validate file types if specified
                if (isset($assignment->allowed_file_types) && !empty($assignment->allowed_file_types)) {
                    foreach ($submissionData['files'] as $file) {
                        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                        
                        if (!in_array($extension, $assignment->allowed_file_types)) {
                            return [
                                'valid' => false,
                                'message' => "File type '.$extension' is not allowed"
                            ];
                        }
                        
                        // Validate file size if specified
                        if (isset($assignment->max_file_size) && $file['size'] > $assignment->max_file_size * 1024 * 1024) {
                            return [
                                'valid' => false,
                                'message' => "File '{$file['name']}' exceeds the maximum allowed size"
                            ];
                        }
                    }
                }
                break;
                
            case 'link':
                if (!isset($submissionData['links']) || empty($submissionData['links'])) {
                    return [
                        'valid' => false,
                        'message' => 'Link submission is required for this assignment'
                    ];
                }
                break;
                
            case 'code':
                if (!isset($submissionData['code']) || empty($submissionData['code'])) {
                    return [
                        'valid' => false,
                        'message' => 'Code submission is required for this assignment'
                    ];
                }
                break;
                
            case 'multiple':
                // For multiple submission types, at least one type must be provided
                if ((!isset($submissionData['content']) || empty($submissionData['content'])) &&
                    (!isset($submissionData['files']) || empty($submissionData['files'])) &&
                    (!isset($submissionData['links']) || empty($submissionData['links'])) &&
                    (!isset($submissionData['code']) || empty($submissionData['code']))) {
                    return [
                        'valid' => false,
                        'message' => 'At least one submission type (text, file, link, or code) is required'
                    ];
                }
                break;
        }
        
        return [
            'valid' => true,
            'message' => 'Submission is valid'
        ];
    }
    
    /**
     * Check if submission is late
     *
     * @param Model $assignment
     * @return bool
     */
    protected function isLateSubmission(Model $assignment): bool
    {
        return isset($assignment->due_date) && strtotime($assignment->due_date) < time();
    }
    
    /**
     * Grade an assignment submission
     *
     * @param int $assignmentId
     * @param int $enrollmentId
     * @param array $gradingData
     * @return array
     */
    public function gradeAssignment(int $assignmentId, int $enrollmentId, array $gradingData): array
    {
        $assignment = $this->getAssignment($assignmentId);
        
        if (!$assignment) {
            return [
                'success' => false,
                'message' => 'Assignment not found'
            ];
        }
        
        $activityId = $assignment->activity_id;
        
        // Get completion record
        $completion = ActivityCompletion::where('enrollment_id', $enrollmentId)
            ->where('activity_id', $activityId)
            ->first();
        
        if (!$completion || $completion->status !== 'submitted') {
            return [
                'success' => false,
                'message' => 'No submission found for grading'
            ];
        }
        
        // Calculate final score
        $score = $gradingData['score'] ?? 0;
        $maxScore = $assignment->points ?? 100;
        
        // Apply late submission penalty if applicable
        $submissions = json_decode($completion->data ?? '[]', true);
        $latestSubmission = end($submissions);
        
        if ($latestSubmission['is_late'] && isset($assignment->late_submission_penalty)) {
            $penaltyAmount = ($score * $assignment->late_submission_penalty) / 100;
            $score = max(0, $score - $penaltyAmount);
        }
        
        // Calculate percentage
        $scorePercentage = ($maxScore > 0) ? round(($score / $maxScore) * 100) : 0;
        
        // Determine if passed
        $passed = true;
        if (isset($assignment->passing_score)) {
            $passed = $scorePercentage >= $assignment->passing_score;
        }
        
        // Update completion record
        $completion->update([
            'status' => $passed ? 'completed' : 'failed',
            'score' => $scorePercentage,
            'completed_at' => now(),
            'feedback' => $gradingData['feedback'] ?? null,
            'graded_by' => $gradingData['graded_by'] ?? null,
            'graded_at' => now()
        ]);
        
        return [
            'success' => true,
            'message' => 'Assignment graded successfully',
            'score' => $score,
            'max_score' => $maxScore,
            'percentage' => $scorePercentage,
            'passed' => $passed,
            'completion' => $completion
        ];
    }
    
    /**
     * Get assignment submissions for a specific enrollment
     *
     * @param int $assignmentId
     * @param int $enrollmentId
     * @return array
     */
    public function getSubmissions(int $assignmentId, int $enrollmentId): array
    {
        $assignment = $this->getAssignment($assignmentId);
        
        if (!$assignment) {
            return [
                'success' => false,
                'message' => 'Assignment not found'
            ];
        }
        
        $activityId = $assignment->activity_id;
        
        // Get completion record
        $completion = ActivityCompletion::where('enrollment_id', $enrollmentId)
            ->where('activity_id', $activityId)
            ->first();
        
        if (!$completion) {
            return [
                'success' => true,
                'assignment' => $assignment,
                'submissions' => []
            ];
        }
        
        $submissions = json_decode($completion->data ?? '[]', true);
        
        return [
            'success' => true,
            'assignment' => $assignment,
            'submissions' => $submissions,
            'status' => $completion->status,
            'score' => $completion->score,
            'feedback' => $completion->feedback,
            'attempts' => $completion->attempts,
            'completed_at' => $completion->completed_at,
            'graded_at' => $completion->graded_at
        ];
    }
    
    /**
     * Add a rubric criterion to assignment
     *
     * @param int $id
     * @param array $criterion
     * @return Model|null
     */
    public function addRubricCriterion(int $id, array $criterion): ?Model
    {
        $assignment = $this->getAssignment($id);
        
        if (!$assignment) {
            return null;
        }
        
        $rubric = $assignment->rubric ?? [];
        $rubric[] = $criterion;
        
        return $this->update($id, ['rubric' => $rubric]);
    }
    
    /**
     * Update a rubric criterion
     *
     * @param int $id
     * @param int $criterionIndex
     * @param array $criterion
     * @return Model|null
     */
    public function updateRubricCriterion(int $id, int $criterionIndex, array $criterion): ?Model
    {
        $assignment = $this->getAssignment($id);
        
        if (!$assignment || !isset($assignment->rubric[$criterionIndex])) {
            return null;
        }
        
        $rubric = $assignment->rubric;
        $rubric[$criterionIndex] = $criterion;
        
        return $this->update($id, ['rubric' => $rubric]);
    }
    
    /**
     * Remove a rubric criterion
     *
     * @param int $id
     * @param int $criterionIndex
     * @return Model|null
     */
    public function removeRubricCriterion(int $id, int $criterionIndex): ?Model
    {
        $assignment = $this->getAssignment($id);
        
        if (!$assignment || !isset($assignment->rubric[$criterionIndex])) {
            return null;
        }
        
        $rubric = $assignment->rubric;
        array_splice($rubric, $criterionIndex, 1);
        
        return $this->update($id, ['rubric' => $rubric]);
    }
    
    /**
     * Add a resource to assignment
     *
     * @param int $id
     * @param array $resource
     * @return Model|null
     */
    public function addResource(int $id, array $resource): ?Model
    {
        $assignment = $this->getAssignment($id);
        
        if (!$assignment) {
            return null;
        }
        
        $resources = $assignment->resources ?? [];
        $resources[] = $resource;
        
        return $this->update($id, ['resources' => $resources]);
    }
    
    /**
     * Remove a resource from assignment
     *
     * @param int $id
     * @param string $resourceTitle
     * @return Model|null
     */
    public function removeResource(int $id, string $resourceTitle): ?Model
    {
        $assignment = $this->getAssignment($id);
        
        if (!$assignment || !isset($assignment->resources)) {
            return null;
        }
        
        $resources = array_filter($assignment->resources, function ($resource) use ($resourceTitle) {
            return $resource['title'] !== $resourceTitle;
        });
        
        return $this->update($id, ['resources' => array_values($resources)]);
    }
}
