<?php

namespace App\Http\Controllers;

use App\Models\QuizContent;
use App\Models\QuizQuestion;
use App\Models\QuizSubmission;
use App\Models\QuizQuestionResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class QuizSubmissionController extends Controller
{
    /**
     * Store a newly created quiz submission in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $quizContentId
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $quizContentId)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'enrollment_id' => 'required|exists:enrollments,id',
            'score' => 'required|numeric|min:0',
            'max_score' => 'required|numeric|min:0',
            'percentage_score' => 'required|numeric|min:0|max:100',
            'is_passed' => 'required|boolean',
            'time_spent' => 'required|integer|min:0',
            'responses' => 'required|array',
            'responses.*.quiz_question_id' => 'required|exists:quiz_questions,id',
            'responses.*.user_response' => 'required',
            'responses.*.is_correct' => 'required|boolean',
            'responses.*.points_earned' => 'required|numeric|min:0',
            'responses.*.max_points' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if quiz content exists
        $quizContent = QuizContent::find($quizContentId);
        if (!$quizContent) {
            return response()->json(['error' => 'Quiz content not found'], 404);
        }

        // Get the last attempt number for this user and quiz
        $lastAttempt = QuizSubmission::where('quiz_content_id', $quizContentId)
            ->where('user_id', Auth::id())
            ->where('enrollment_id', $request->enrollment_id)
            ->max('attempt_number') ?? 0;

        // Create the quiz submission
        $submission = QuizSubmission::create([
            'quiz_content_id' => $quizContentId,
            'user_id' => Auth::id(),
            'enrollment_id' => $request->enrollment_id,
            'score' => $request->score,
            'max_score' => $request->max_score,
            'percentage_score' => $request->percentage_score,
            'is_passed' => $request->is_passed,
            'completed_at' => now(),
            'time_spent' => $request->time_spent,
            'attempt_number' => $lastAttempt + 1,
            'created_by' => Auth::id(),
        ]);

        // Store each question response with server-side validation
        $totalServerScore = 0;
        $totalMaxScore = 0;
        $validationWarnings = [];
        
        foreach ($request->responses as $responseData) {
            // Validate answer server-side
            $question = QuizQuestion::with(['options'])->find($responseData['quiz_question_id']);
            $validationResult = $this->validateAnswer($question, $responseData['user_response'], $responseData['is_correct'], $responseData['points_earned']);
            
            if (!$validationResult['valid']) {
                $validationWarnings[] = [
                    'question_id' => $responseData['quiz_question_id'],
                    'message' => $validationResult['message'],
                    'client_correct' => $responseData['is_correct'],
                    'server_correct' => $validationResult['server_is_correct'],
                    'client_points' => $responseData['points_earned'],
                    'server_points' => $validationResult['server_points_earned']
                ];
            }
            
            // Use server-calculated values if validation failed, otherwise trust client
            $finalIsCorrect = $validationResult['valid'] ? $responseData['is_correct'] : $validationResult['server_is_correct'];
            $finalPointsEarned = $validationResult['valid'] ? $responseData['points_earned'] : $validationResult['server_points_earned'];
            
            $totalServerScore += $finalPointsEarned;
            $totalMaxScore += $responseData['max_points'];
            
            QuizQuestionResponse::create([
                'quiz_submission_id' => $submission->id,
                'quiz_question_id' => $responseData['quiz_question_id'],
                'user_response' => $responseData['user_response'],
                'is_correct' => $finalIsCorrect,
                'points_earned' => $finalPointsEarned,
                'max_points' => $responseData['max_points'],
            ]);
        }
        
        // Update submission with server-calculated score if there were discrepancies
        if (count($validationWarnings) > 0 && $totalMaxScore > 0) {
            $serverPercentageScore = round(($totalServerScore / $totalMaxScore) * 100);
            $submission->update([
                'score' => $serverPercentageScore,
                'percentage_score' => $serverPercentageScore,
                'is_passed' => $serverPercentageScore >= ($quizContent->passing_score ?? 70)
            ]);
        }

        // Return the submission with responses and validation info
        $response = [
            'message' => 'Quiz submission created successfully',
            'submission' => $submission->load('responses'),
        ];
        
        if (count($validationWarnings) > 0) {
            $response['validation_warnings'] = $validationWarnings;
            $response['message'] = 'Quiz submission created with validation adjustments';
        }
        
        return response()->json($response, 201);
    }

    /**
     * Validate user answer against correct answer stored in database
     */
    private function validateAnswer($question, $userResponse, $clientIsCorrect, $clientPointsEarned)
    {
        if (!$question) {
            return [
                'valid' => false,
                'message' => 'Question not found',
                'server_is_correct' => false,
                'server_points_earned' => 0
            ];
        }

        // Handle null/empty responses
        if ($userResponse === null || $userResponse === '' || (is_array($userResponse) && empty($userResponse))) {
            return [
                'valid' => $clientIsCorrect === false && $clientPointsEarned === 0,
                'message' => $clientIsCorrect ? 'Client marked empty response as correct' : 'Empty response correctly handled',
                'server_is_correct' => false,
                'server_points_earned' => 0
            ];
        }

        $serverResult = $this->calculateCorrectness($question, $userResponse);
        
        // Compare server calculation with client submission
        $scoresMatch = abs($serverResult['points_earned'] - $clientPointsEarned) < 0.01; // Allow small floating point differences
        $correctnessMatches = $serverResult['is_correct'] === $clientIsCorrect;
        
        return [
            'valid' => $scoresMatch && $correctnessMatches,
            'message' => $scoresMatch && $correctnessMatches ? 'Validation passed' : 'Score/correctness mismatch',
            'server_is_correct' => $serverResult['is_correct'],
            'server_points_earned' => $serverResult['points_earned']
        ];
    }

    /**
     * Calculate correctness and points for a question response
     */
    private function calculateCorrectness($question, $userResponse)
    {
        switch ($question->question_type) {
            case 'multiple_choice':
                return $this->validateMultipleChoice($question, $userResponse);
            case 'multiple_response':
                return $this->validateMultipleResponse($question, $userResponse);
            case 'true_false':
                return $this->validateTrueFalse($question, $userResponse);
            case 'questionnaire':
                return $this->validateQuestionnaire($question, $userResponse);
            case 'short_answer':
                return $this->validateShortAnswer($question, $userResponse);
            case 'hotspot':
                return $this->validateHotspot($question, $userResponse);
            default:
                // For question types we don't validate server-side, trust client
                return ['is_correct' => true, 'points_earned' => $question->points ?? 1];
        }
    }

    private function validateMultipleChoice($question, $userResponse)
    {
        $selectedIndex = is_array($userResponse) && isset($userResponse['index']) 
            ? $userResponse['index'] 
            : (is_numeric($userResponse) ? intval($userResponse) : null);
            
        if ($selectedIndex === null) {
            return ['is_correct' => false, 'points_earned' => 0];
        }
        
        $options = $question->options ?? [];
        $correctOption = collect($options)->firstWhere('is_correct', true);
        $selectedOption = $options[$selectedIndex] ?? null;
        
        $isCorrect = $selectedOption && $selectedOption['is_correct'] === true;
        
        return [
            'is_correct' => $isCorrect,
            'points_earned' => $isCorrect ? ($question->points ?? 1) : 0
        ];
    }

    private function validateMultipleResponse($question, $userResponse)
    {
        if (!is_array($userResponse)) {
            return ['is_correct' => false, 'points_earned' => 0];
        }
        
        $options = $question->options ?? [];
        $correctIndices = [];
        foreach ($options as $index => $option) {
            if ($option['is_correct'] === true) {
                $correctIndices[] = $index;
            }
        }
        
        $selectedIndices = array_map('intval', $userResponse);
        sort($selectedIndices);
        sort($correctIndices);
        
        $isFullyCorrect = $selectedIndices === $correctIndices;
        
        // Calculate partial score
        $correctSelections = count(array_intersect($selectedIndices, $correctIndices));
        $incorrectSelections = count(array_diff($selectedIndices, $correctIndices));
        $totalCorrect = count($correctIndices);
        
        $partialScore = $totalCorrect > 0 ? max(0, ($correctSelections - $incorrectSelections) / $totalCorrect) : 0;
        
        return [
            'is_correct' => $isFullyCorrect,
            'points_earned' => $partialScore * ($question->points ?? 1)
        ];
    }

    private function validateTrueFalse($question, $userResponse)
    {
        $userBool = $userResponse === true || $userResponse === 'true' || $userResponse === 1;
        $options = $question->options ?? [];
        $correctOption = collect($options)->firstWhere('is_correct', true);
        $correctAnswer = $correctOption && $correctOption['text'] === 'True';
        
        $isCorrect = $userBool === $correctAnswer;
        
        return [
            'is_correct' => $isCorrect,
            'points_earned' => $isCorrect ? ($question->points ?? 1) : 0
        ];
    }

    private function validateQuestionnaire($question, $userResponse)
    {
        if (!$userResponse || !is_array($userResponse)) {
            return ['is_correct' => false, 'points_earned' => 0];
        }
        
        $totalPointsEarned = 0;
        
        // Use the accessor method to get subquestions
        $subquestions = $question->subquestions;
        
        if (!$subquestions || $subquestions->isEmpty()) {
            // Fallback: try to get questionnaire data directly from options
            $options = $question->options()->whereNotNull('subquestion_text')->get();
            if ($options->isEmpty()) {
                return ['is_correct' => false, 'points_earned' => 0];
            }
            
            // Calculate points from options directly
            foreach ($userResponse as $subIndex => $userSubAnswers) {
                if (!is_array($userSubAnswers)) continue;
                
                foreach ($userSubAnswers as $answerOptionId) {
                    $option = $options->where('answer_option_id', $answerOptionId)->first();
                    if ($option) {
                        $totalPointsEarned += $option->points ?? 0;
                    }
                }
            }
        } else {
            // Use the structured subquestions data
            foreach ($subquestions as $subIndex => $subquestion) {
                $userSubAnswers = $userResponse[$subIndex] ?? [];
                if (!is_array($userSubAnswers)) continue;
                
                $assignments = $subquestion->assignments ?? collect();
                foreach ($userSubAnswers as $answerOptionId) {
                    $assignment = $assignments->firstWhere('answer_option_id', $answerOptionId);
                    if ($assignment) {
                        $totalPointsEarned += $assignment->points ?? 0;
                    }
                }
            }
        }
        
        return [
            'is_correct' => $totalPointsEarned > 0,
            'points_earned' => min($totalPointsEarned, $question->points ?? 0)
        ];
    }

    private function validateShortAnswer($question, $userResponse)
    {
        $userText = trim(strtolower($userResponse));
        $options = $question->options ?? [];

        foreach ($options as $option) {
            $acceptableAnswer = trim(strtolower($option['option_text'] ?? $option['text'] ?? ''));
            if ($userText === $acceptableAnswer) {
                return ['is_correct' => true, 'points_earned' => $question->points ?? 1];
            }
        }

        return ['is_correct' => false, 'points_earned' => 0];
    }

    /**
     * Validate hotspot question response
     * User submits click coordinates, we check if they're in correct hotspot zones
     */
    private function validateHotspot($question, $userResponse)
    {
        if (!is_array($userResponse)) {
            return ['is_correct' => false, 'points_earned' => 0];
        }

        $options = $question->options ?? [];

        // Helper function to check if a point is within a circular hotspot zone
        // Adds a 20% tolerance buffer to make it easier to hit hotspots
        $isPointInCircle = function($clickX, $clickY, $centerX, $centerY, $radius) {
            $distance = sqrt(pow($clickX - $centerX, 2) + pow($clickY - $centerY, 2));
            // Add 20% tolerance to radius for more lenient validation
            $tolerantRadius = $radius * 1.2;
            return $distance <= $tolerantRadius;
        };

        // Parse position data from various formats
        $parsePosition = function($position) {
            if (!$position) return null;

            // Handle string format (JSON)
            if (is_string($position)) {
                $decoded = json_decode($position, true);
                if ($decoded && isset($decoded['x']) && isset($decoded['y'])) {
                    return [
                        'x' => $decoded['x'],
                        'y' => $decoded['y'],
                        'radius' => $decoded['radius'] ?? 8
                    ];
                }
                return null;
            }

            // Handle array format
            if (is_array($position) && isset($position['x']) && isset($position['y'])) {
                return [
                    'x' => $position['x'],
                    'y' => $position['y'],
                    'radius' => $position['radius'] ?? 8
                ];
            }

            return null;
        };

        // Get correct hotspot zones with positions
        $correctHotspots = [];
        foreach ($options as $option) {
            if (isset($option['is_correct']) && $option['is_correct'] === true) {
                $position = $parsePosition($option['position'] ?? null);
                if ($position) {
                    $correctHotspots[] = $position;
                }
            }
        }

        if (empty($correctHotspots)) {
            return ['is_correct' => false, 'points_earned' => 0];
        }

        // Count correct and incorrect clicks
        $correctClicks = 0;
        $incorrectClicks = 0;

        foreach ($userResponse as $click) {
            if (is_array($click) && isset($click['x']) && isset($click['y'])) {
                // Check if this click is within any correct hotspot zone
                $isInCorrectZone = false;
                foreach ($correctHotspots as $hotspot) {
                    if ($isPointInCircle($click['x'], $click['y'], $hotspot['x'], $hotspot['y'], $hotspot['radius'])) {
                        $isInCorrectZone = true;
                        break;
                    }
                }

                if ($isInCorrectZone) {
                    $correctClicks++;
                } else {
                    $incorrectClicks++;
                }
            }
        }

        // Calculate partial score
        $totalCorrectHotspots = count($correctHotspots);
        $partialScore = 0;

        if ($totalCorrectHotspots > 0) {
            $positiveScore = min($correctClicks, $totalCorrectHotspots) / $totalCorrectHotspots;
            $penaltyScore = count($options) > 0 ? $incorrectClicks / count($options) : 0;
            $partialScore = max(0, $positiveScore - $penaltyScore);
        }

        // Fully correct if all correct hotspots hit and no incorrect clicks
        $isFullyCorrect = $correctClicks >= $totalCorrectHotspots && $incorrectClicks === 0;

        return [
            'is_correct' => $isFullyCorrect,
            'points_earned' => $partialScore * ($question->points ?? 1)
        ];
    }

    /**
     * Get all submissions for a quiz content.
     *
     * @param  int  $quizContentId
     * @return \Illuminate\Http\Response
     */
    public function index($quizContentId)
    {

        Log::info("Getting quizcontentId".$quizContentId);
        // Check if quiz content exists
        $quizContent = QuizContent::find($quizContentId);
        if (!$quizContent) {
            return response()->json(['error' => 'Quiz content not found'], 404);
        }

        // Get all submissions for this quiz content
        $submissions = QuizSubmission::where('quiz_content_id', $quizContentId)
            ->with('responses')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($submissions);
    }

    /**
     * Get a specific submission.
     *
     * @param  int  $submissionId
     * @return \Illuminate\Http\Response
     */
    public function show($submissionId)
    {
        // Find the submission
        $submission = QuizSubmission::with('responses')->find($submissionId);
        if (!$submission) {
            return response()->json(['error' => 'Quiz submission not found'], 404);
        }

        return response()->json($submission);
    }

    /**
     * Get all submissions for a user's enrollment
     *
     * @param  int  $enrollmentId
     * @return \Illuminate\Http\Response
     */
    public function getByEnrollment($enrollmentId)
    {
        // Get all submissions for this enrollment
        $submissions = QuizSubmission::where('enrollment_id', $enrollmentId)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($submissions);
    }

    /**
     * Get all submissions for the current authenticated user for a specific quiz
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $quizContentId
     * @return \Illuminate\Http\Response
     */
    public function getUserSubmissions(Request $request, $quizContentId)
    {
        Log::info("Request from getUserSubmissions for quiz", [
            'quiz_content_id' => $quizContentId, 
            'user_id' => Auth::id(),
            'query_params' => $request->all()
        ]);

        // Check if quiz content exists
        $quizContent = QuizContent::find($quizContentId);
        if (!$quizContent) {
            return response()->json(['error' => 'Quiz content not found'], 404);
        }

        $query = QuizSubmission::where('user_id', Auth::id())
            ->where('quiz_content_id', $quizContentId)
            ->with(['responses']);
            
        // Filter by enrollment if provided
        $enrollmentId = $request->query('enrollment_id');
        if ($enrollmentId) {
            $query->where('enrollment_id', $enrollmentId);
        }
        
        $submissions = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $submissions,
        ]);
    }
}
