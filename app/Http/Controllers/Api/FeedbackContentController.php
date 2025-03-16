<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Block;
use App\Models\FeedbackContent;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Schema(
 *     schema="FeedbackContent",
 *     required={"activity_id", "title", "description", "questions"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="activity_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="title", type="string", example="Course Feedback Survey"),
 *     @OA\Property(property="description", type="string", example="Please provide your feedback on the certification course"),
 *     @OA\Property(property="is_anonymous", type="boolean", example=true),
 *     @OA\Property(
 *         property="questions",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="id", type="string", example="q1"),
 *             @OA\Property(property="text", type="string", example="How would you rate the overall course content?"),
 *             @OA\Property(property="type", type="string", enum={"rating", "text", "multiple_choice", "checkbox"}, example="rating"),
 *             @OA\Property(property="required", type="boolean", example=true),
 *             @OA\Property(property="order", type="integer", example=1),
 *             @OA\Property(
 *                 property="options",
 *                 type="array",
 *                 @OA\Items(
 *                     type="object",
 *                     @OA\Property(property="value", type="string", example="excellent"),
 *                     @OA\Property(property="label", type="string", example="Excellent")
 *                 )
 *             )
 *         )
 *     ),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */

class FeedbackContentController extends Controller
{
    /**
     * Store a newly created feedback content in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/activities/{activityId}/feedback-content",
     *     summary="Create feedback content for an activity",
     *     description="Creates new feedback content for a feedback-type activity",
     *     operationId="storeFeedbackContent",
     *     tags={"Content Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         required=true,
     *         description="ID of the activity",
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Feedback content data",
     *         @OA\JsonContent(
     *             required={"title", "description", "questions"},
     *             @OA\Property(property="title", type="string", example="Course Feedback Survey"),
     *             @OA\Property(property="description", type="string", example="Please provide your feedback on the certification course"),
     *             @OA\Property(property="is_anonymous", type="boolean", example=true),
     *             @OA\Property(
     *                 property="questions",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="string", example="q1"),
     *                     @OA\Property(property="text", type="string", example="How would you rate the overall course content?"),
     *                     @OA\Property(property="type", type="string", enum={"rating", "text", "multiple_choice", "checkbox"}, example="rating"),
     *                     @OA\Property(property="required", type="boolean", example=true),
     *                     @OA\Property(property="order", type="integer", example=1),
     *                     @OA\Property(
     *                         property="options",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="value", type="string", example="excellent"),
     *                             @OA\Property(property="label", type="string", example="Excellent")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Feedback content created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Feedback content created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/FeedbackContent")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid activity type"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Activity not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request, $activityId)
    {
        $activity = Activity::findOrFail($activityId);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        // Check if user has permission to add content to this activity
        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to add content to this activity',
            ], Response::HTTP_FORBIDDEN);
        }

        // Validate activity type
        if ($activity->type !== 'feedback') {
            return response()->json([
                'status' => 'error',
                'message' => 'This activity is not of type feedback',
            ], Response::HTTP_BAD_REQUEST);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'feedback_type' => 'required|string|in:survey,evaluation,rating,open_ended',
            'is_anonymous' => 'boolean',
            'is_required' => 'boolean',
            'show_results_to_participants' => 'boolean',
            'questions' => 'required|array|min:1',
            'questions.*.question_text' => 'required|string',
            'questions.*.question_type' => 'required|string|in:multiple_choice,rating,text,likert_scale,yes_no',
            'questions.*.is_required' => 'boolean',
            'questions.*.options' => 'required_if:questions.*.question_type,multiple_choice,likert_scale|array',
            'questions.*.options.*' => 'required_with:questions.*.options|string',
            'questions.*.min_rating' => 'required_if:questions.*.question_type,rating|integer|min:1',
            'questions.*.max_rating' => 'required_if:questions.*.question_type,rating|integer|gt:questions.*.min_rating',
            'questions.*.min_label' => 'nullable|string|max:50',
            'questions.*.max_label' => 'nullable|string|max:50',
            'target_activities' => 'nullable|array',
            'target_activities.*' => 'integer|exists:activities,id',
            'due_days' => 'nullable|integer', // Days from enrollment to complete
            'reminder_days' => 'nullable|integer', // Days before due date to send reminder
            'allow_multiple_submissions' => 'boolean',
            'submission_limit' => 'required_if:allow_multiple_submissions,true|nullable|integer|min:1',
            'thank_you_message' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check if feedback content already exists for this activity
        $existingContent = FeedbackContent::where('activity_id', $activityId)->first();
        if ($existingContent) {
            return response()->json([
                'status' => 'error',
                'message' => 'Feedback content already exists for this activity',
            ], Response::HTTP_CONFLICT);
        }

        // Prepare data for storage
        $data = $request->except(['questions', 'target_activities']);
        
        // Handle arrays that need to be stored as JSON
        if ($request->has('questions')) {
            $data['questions'] = json_encode($request->questions);
        }
        
        if ($request->has('target_activities')) {
            $data['target_activities'] = json_encode($request->target_activities);
        }
        
        // Add activity_id to data
        $data['activity_id'] = $activityId;

        $feedbackContent = FeedbackContent::create($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Feedback content created successfully',
            'data' => $feedbackContent,
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified feedback content.
     *
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/activities/{activityId}/feedback-content",
     *     summary="Get feedback content for an activity",
     *     description="Gets the feedback content for a feedback-type activity",
     *     operationId="getFeedbackContent",
     *     tags={"Content Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         required=true,
     *         description="ID of the activity",
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Feedback content retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/FeedbackContent")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Activity not found"
     *     )
     * )
     */
    public function show($activityId)
    {
        $activity = Activity::findOrFail($activityId);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        // Check if user has access to this template
        if (!$template->is_public && $template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to view this content',
            ], Response::HTTP_FORBIDDEN);
        }

        $feedbackContent = FeedbackContent::where('activity_id', $activityId)->firstOrFail();
        
        // Decode JSON fields for the response
        if ($feedbackContent->questions) {
            $feedbackContent->questions = json_decode($feedbackContent->questions);
        }
        
        if ($feedbackContent->target_activities) {
            $feedbackContent->target_activities = json_decode($feedbackContent->target_activities);
        }

        return response()->json([
            'status' => 'success',
            'data' => $feedbackContent,
        ]);
    }

    /**
     * Update the specified feedback content in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Put(
     *     path="/activities/{activityId}/feedback-content",
     *     summary="Update feedback content for an activity",
     *     description="Updates the feedback content for a feedback-type activity",
     *     operationId="updateFeedbackContent",
     *     tags={"Content Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         required=true,
     *         description="ID of the activity",
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Feedback content data",
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="Course Feedback Survey"),
     *             @OA\Property(property="description", type="string", example="Please provide your feedback on the certification course"),
     *             @OA\Property(property="is_anonymous", type="boolean", example=true),
     *             @OA\Property(
     *                 property="questions",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="string", example="q1"),
     *                     @OA\Property(property="text", type="string", example="How would you rate the overall course content?"),
     *                     @OA\Property(property="type", type="string", enum={"rating", "text", "multiple_choice", "checkbox"}, example="rating"),
     *                     @OA\Property(property="required", type="boolean", example=true),
     *                     @OA\Property(property="order", type="integer", example=1),
     *                     @OA\Property(
     *                         property="options",
     *                         type="array",
     *                         @OA\Items(
     *                             type="object",
     *                             @OA\Property(property="value", type="string", example="excellent"),
     *                             @OA\Property(property="label", type="string", example="Excellent")
     *                         )
     *                     )
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Feedback content updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Feedback content updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/FeedbackContent")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Activity not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update(Request $request, $activityId)
    {
        $activity = Activity::findOrFail($activityId);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        // Check if user has permission to update this content
        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to update this content',
            ], Response::HTTP_FORBIDDEN);
        }

        $feedbackContent = FeedbackContent::where('activity_id', $activityId)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'title' => 'string|max:255',
            'description' => 'string',
            'feedback_type' => 'string|in:survey,evaluation,rating,open_ended',
            'is_anonymous' => 'boolean',
            'is_required' => 'boolean',
            'show_results_to_participants' => 'boolean',
            'questions' => 'array|min:1',
            'questions.*.question_text' => 'required_with:questions|string',
            'questions.*.question_type' => 'required_with:questions|string|in:multiple_choice,rating,text,likert_scale,yes_no',
            'questions.*.is_required' => 'boolean',
            'questions.*.options' => 'required_if:questions.*.question_type,multiple_choice,likert_scale|array',
            'questions.*.options.*' => 'required_with:questions.*.options|string',
            'questions.*.min_rating' => 'required_if:questions.*.question_type,rating|integer|min:1',
            'questions.*.max_rating' => 'required_if:questions.*.question_type,rating|integer|gt:questions.*.min_rating',
            'questions.*.min_label' => 'nullable|string|max:50',
            'questions.*.max_label' => 'nullable|string|max:50',
            'target_activities' => 'nullable|array',
            'target_activities.*' => 'integer|exists:activities,id',
            'due_days' => 'nullable|integer',
            'reminder_days' => 'nullable|integer',
            'allow_multiple_submissions' => 'boolean',
            'submission_limit' => 'required_if:allow_multiple_submissions,true|nullable|integer|min:1',
            'thank_you_message' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Prepare data for update
        $updateData = $request->except(['questions', 'target_activities']);
        
        // Handle arrays that need to be stored as JSON
        if ($request->has('questions')) {
            $updateData['questions'] = json_encode($request->questions);
        }
        
        if ($request->has('target_activities')) {
            $updateData['target_activities'] = json_encode($request->target_activities);
        }

        $feedbackContent->update($updateData);

        // Decode JSON fields for the response
        if ($feedbackContent->questions) {
            $feedbackContent->questions = json_decode($feedbackContent->questions);
        }
        
        if ($feedbackContent->target_activities) {
            $feedbackContent->target_activities = json_decode($feedbackContent->target_activities);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Feedback content updated successfully',
            'data' => $feedbackContent,
        ]);
    }

    /**
     * Remove the specified feedback content from storage.
     *
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Delete(
     *     path="/activities/{activityId}/feedback-content",
     *     summary="Delete feedback content for an activity",
     *     description="Deletes the feedback content for a feedback-type activity",
     *     operationId="deleteFeedbackContent",
     *     tags={"Content Types"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="activityId",
     *         in="path",
     *         required=true,
     *         description="ID of the activity",
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Feedback content deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Feedback content deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Activity not found"
     *     )
     * )
     */
    public function destroy($activityId)
    {
        $activity = Activity::findOrFail($activityId);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        // Check if user has permission to delete this content
        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to delete this content',
            ], Response::HTTP_FORBIDDEN);
        }

        $feedbackContent = FeedbackContent::where('activity_id', $activityId)->firstOrFail();
        $feedbackContent->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Feedback content deleted successfully',
        ]);
    }
}
