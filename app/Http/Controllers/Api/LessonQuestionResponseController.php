<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\ActivityCompletion;
use App\Models\LessonContent;
use App\Models\LessonQuestion;
use App\Models\LessonQuestionOption;
use App\Models\LessonQuestionResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Schema(
 *     schema="LessonQuestionResponse",
 *     required={"user_id", "lesson_question_id", "lesson_content_id"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="user_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="lesson_question_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="lesson_content_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="selected_option_id", type="integer", format="int64", example=1, nullable=true),
 *     @OA\Property(property="text_response", type="string", example="User's answer text", nullable=true),
 *     @OA\Property(property="is_correct", type="boolean", example=true),
 *     @OA\Property(property="points_earned", type="number", format="float", example=1.0),
 *     @OA\Property(property="attempt_number", type="integer", example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class LessonQuestionResponseController extends Controller
{
    /**
     * Submit responses for lesson questions.
     *
     * @OA\Post(
     *     path="/api/lessons/{lessonId}/submit-responses",
     *     summary="Submit responses for lesson questions",
     *     tags={"Lesson Responses"},
     *     @OA\Parameter(
     *         name="lessonId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="responses", type="array", @OA\Items(
     *                 @OA\Property(property="question_id", type="integer", example=1),
     *                 @OA\Property(property="selected_option_id", type="integer", example=1, nullable=true),
     *                 @OA\Property(property="text_response", type="string", example="User's answer", nullable=true)
     *             ))
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Responses submitted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Responses submitted successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="score", type="number", example=85.5),
     *                 @OA\Property(property="pass_score", type="integer", example=70),
     *                 @OA\Property(property="passed", type="boolean", example=true),
     *                 @OA\Property(property="activity_completed", type="boolean", example=true),
     *                 @OA\Property(property="responses", type="array", @OA\Items(ref="#/components/schemas/LessonQuestionResponse"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid input data"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Lesson not found"
     *     )
     * )
     */
    public function submitResponses(Request $request, $lessonId)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'responses' => 'required|array',
            'responses.*.question_id' => 'required|integer|exists:lesson_questions,id',
            'responses.*.selected_option_id' => 'nullable|integer|exists:lesson_question_options,id',
            'responses.*.text_response' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], Response::HTTP_BAD_REQUEST);
        }

        // Find the lesson content
        $lessonContent = LessonContent::with('questions.options')->findOrFail($lessonId);
        
        // Get the activity
        $activity = Activity::findOrFail($lessonContent->activity_id);
        
        // Check if the activity is a lesson
        if ($activity->type !== 'lesson') {
            return response()->json([
                'success' => false,
                'message' => 'This activity is not a lesson'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Get the user ID
        $userId = Auth::id();
        
        // Get the existing attempt number or create a new one
        $attemptNumber = LessonQuestionResponse::where('user_id', $userId)
            ->where('lesson_content_id', $lessonId)
            ->max('attempt_number') + 1;

        $responses = [];
        $totalPoints = 0;
        $earnedPoints = 0;
        $questionCount = 0;

        // Process each response
        DB::beginTransaction();
        try {
            foreach ($request->responses as $responseData) {
                $question = LessonQuestion::with('options')->findOrFail($responseData['question_id']);
                
                // Skip if the question doesn't belong to this lesson
                if ($question->lesson_content_id != $lessonId) {
                    continue;
                }
                
                $questionCount++;
                $isCorrect = false;
                $pointsEarned = 0;
                
                // Calculate if the response is correct based on question type
                switch ($question->question_type) {
                    case 'multiple_choice':
                    case 'true_false':
                        if (isset($responseData['selected_option_id'])) {
                            $selectedOption = LessonQuestionOption::findOrFail($responseData['selected_option_id']);
                            $isCorrect = $selectedOption->is_correct;
                            $pointsEarned = $isCorrect ? ($question->points ?? 1) : 0;
                        }
                        break;
                        
                    case 'short_answer':
                        // For short answer, we'll need manual grading or exact match
                        // For now, we'll just store the response
                        $isCorrect = null; // Null means pending review
                        $pointsEarned = null;
                        break;
                        
                    // Add more question types as needed
                }
                
                // Add to total points
                $totalPoints += ($question->points ?? 1);
                $earnedPoints += $pointsEarned ?? 0;
                
                // Create the response record
                $response = LessonQuestionResponse::create([
                    'user_id' => $userId,
                    'lesson_question_id' => $question->id,
                    'lesson_content_id' => $lessonId,
                    'selected_option_id' => $responseData['selected_option_id'] ?? null,
                    'text_response' => $responseData['text_response'] ?? null,
                    'is_correct' => $isCorrect,
                    'points_earned' => $pointsEarned,
                    'attempt_number' => $attemptNumber,
                ]);
                
                $responses[] = $response;
            }
            
            // Calculate the score percentage
            $score = $totalPoints > 0 ? ($earnedPoints / $totalPoints) * 100 : 0;
            $passScore = $lessonContent->pass_score ?? 70;
            $passed = $score >= $passScore;
            
            // Update activity completion if passed
            $activityCompleted = false;
            if ($passed) {
                $completion = ActivityCompletion::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'activity_id' => $activity->id,
                    ],
                    [
                        'status' => 'completed',
                        'score' => $score,
                        'completed_at' => now(),
                        'attempts' => DB::raw('attempts + 1'),
                    ]
                );
                $activityCompleted = true;
            } else {
                // Update attempts count even if not passed
                ActivityCompletion::updateOrCreate(
                    [
                        'user_id' => $userId,
                        'activity_id' => $activity->id,
                    ],
                    [
                        'status' => 'in_progress',
                        'score' => $score,
                        'attempts' => DB::raw('attempts + 1'),
                    ]
                );
            }
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Responses submitted successfully',
                'data' => [
                    'score' => round($score, 2),
                    'pass_score' => $passScore,
                    'passed' => $passed,
                    'activity_completed' => $activityCompleted,
                    'responses' => $responses
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit responses',
                'error' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get lesson question responses for a user.
     *
     * @OA\Get(
     *     path="/api/lessons/{lessonId}/responses",
     *     summary="Get lesson question responses for the current user",
     *     tags={"Lesson Responses"},
     *     @OA\Parameter(
     *         name="lessonId",
     *         in="path",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="attempt",
     *         in="query",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Responses retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="score", type="number", example=85.5),
     *                 @OA\Property(property="pass_score", type="integer", example=70),
     *                 @OA\Property(property="passed", type="boolean", example=true),
     *                 @OA\Property(property="responses", type="array", @OA\Items(ref="#/components/schemas/LessonQuestionResponse"))
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Lesson not found"
     *     )
     * )
     */
    public function getResponses(Request $request, $lessonId)
    {
        // Find the lesson content
        $lessonContent = LessonContent::findOrFail($lessonId);
        
        // Get the user ID
        $userId = Auth::id();
        
        // Get attempt number from query params or use the latest
        $attemptNumber = $request->query('attempt');
        
        $query = LessonQuestionResponse::where('user_id', $userId)
            ->where('lesson_content_id', $lessonId);
            
        if ($attemptNumber) {
            $query->where('attempt_number', $attemptNumber);
        } else {
            // Get the latest attempt number
            $latestAttempt = LessonQuestionResponse::where('user_id', $userId)
                ->where('lesson_content_id', $lessonId)
                ->max('attempt_number');
                
            if ($latestAttempt) {
                $query->where('attempt_number', $latestAttempt);
            }
        }
        
        $responses = $query->get();
        
        // Calculate score
        $totalPoints = 0;
        $earnedPoints = 0;
        
        foreach ($responses as $response) {
            $question = LessonQuestion::find($response->lesson_question_id);
            $totalPoints += ($question->points ?? 1);
            $earnedPoints += $response->points_earned ?? 0;
        }
        
        $score = $totalPoints > 0 ? ($earnedPoints / $totalPoints) * 100 : 0;
        $passScore = $lessonContent->pass_score ?? 70;
        $passed = $score >= $passScore;
        
        return response()->json([
            'success' => true,
            'data' => [
                'score' => round($score, 2),
                'pass_score' => $passScore,
                'passed' => $passed,
                'responses' => $responses
            ]
        ]);
    }
}
