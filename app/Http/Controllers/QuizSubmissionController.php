<?php

namespace App\Http\Controllers;

use App\Models\QuizContent;
use App\Models\QuizQuestion;
use App\Models\QuizSubmission;
use App\Models\QuizQuestionResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

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

        // Store each question response
        foreach ($request->responses as $responseData) {
            QuizQuestionResponse::create([
                'quiz_submission_id' => $submission->id,
                'quiz_question_id' => $responseData['quiz_question_id'],
                'user_response' => $responseData['user_response'],
                'is_correct' => $responseData['is_correct'],
                'points_earned' => $responseData['points_earned'],
                'max_points' => $responseData['max_points'],
            ]);
        }

        // Return the submission with responses
        return response()->json([
            'message' => 'Quiz submission created successfully',
            'submission' => $submission->load('responses'),
        ], 201);
    }

    /**
     * Get all submissions for a quiz content.
     *
     * @param  int  $quizContentId
     * @return \Illuminate\Http\Response
     */
    public function index($quizContentId)
    {
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
}
