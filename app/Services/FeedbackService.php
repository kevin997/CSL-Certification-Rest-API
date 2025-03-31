<?php

namespace App\Services;

use App\Models\FeedbackContent;
use App\Models\ActivityCompletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class FeedbackService extends ContentService
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->activityType = 'feedback';
        $this->modelClass = FeedbackContent::class;
        
        $this->validationRules = [
            'activity_id' => 'required|integer|exists:activities,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'introduction' => 'nullable|string',
            'conclusion' => 'nullable|string',
            'questions' => 'required|array',
            'questions.*.id' => 'required|string',
            'questions.*.text' => 'required|string',
            'questions.*.type' => 'required|string|in:text,textarea,radio,checkbox,rating,dropdown',
            'questions.*.required' => 'boolean',
            'questions.*.options' => 'nullable|array',
            'questions.*.options.*' => 'required|string',
            'questions.*.min_rating' => 'nullable|integer|min:1',
            'questions.*.max_rating' => 'nullable|integer|min:1',
            'questions.*.min_label' => 'nullable|string',
            'questions.*.max_label' => 'nullable|string',
            'is_anonymous' => 'boolean',
            'allow_multiple_submissions' => 'boolean',
            'show_progress' => 'boolean',
            'randomize_questions' => 'boolean',
            'thank_you_message' => 'nullable|string',
            'redirect_url' => 'nullable|string|url',
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
        if (isset($data['questions']) && is_array($data['questions'])) {
            $data['questions'] = json_encode($data['questions']);
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
        if (isset($model->questions) && is_string($model->questions)) {
            $model->questions = json_decode($model->questions, true);
        }
        
        if (isset($model->metadata) && is_string($model->metadata)) {
            $model->metadata = json_decode($model->metadata, true);
        }
        
        return $model;
    }
    
    /**
     * Get feedback content by ID with decoded data
     *
     * @param int $id
     * @return Model|null
     */
    public function getFeedback(int $id): ?Model
    {
        $content = $this->getById($id);
        
        if ($content) {
            return $this->processDataAfterRetrieve($content);
        }
        
        return null;
    }
    
    /**
     * Submit feedback response
     *
     * @param int $feedbackId
     * @param int $enrollmentId
     * @param array $responses
     * @return array
     */
    public function submitFeedback(int $feedbackId, int $enrollmentId, array $responses): array
    {
        $feedback = $this->getFeedback($feedbackId);
        
        if (!$feedback) {
            return [
                'success' => false,
                'message' => 'Feedback form not found'
            ];
        }
        
        // Get activity ID
        $activityId = $feedback->activity_id;
        
        // Check if already submitted and multiple submissions are not allowed
        $existing = ActivityCompletion::where('enrollment_id', $enrollmentId)
            ->where('activity_id', $activityId)
            ->where('status', 'completed')
            ->first();
        
        if ($existing && !$feedback->allow_multiple_submissions) {
            return [
                'success' => false,
                'message' => 'You have already submitted this feedback form'
            ];
        }
        
        // Validate responses
        $validationResult = $this->validateResponses($feedback, $responses);
        
        if (!$validationResult['valid']) {
            return [
                'success' => false,
                'message' => 'Invalid responses: ' . $validationResult['message']
            ];
        }
        
        // Process responses
        $processedResponses = $this->processResponses($feedback, $responses);
        
        // Create or update feedback submission
        $submissionData = [
            'submission_date' => now()->format('Y-m-d H:i:s'),
            'responses' => $processedResponses,
            'is_anonymous' => $feedback->is_anonymous
        ];
        
        if ($existing) {
            $existing->update([
                'status' => 'completed',
                'completed_at' => now(),
                'data' => json_encode($submissionData)
            ]);
            $completion = $existing;
        } else {
            $completion = ActivityCompletion::create([
                'enrollment_id' => $enrollmentId,
                'activity_id' => $activityId,
                'status' => 'completed',
                'completed_at' => now(),
                'data' => json_encode($submissionData)
            ]);
        }
        
        return [
            'success' => true,
            'message' => 'Feedback submitted successfully',
            'thank_you_message' => $feedback->thank_you_message,
            'redirect_url' => $feedback->redirect_url
        ];
    }
    
    /**
     * Validate feedback responses
     *
     * @param Model $feedback
     * @param array $responses
     * @return array
     */
    protected function validateResponses(Model $feedback, array $responses): array
    {
        $questions = $feedback->questions;
        
        // Check for required questions
        foreach ($questions as $question) {
            $questionId = $question['id'];
            
            if ($question['required'] && (!isset($responses[$questionId]) || empty($responses[$questionId]))) {
                return [
                    'valid' => false,
                    'message' => "Question '{$question['text']}' is required"
                ];
            }
            
            // If response exists, validate it based on question type
            if (isset($responses[$questionId])) {
                $response = $responses[$questionId];
                
                switch ($question['type']) {
                    case 'radio':
                        if (!in_array($response, $question['options'])) {
                            return [
                                'valid' => false,
                                'message' => "Invalid option for question '{$question['text']}'"
                            ];
                        }
                        break;
                        
                    case 'checkbox':
                        if (!is_array($response)) {
                            return [
                                'valid' => false,
                                'message' => "Checkbox response must be an array for question '{$question['text']}'"
                            ];
                        }
                        
                        foreach ($response as $option) {
                            if (!in_array($option, $question['options'])) {
                                return [
                                    'valid' => false,
                                    'message' => "Invalid option '{$option}' for question '{$question['text']}'"
                                ];
                            }
                        }
                        break;
                        
                    case 'rating':
                        $min = $question['min_rating'] ?? 1;
                        $max = $question['max_rating'] ?? 5;
                        
                        if (!is_numeric($response) || $response < $min || $response > $max) {
                            return [
                                'valid' => false,
                                'message' => "Rating must be between {$min} and {$max} for question '{$question['text']}'"
                            ];
                        }
                        break;
                        
                    case 'dropdown':
                        if (!in_array($response, $question['options'])) {
                            return [
                                'valid' => false,
                                'message' => "Invalid option for question '{$question['text']}'"
                            ];
                        }
                        break;
                }
            }
        }
        
        return [
            'valid' => true
        ];
    }
    
    /**
     * Process feedback responses
     *
     * @param Model $feedback
     * @param array $responses
     * @return array
     */
    protected function processResponses(Model $feedback, array $responses): array
    {
        $questions = $feedback->questions;
        $processedResponses = [];
        
        foreach ($questions as $question) {
            $questionId = $question['id'];
            
            if (isset($responses[$questionId])) {
                $processedResponses[] = [
                    'question_id' => $questionId,
                    'question_text' => $question['text'],
                    'question_type' => $question['type'],
                    'response' => $responses[$questionId]
                ];
            }
        }
        
        return $processedResponses;
    }
    
    /**
     * Get feedback submissions
     *
     * @param int $feedbackId
     * @param bool $includeAnonymous
     * @return array
     */
    public function getFeedbackSubmissions(int $feedbackId, bool $includeAnonymous = true): array
    {
        $feedback = $this->getFeedback($feedbackId);
        
        if (!$feedback) {
            return [
                'success' => false,
                'message' => 'Feedback form not found'
            ];
        }
        
        // Get activity ID
        $activityId = $feedback->activity_id;
        
        // Get all submissions
        $completions = ActivityCompletion::with(['enrollment.user'])
            ->where('activity_id', $activityId)
            ->where('status', 'completed')
            ->get();
        
        $submissions = [];
        
        foreach ($completions as $completion) {
            $data = json_decode($completion->data ?? '{}', true);
            
            if (!isset($data['responses'])) {
                continue;
            }
            
            $submission = [
                'completion_id' => $completion->id,
                'submission_date' => $data['submission_date'] ?? null,
                'responses' => $data['responses'] ?? []
            ];
            
            // Include user information if not anonymous or if includeAnonymous is true
            if (!($data['is_anonymous'] ?? false) || $includeAnonymous) {
                $submission['user'] = [
                    'id' => $completion->enrollment->user->id,
                    'name' => $completion->enrollment->user->name,
                    'email' => $completion->enrollment->user->email
                ];
                
                $submission['is_anonymous'] = $data['is_anonymous'] ?? false;
            }
            
            $submissions[] = $submission;
        }
        
        return [
            'success' => true,
            'feedback' => $feedback,
            'submissions' => $submissions,
            'total_submissions' => count($submissions)
        ];
    }
    
    /**
     * Get feedback analytics
     *
     * @param int $feedbackId
     * @return array
     */
    public function getFeedbackAnalytics(int $feedbackId): array
    {
        $submissions = $this->getFeedbackSubmissions($feedbackId);
        
        if (!$submissions['success']) {
            return $submissions;
        }
        
        $feedback = $submissions['feedback'];
        $questions = $feedback->questions;
        $allSubmissions = $submissions['submissions'];
        
        $analytics = [
            'total_submissions' => count($allSubmissions),
            'questions' => []
        ];
        
        foreach ($questions as $question) {
            $questionId = $question['id'];
            $questionType = $question['type'];
            
            $questionAnalytics = [
                'id' => $questionId,
                'text' => $question['text'],
                'type' => $questionType
            ];
            
            switch ($questionType) {
                case 'text':
                case 'textarea':
                    $responses = [];
                    
                    foreach ($allSubmissions as $submission) {
                        foreach ($submission['responses'] as $response) {
                            if ($response['question_id'] === $questionId) {
                                $responses[] = $response['response'];
                            }
                        }
                    }
                    
                    $questionAnalytics['responses'] = $responses;
                    break;
                    
                case 'radio':
                case 'dropdown':
                    $options = $question['options'];
                    $counts = array_fill_keys($options, 0);
                    
                    foreach ($allSubmissions as $submission) {
                        foreach ($submission['responses'] as $response) {
                            if ($response['question_id'] === $questionId) {
                                $counts[$response['response']]++;
                            }
                        }
                    }
                    
                    $questionAnalytics['options'] = $options;
                    $questionAnalytics['counts'] = $counts;
                    $questionAnalytics['percentages'] = array_map(function ($count) use ($allSubmissions) {
                        return count($allSubmissions) > 0 ? round(($count / count($allSubmissions)) * 100, 1) : 0;
                    }, $counts);
                    break;
                    
                case 'checkbox':
                    $options = $question['options'];
                    $counts = array_fill_keys($options, 0);
                    
                    foreach ($allSubmissions as $submission) {
                        foreach ($submission['responses'] as $response) {
                            if ($response['question_id'] === $questionId && is_array($response['response'])) {
                                foreach ($response['response'] as $option) {
                                    $counts[$option]++;
                                }
                            }
                        }
                    }
                    
                    $questionAnalytics['options'] = $options;
                    $questionAnalytics['counts'] = $counts;
                    $questionAnalytics['percentages'] = array_map(function ($count) use ($allSubmissions) {
                        return count($allSubmissions) > 0 ? round(($count / count($allSubmissions)) * 100, 1) : 0;
                    }, $counts);
                    break;
                    
                case 'rating':
                    $min = $question['min_rating'] ?? 1;
                    $max = $question['max_rating'] ?? 5;
                    $ratings = [];
                    
                    foreach ($allSubmissions as $submission) {
                        foreach ($submission['responses'] as $response) {
                            if ($response['question_id'] === $questionId) {
                                $ratings[] = (int) $response['response'];
                            }
                        }
                    }
                    
                    $questionAnalytics['min_rating'] = $min;
                    $questionAnalytics['max_rating'] = $max;
                    $questionAnalytics['min_label'] = $question['min_label'] ?? null;
                    $questionAnalytics['max_label'] = $question['max_label'] ?? null;
                    $questionAnalytics['average'] = count($ratings) > 0 ? round(array_sum($ratings) / count($ratings), 1) : 0;
                    $questionAnalytics['distribution'] = array_count_values($ratings);
                    break;
            }
            
            $analytics['questions'][] = $questionAnalytics;
        }
        
        return [
            'success' => true,
            'feedback' => $feedback,
            'analytics' => $analytics
        ];
    }
    
    /**
     * Add question to feedback form
     *
     * @param int $id
     * @param array $question
     * @return Model|null
     */
    public function addQuestion(int $id, array $question): ?Model
    {
        $feedback = $this->getFeedback($id);
        
        if (!$feedback) {
            return null;
        }
        
        $questions = $feedback->questions ?? [];
        
        // Generate question ID if not provided
        if (!isset($question['id'])) {
            $question['id'] = 'q_' . uniqid();
        }
        
        $questions[] = $question;
        
        return $this->update($id, ['questions' => $questions]);
    }
    
    /**
     * Update question in feedback form
     *
     * @param int $id
     * @param string $questionId
     * @param array $questionData
     * @return Model|null
     */
    public function updateQuestion(int $id, string $questionId, array $questionData): ?Model
    {
        $feedback = $this->getFeedback($id);
        
        if (!$feedback) {
            return null;
        }
        
        $questions = $feedback->questions ?? [];
        $updated = false;
        
        foreach ($questions as $key => $question) {
            if ($question['id'] === $questionId) {
                // Preserve the original ID
                $questionData['id'] = $questionId;
                $questions[$key] = $questionData;
                $updated = true;
                break;
            }
        }
        
        if (!$updated) {
            return null;
        }
        
        return $this->update($id, ['questions' => $questions]);
    }
    
    /**
     * Remove question from feedback form
     *
     * @param int $id
     * @param string $questionId
     * @return Model|null
     */
    public function removeQuestion(int $id, string $questionId): ?Model
    {
        $feedback = $this->getFeedback($id);
        
        if (!$feedback) {
            return null;
        }
        
        $questions = $feedback->questions ?? [];
        $filtered = array_filter($questions, function ($question) use ($questionId) {
            return $question['id'] !== $questionId;
        });
        
        if (count($filtered) === count($questions)) {
            return null;
        }
        
        return $this->update($id, ['questions' => array_values($filtered)]);
    }
    
    /**
     * Reorder questions in feedback form
     *
     * @param int $id
     * @param array $questionIds
     * @return Model|null
     */
    public function reorderQuestions(int $id, array $questionIds): ?Model
    {
        $feedback = $this->getFeedback($id);
        
        if (!$feedback) {
            return null;
        }
        
        $questions = $feedback->questions ?? [];
        $questionMap = [];
        
        // Create a map of question ID to question data
        foreach ($questions as $question) {
            $questionMap[$question['id']] = $question;
        }
        
        // Create a new array of questions in the specified order
        $reorderedQuestions = [];
        
        foreach ($questionIds as $questionId) {
            if (isset($questionMap[$questionId])) {
                $reorderedQuestions[] = $questionMap[$questionId];
            }
        }
        
        // Check if all questions are included
        if (count($reorderedQuestions) !== count($questions)) {
            return null;
        }
        
        return $this->update($id, ['questions' => $reorderedQuestions]);
    }
}
