<?php

namespace App\Services;

use App\Models\QuizContent;
use App\Models\ActivityCompletion;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class QuizService extends ContentService
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        
        $this->activityType = 'quiz';
        $this->modelClass = QuizContent::class;
        
        $this->validationRules = [
            'activity_id' => 'required|integer|exists:activities,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'questions' => 'required|array|min:1',
            'questions.*.text' => 'required|string',
            'questions.*.type' => 'required|string|in:multiple_choice,single_choice,true_false,text',
            'questions.*.options' => 'required_unless:questions.*.type,text|array|min:2',
            'questions.*.options.*.text' => 'required|string',
            'questions.*.options.*.is_correct' => 'required|boolean',
            'questions.*.points' => 'required|integer|min:1',
            'time_limit' => 'nullable|integer|min:1',
            'passing_score' => 'required|integer|min:1',
            'allow_retakes' => 'boolean',
            'randomize_questions' => 'boolean',
            'show_correct_answers' => 'boolean',
            'max_attempts' => 'nullable|integer|min:1',
        ];
    }
    
    /**
     * Process data before saving to the database
     * Encode questions array to JSON
     *
     * @param array $data
     * @return array
     */
    protected function processDataBeforeSave(array $data): array
    {
        if (isset($data['questions']) && is_array($data['questions'])) {
            $data['questions'] = json_encode($data['questions']);
        }
        
        return $data;
    }
    
    /**
     * Get a quiz with decoded questions
     *
     * @param int $id
     * @return Model|null
     */
    public function getQuiz(int $id): ?Model
    {
        $quiz = $this->getById($id);
        
        if ($quiz && isset($quiz->questions)) {
            $quiz->questions = json_decode($quiz->questions, true);
        }
        
        return $quiz;
    }
    
    /**
     * Submit a quiz attempt
     *
     * @param int $quizId
     * @param array $answers
     * @param int $enrollmentId
     * @return array
     */
    public function submitQuizAttempt(int $quizId, array $answers, int $enrollmentId): array
    {
        $quiz = $this->getQuiz($quizId);
        
        if (!$quiz) {
            return [
                'success' => false,
                'message' => 'Quiz not found'
            ];
        }
        
        // Calculate score
        $score = $this->calculateScore($quiz, $answers);
        $totalPoints = $this->calculateTotalPoints($quiz);
        $scorePercentage = ($totalPoints > 0) ? round(($score / $totalPoints) * 100) : 0;
        $passed = $scorePercentage >= $quiz->passing_score;
        
        // Get activity ID
        $activityId = $quiz->activity_id;
        
        // Record completion
        $completion = ActivityCompletion::updateOrCreate(
            [
                'enrollment_id' => $enrollmentId,
                'activity_id' => $activityId
            ],
            [
                'status' => $passed ? 'completed' : 'failed',
                'score' => $scorePercentage,
                'attempts' => DB::raw('COALESCE(attempts, 0) + 1'),
                'completed_at' => $passed ? now() : null,
                'time_spent' => DB::raw('COALESCE(time_spent, 0) + ' . ($answers['time_spent'] ?? 0))
            ]
        );
        
        return [
            'success' => true,
            'score' => $score,
            'total_points' => $totalPoints,
            'percentage' => $scorePercentage,
            'passed' => $passed,
            'completion' => $completion,
            'feedback' => $this->generateFeedback($quiz, $answers, $scorePercentage)
        ];
    }
    
    /**
     * Calculate score for a quiz attempt
     *
     * @param Model $quiz
     * @param array $answers
     * @return int
     */
    protected function calculateScore(Model $quiz, array $answers): int
    {
        $score = 0;
        $questions = $quiz->questions;
        
        foreach ($questions as $questionIndex => $question) {
            $questionId = $question['id'] ?? $questionIndex;
            
            if (!isset($answers['questions'][$questionId])) {
                continue;
            }
            
            $userAnswer = $answers['questions'][$questionId];
            
            switch ($question['type']) {
                case 'multiple_choice':
                    $score += $this->scoreMultipleChoiceQuestion($question, $userAnswer);
                    break;
                    
                case 'single_choice':
                case 'true_false':
                    $score += $this->scoreSingleChoiceQuestion($question, $userAnswer);
                    break;
                    
                case 'text':
                    $score += $this->scoreTextQuestion($question, $userAnswer);
                    break;
            }
        }
        
        return $score;
    }
    
    /**
     * Calculate total possible points for a quiz
     *
     * @param Model $quiz
     * @return int
     */
    protected function calculateTotalPoints(Model $quiz): int
    {
        $totalPoints = 0;
        
        foreach ($quiz->questions as $question) {
            $totalPoints += $question['points'] ?? 0;
        }
        
        return $totalPoints;
    }
    
    /**
     * Score a multiple choice question
     *
     * @param array $question
     * @param array $userAnswer
     * @return int
     */
    protected function scoreMultipleChoiceQuestion(array $question, $userAnswer): int
    {
        if (!is_array($userAnswer)) {
            return 0;
        }
        
        $correctOptions = [];
        $userSelectedOptions = $userAnswer;
        
        // Get all correct options
        foreach ($question['options'] as $optionIndex => $option) {
            if ($option['is_correct']) {
                $correctOptions[] = $option['id'] ?? $optionIndex;
            }
        }
        
        // Check if user selected all correct options and no incorrect ones
        $allCorrectSelected = count(array_intersect($userSelectedOptions, $correctOptions)) === count($correctOptions);
        $noIncorrectSelected = count(array_diff($userSelectedOptions, $correctOptions)) === 0;
        
        return ($allCorrectSelected && $noIncorrectSelected) ? $question['points'] : 0;
    }
    
    /**
     * Score a single choice question
     *
     * @param array $question
     * @param mixed $userAnswer
     * @return int
     */
    protected function scoreSingleChoiceQuestion(array $question, $userAnswer): int
    {
        if (!is_string($userAnswer) && !is_numeric($userAnswer)) {
            return 0;
        }
        
        foreach ($question['options'] as $optionIndex => $option) {
            $optionId = $option['id'] ?? $optionIndex;
            
            if ($option['is_correct'] && $userAnswer == $optionId) {
                return $question['points'];
            }
        }
        
        return 0;
    }
    
    /**
     * Score a text question (requires manual grading)
     *
     * @param array $question
     * @param mixed $userAnswer
     * @return int
     */
    protected function scoreTextQuestion(array $question, $userAnswer): int
    {
        // Text questions require manual grading
        // For now, we'll check if there's an exact match with any correct answer
        if (!isset($question['correct_answers']) || !is_array($question['correct_answers'])) {
            return 0;
        }
        
        if (in_array(strtolower(trim($userAnswer)), array_map('strtolower', array_map('trim', $question['correct_answers'])))) {
            return $question['points'];
        }
        
        return 0;
    }
    
    /**
     * Generate feedback for a quiz attempt
     *
     * @param Model $quiz
     * @param array $answers
     * @param int $scorePercentage
     * @return array
     */
    protected function generateFeedback(Model $quiz, array $answers, int $scorePercentage): array
    {
        $feedback = [
            'overall' => '',
            'questions' => []
        ];
        
        // Overall feedback
        if ($scorePercentage >= $quiz->passing_score) {
            $feedback['overall'] = 'Congratulations! You have passed the quiz.';
        } else {
            $feedback['overall'] = 'You did not meet the passing score. You may retake the quiz if allowed.';
        }
        
        // Question-specific feedback
        if ($quiz->show_correct_answers) {
            foreach ($quiz->questions as $questionIndex => $question) {
                $questionId = $question['id'] ?? $questionIndex;
                $userAnswer = $answers['questions'][$questionId] ?? null;
                
                $questionFeedback = [
                    'question' => $question['text'],
                    'correct' => false,
                    'correct_answer' => null,
                    'your_answer' => $userAnswer
                ];
                
                switch ($question['type']) {
                    case 'multiple_choice':
                        $correctOptions = [];
                        foreach ($question['options'] as $optionIndex => $option) {
                            if ($option['is_correct']) {
                                $correctOptions[] = $option['text'];
                            }
                        }
                        $questionFeedback['correct_answer'] = $correctOptions;
                        $questionFeedback['correct'] = $this->scoreMultipleChoiceQuestion($question, $userAnswer) > 0;
                        break;
                        
                    case 'single_choice':
                    case 'true_false':
                        foreach ($question['options'] as $option) {
                            if ($option['is_correct']) {
                                $questionFeedback['correct_answer'] = $option['text'];
                                break;
                            }
                        }
                        $questionFeedback['correct'] = $this->scoreSingleChoiceQuestion($question, $userAnswer) > 0;
                        break;
                        
                    case 'text':
                        $questionFeedback['correct_answer'] = $question['correct_answers'][0] ?? 'Not provided';
                        $questionFeedback['correct'] = $this->scoreTextQuestion($question, $userAnswer) > 0;
                        break;
                }
                
                $feedback['questions'][] = $questionFeedback;
            }
        }
        
        return $feedback;
    }
    
    /**
     * Get quiz statistics
     *
     * @param int $quizId
     * @return array
     */
    public function getQuizStatistics(int $quizId): array
    {
        $quiz = $this->getById($quizId);
        
        if (!$quiz) {
            return [
                'success' => false,
                'message' => 'Quiz not found'
            ];
        }
        
        $activityId = $quiz->activity_id;
        
        $completions = ActivityCompletion::where('activity_id', $activityId)->get();
        
        $totalAttempts = $completions->sum('attempts');
        $totalCompletions = $completions->where('status', 'completed')->count();
        $averageScore = $completions->avg('score');
        $highestScore = $completions->max('score');
        $lowestScore = $completions->min('score');
        $averageTimeSpent = $completions->avg('time_spent');
        
        return [
            'success' => true,
            'statistics' => [
                'total_attempts' => $totalAttempts,
                'total_completions' => $totalCompletions,
                'completion_rate' => $totalAttempts > 0 ? round(($totalCompletions / $totalAttempts) * 100, 2) : 0,
                'average_score' => round($averageScore, 2),
                'highest_score' => $highestScore,
                'lowest_score' => $lowestScore,
                'average_time_spent' => round($averageTimeSpent, 2)
            ]
        ];
    }
}
