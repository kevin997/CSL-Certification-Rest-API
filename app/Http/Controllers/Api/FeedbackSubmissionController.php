<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Block;
use App\Models\FeedbackAnswer;
use App\Models\FeedbackContent;
use App\Models\FeedbackQuestion;
use App\Models\FeedbackSubmission;
use App\Models\Template;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class FeedbackSubmissionController extends Controller
{
    /**
     * Get all submissions for a feedback content
     *
     * @param  int  $feedbackContentId
     * @return \Illuminate\Http\Response
     */
    public function index($feedbackContentId)
    {
        $feedbackContent = FeedbackContent::findOrFail($feedbackContentId);
        
        // Get the activity and check permissions
        $activity = Activity::findOrFail($feedbackContent->activity_id);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        // Only the template owner can view all submissions
        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to view these submissions',
            ], Response::HTTP_FORBIDDEN);
        }

        // Get all submissions with answers and questions
        $submissions = FeedbackSubmission::where('feedback_content_id', $feedbackContentId)
            ->with(['answers.question', 'user'])
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $submissions,
        ]);
    }

    /**
     * Get a specific submission
     *
     * @param  int  $submissionId
     * @return \Illuminate\Http\Response
     */
    public function show($submissionId)
    {
        $submission = FeedbackSubmission::with(['answers.question', 'feedbackContent', 'user'])
            ->findOrFail($submissionId);
        
        // Get the activity and check permissions
        $feedbackContent = FeedbackContent::findOrFail($submission->feedback_content_id);
        $activity = Activity::findOrFail($feedbackContent->activity_id);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        // Only the template owner or the submission owner can view it
        if ($template->created_by !== Auth::id() && $submission->user_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to view this submission',
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'status' => 'success',
            'data' => $submission,
        ]);
    }

    /**
     * Create a new submission
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $feedbackContentId
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $feedbackContentId)
    {
        $feedbackContent = FeedbackContent::findOrFail($feedbackContentId);
        
        // Validate the request
        $validator = Validator::make($request->all(), [
            'answers' => 'required|array',
            'answers.*.feedback_question_id' => 'required|integer|exists:feedback_questions,id',
            'answers.*.answer_text' => 'nullable|string',
            'answers.*.answer_value' => 'nullable|numeric',
            'answers.*.answer_options' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check if there's an existing draft submission for this user and feedback content
        $existingSubmission = FeedbackSubmission::where('feedback_content_id', $feedbackContentId)
            ->where('user_id', Auth::id())
            ->where('status', 'draft')
            ->first();

        if ($existingSubmission) {
            // Update existing draft
            return $this->updateSubmission($request, $existingSubmission->id);
        }

        // Create new submission
        $submission = FeedbackSubmission::create([
            'feedback_content_id' => $feedbackContentId,
            'user_id' => Auth::id(),
            'status' => 'draft',
        ]);

        // Create answers
        foreach ($request->answers as $answerData) {
            // Validate that the question belongs to this feedback content
            $question = FeedbackQuestion::where('id', $answerData['feedback_question_id'])
                ->where('feedback_content_id', $feedbackContentId)
                ->first();

            if (!$question) {
                continue; // Skip invalid questions
            }

            // Handle answer options as JSON
            if (isset($answerData['answer_options']) && is_array($answerData['answer_options'])) {
                $answerData['answer_options'] = json_encode($answerData['answer_options']);
            }

            // Create the answer
            FeedbackAnswer::create([
                'feedback_submission_id' => $submission->id,
                'feedback_question_id' => $answerData['feedback_question_id'],
                'answer_text' => $answerData['answer_text'] ?? null,
                'answer_value' => $answerData['answer_value'] ?? null,
                'answer_options' => $answerData['answer_options'] ?? null,
            ]);
        }

        // Load the answers and questions
        $submission->load(['answers.question']);

        return response()->json([
            'status' => 'success',
            'message' => 'Draft submission created successfully',
            'data' => $submission,
        ], Response::HTTP_CREATED);
    }

    /**
     * Update an existing submission
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $submissionId
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $submissionId)
    {
        $submission = FeedbackSubmission::findOrFail($submissionId);

        // Only the submission owner can update it
        if ($submission->user_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to update this submission',
            ], Response::HTTP_FORBIDDEN);
        }

        // Only draft submissions can be updated
        if ($submission->status !== 'draft') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only draft submissions can be updated',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate the request
        $validator = Validator::make($request->all(), [
            'answers' => 'required|array',
            'answers.*.feedback_question_id' => 'required|integer|exists:feedback_questions,id',
            'answers.*.answer_text' => 'nullable|string',
            'answers.*.answer_value' => 'nullable|numeric',
            'answers.*.answer_options' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Get existing answer IDs
        $existingAnswerIds = FeedbackAnswer::where('feedback_submission_id', $submissionId)
            ->pluck('id')
            ->toArray();
        
        $updatedAnswerIds = [];
        
        // Update or create answers
        foreach ($request->answers as $answerData) {
            // Validate that the question belongs to this feedback content
            $question = FeedbackQuestion::where('id', $answerData['feedback_question_id'])
                ->where('feedback_content_id', $submission->feedback_content_id)
                ->first();

            if (!$question) {
                continue; // Skip invalid questions
            }

            // Handle answer options as JSON
            if (isset($answerData['answer_options']) && is_array($answerData['answer_options'])) {
                $answerData['answer_options'] = json_encode($answerData['answer_options']);
            }

            // Check if answer already exists
            $answer = FeedbackAnswer::where('feedback_submission_id', $submissionId)
                ->where('feedback_question_id', $answerData['feedback_question_id'])
                ->first();

            if ($answer) {
                // Update existing answer
                $answer->update([
                    'answer_text' => $answerData['answer_text'] ?? null,
                    'answer_value' => $answerData['answer_value'] ?? null,
                    'answer_options' => $answerData['answer_options'] ?? null,
                ]);
                $updatedAnswerIds[] = $answer->id;
            } else {
                // Create new answer
                $newAnswer = FeedbackAnswer::create([
                    'feedback_submission_id' => $submission->id,
                    'feedback_question_id' => $answerData['feedback_question_id'],
                    'answer_text' => $answerData['answer_text'] ?? null,
                    'answer_value' => $answerData['answer_value'] ?? null,
                    'answer_options' => $answerData['answer_options'] ?? null,
                ]);
                $updatedAnswerIds[] = $newAnswer->id;
            }
        }
        
        // Delete answers that weren't included in the update
        $answersToDelete = array_diff($existingAnswerIds, $updatedAnswerIds);
        if (!empty($answersToDelete)) {
            FeedbackAnswer::whereIn('id', $answersToDelete)->delete();
        }

        // Load the answers and questions
        $submission->load(['answers.question']);

        return response()->json([
            'status' => 'success',
            'message' => 'Submission updated successfully',
            'data' => $submission,
        ]);
    }

    /**
     * Submit a draft submission
     *
     * @param  int  $submissionId
     * @return \Illuminate\Http\Response
     */
    public function submit($submissionId)
    {
        $submission = FeedbackSubmission::findOrFail($submissionId);

        // Only the submission owner can submit it
        if ($submission->user_id !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to submit this feedback',
            ], Response::HTTP_FORBIDDEN);
        }

        // Only draft submissions can be submitted
        if ($submission->status !== 'draft') {
            return response()->json([
                'status' => 'error',
                'message' => 'Only draft submissions can be submitted',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Get the feedback content to check required questions
        $feedbackContent = FeedbackContent::findOrFail($submission->feedback_content_id);
        $requiredQuestions = FeedbackQuestion::where('feedback_content_id', $feedbackContent->id)
            ->where('required', true)
            ->pluck('id')
            ->toArray();
        
        // Get answered question IDs
        $answeredQuestionIds = FeedbackAnswer::where('feedback_submission_id', $submissionId)
            ->pluck('feedback_question_id')
            ->toArray();
        
        // Check if all required questions are answered
        $missingRequiredQuestions = array_diff($requiredQuestions, $answeredQuestionIds);
        if (!empty($missingRequiredQuestions)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Please answer all required questions before submitting',
                'missing_questions' => $missingRequiredQuestions,
            ], Response::HTTP_BAD_REQUEST);
        }

        // Update submission status
        $submission->update([
            'status' => 'submitted',
            'submission_date' => now(),
        ]);

        // Load the answers and questions
        $submission->load(['answers.question']);

        return response()->json([
            'status' => 'success',
            'message' => 'Feedback submitted successfully',
            'data' => $submission,
        ]);
    }

    /**
     * Delete a submission
     *
     * @param  int  $submissionId
     * @return \Illuminate\Http\Response
     */
    public function destroy($submissionId)
    {
        $submission = FeedbackSubmission::findOrFail($submissionId);

        // Only the submission owner or template owner can delete it
        $feedbackContent = FeedbackContent::findOrFail($submission->feedback_content_id);
        $activity = Activity::findOrFail($feedbackContent->activity_id);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        if ($submission->user_id !== Auth::id() && $template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to delete this submission',
            ], Response::HTTP_FORBIDDEN);
        }

        // Delete all answers
        FeedbackAnswer::where('feedback_submission_id', $submissionId)->delete();
        
        // Delete the submission
        $submission->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Submission deleted successfully',
        ]);
    }

    /**
     * Get all submissions for the authenticated user
     *
     * @return \Illuminate\Http\Response
     */
    public function getUserSubmissions(Request $request)
    {
        Log::info("Request from getUserSubmissions", $request->all());
        Log::info("User making request", ['user_id' => Auth::id()]);

        // Get feedback_content_id from query parameters if provided
        $feedbackContentId = $request->query('feedback_content_id');
        
        $query = FeedbackSubmission::where('user_id', Auth::id())
            ->with(['feedbackContent', 'answers.question']);
            
        // Filter by feedback content ID if provided
        if ($feedbackContentId) {
            $query->where('feedback_content_id', $feedbackContentId);
        }
        
        $submissions = $query->get();

        return response()->json([
            'status' => 'success',
            'data' => $submissions,
        ]);
    }

    /**
     * Get all submissions for a specific user (admin only)
     *
     * @param  int  $userId
     * @return \Illuminate\Http\Response
     */
    public function getUserSubmissionsById($userId)
    {
        $submissions = FeedbackSubmission::where('user_id', $userId)
            ->with(['feedbackContent', 'answers.question'])
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $submissions,
        ]);
    }
}
