<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Block;
use App\Models\FeedbackContent;
use App\Models\FeedbackQuestion;
use App\Models\FeedbackQuestionOption;
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
        if ($activity->type->value !== 'feedback') {
            return response()->json([
                'status' => 'error',
                'message' => 'This activity is not of type feedback',
            ], Response::HTTP_BAD_REQUEST);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|sometimes',
            'instructions' => 'nullable|string',
            'instruction_format' => 'nullable|string|in:plain,markdown,html,wysiwyg',
            'feedback_type' => 'required|string|in:survey,evaluation,rating,open_ended',
            'allow_anonymous' => 'boolean',
            'completion_message' => 'nullable|string',
            'resource_files' => 'nullable|array',
            'questions' => 'required|array|min:1',
            // Title is optional and will default to question_text if not provided
            'questions.*.title' => 'sometimes|nullable|string|max:255',
            'questions.*.question_text' => 'required|string',
            'questions.*.question_type' => 'required|string|in:text,rating,multiple_choice,checkbox,dropdown,questionnaire',
            'questions.*.required' => 'boolean',
            'questions.*.order' => 'integer',
            'questions.*.options' => 'required_if:questions.*.question_type,multiple_choice,checkbox,dropdown,questionnaire|array',
            'questions.*.options.*.text' => 'sometimes|string',
            'questions.*.options.*.option_text' => 'sometimes|string',
            'questions.*.options.*.subquestion_text' => 'sometimes|nullable|string',
            'questions.*.options.*.answer_option_id' => 'sometimes|nullable|integer',
            'questions.*.options.*.points' => 'sometimes|nullable|integer',
            'questions.*.answer_options' => 'sometimes|array',
            'questions.*.answer_options.*.id' => 'sometimes|integer',
            'questions.*.answer_options.*.text' => 'sometimes|string',
            'target_activities' => 'nullable|array',
            'target_activities.*' => 'integer|exists:activities,id',
            'allow_multiple_submissions' => 'boolean',
            'submission_limit' => 'required_if:allow_multiple_submissions,true|nullable|integer|min:1',
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
            // Instead of returning an error, update the existing content
            return $this->update($request, $activityId);
        }

        // Prepare data for storage
        $data = $request->except(['questions', 'target_activities', 'resource_files']);
        
        // Set arrays directly - Laravel's attribute casting will handle JSON conversion
        if ($request->has('resource_files')) {
            $data['resource_files'] = $request->resource_files;
        }
        
        if ($request->has('target_activities')) {
            $data['target_activities'] = $request->target_activities;
        }
        
        // Add activity_id to data
        $data['activity_id'] = $activityId;
        $data['created_by'] = Auth::id();

        // Create the feedback content
        $feedbackContent = FeedbackContent::create($data);
        
        // Create questions separately
        if ($request->has('questions') && is_array($request->questions)) {
            foreach ($request->questions as $index => $questionData) {
                // Don't skip questions with ID = -1 (frontend placeholder) as they need to be created
                // We just need to remove the ID field since it's not valid for creation
                
                // Create new question with explicit field mapping
                // If title is not provided, use question_text as the title
                $questionText = $questionData['question_text'];
                
                $newQuestionData = [
                    'feedback_content_id' => $feedbackContent->id,
                    'question_text' => $questionText,
                    'question_type' => $questionData['question_type'],
                    'required' => $questionData['required'] ?? false,
                    'order' => $index,
                    'created_by' => Auth::id()
                ];
                
                // Set title if available
                if (isset($questionData['title']) && !empty($questionData['title'])) {
                    $newQuestionData['title'] = $questionData['title'];
                }
                
                // Handle questionnaire type
                if ($questionData['question_type'] === 'questionnaire') {
                    // Set answer_options JSON field
                    if (isset($questionData['answer_options']) && is_array($questionData['answer_options'])) {
                        $newQuestionData['answer_options'] = $questionData['answer_options'];
                    }

                    // Create the question
                    $newQuestion = FeedbackQuestion::create($newQuestionData);

                    // Create feedback_question_options records for subquestions/assignments
                    if (isset($questionData['options']) && is_array($questionData['options'])) {
                        foreach ($questionData['options'] as $optionIndex => $optionData) {
                            FeedbackQuestionOption::create([
                                'feedback_question_id' => $newQuestion->id,
                                'option_text' => $optionData['option_text'] ?? '',
                                'subquestion_text' => $optionData['subquestion_text'] ?? null,
                                'answer_option_id' => $optionData['answer_option_id'] ?? null,
                                'points' => $optionData['points'] ?? 0,
                                'order' => $optionData['order'] ?? $optionIndex,
                                'created_by' => Auth::id()
                            ]);
                        }
                    }
                } else {
                    // Handle legacy question types (text, rating, multiple_choice, checkbox, dropdown)
                    // Set options directly - Laravel's attribute casting will handle JSON conversion
                    if (isset($questionData['options']) && is_array($questionData['options'])) {
                        $newQuestionData['options'] = $questionData['options'];
                    }

                    FeedbackQuestion::create($newQuestionData);
                }
            }
        }
        
        // Get the created feedback content
        $createdFeedbackContent = FeedbackContent::findOrFail($feedbackContent->id);
        
        // Load questions relationship explicitly - this is what the update method does
        $questions = $createdFeedbackContent->questions()
            ->orderBy('order')
            ->get();
            
        // Add questions to the response
        $createdFeedbackContent->questions = $questions;
        
        // No need to decode JSON fields - Laravel's attribute casting already handles this

        return response()->json([
            'status' => 'success',
            'message' => 'Feedback content created successfully',
            'data' => $createdFeedbackContent,
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
        
        // Load questions relationship - Laravel's attribute casting will handle JSON conversion
        $questions = $feedbackContent->questions()
            ->orderBy('order')
            ->get();
            
        // Add questions to the response
        $feedbackContent->questions = $questions;
        
        // No need to decode JSON fields - Laravel's attribute casting already handles this

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
            'description' => 'nullable|string|sometimes',
            'instructions' => 'nullable|string',
            'feedback_type' => 'string|in:survey,evaluation,rating,open_ended',
            'allow_anonymous' => 'boolean',
            'completion_message' => 'nullable|string',
            'resource_files' => 'nullable|array',
            'questions' => 'array|min:1',
            'questions.*.id' => 'nullable|integer',
            // Title is optional and will default to question_text if not provided
            'questions.*.title' => 'sometimes|nullable|string|max:255',
            'questions.*.question_text' => 'required_with:questions|string',
            'questions.*.question_type' => 'required_with:questions|string|in:text,rating,multiple_choice,checkbox,dropdown,questionnaire',
            'questions.*.required' => 'boolean',
            'questions.*.order' => 'integer',
            'questions.*.options' => 'required_if:questions.*.question_type,multiple_choice,checkbox,dropdown,questionnaire|array',
            'questions.*.options.*.text' => 'sometimes|string',
            'questions.*.options.*.option_text' => 'sometimes|string',
            'questions.*.options.*.subquestion_text' => 'sometimes|nullable|string',
            'questions.*.options.*.answer_option_id' => 'sometimes|nullable|integer',
            'questions.*.options.*.points' => 'sometimes|nullable|integer',
            'questions.*.answer_options' => 'sometimes|array',
            'questions.*.answer_options.*.id' => 'sometimes|integer',
            'questions.*.answer_options.*.text' => 'sometimes|string',
            'target_activities' => 'nullable|array',
            'target_activities.*' => 'integer|exists:activities,id',
            'allow_multiple_submissions' => 'boolean',
            'submission_limit' => 'required_if:allow_multiple_submissions,true|nullable|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Prepare data for update
        $updateData = $request->except(['questions', 'target_activities', 'resource_files']);
        
        // Set arrays directly - Laravel's attribute casting will handle JSON conversion
        if ($request->has('resource_files')) {
            $updateData['resource_files'] = $request->resource_files;
        }
        
        if ($request->has('target_activities')) {
            $updateData['target_activities'] = $request->target_activities;
        }

        // Update the feedback content
        $feedbackContent->update($updateData);
        
        // Update questions if provided
        if ($request->has('questions') && is_array($request->questions)) {
            // Get existing question IDs
            $existingQuestionIds = FeedbackQuestion::where('feedback_content_id', $feedbackContent->id)
                ->pluck('id')
                ->toArray();
            
            $updatedQuestionIds = [];
            
            foreach ($request->questions as $index => $questionData) {
                // If question has an ID and it's not -1 (frontend placeholder), update it
                if (isset($questionData['id']) && $questionData['id'] > 0) {
                    $question = FeedbackQuestion::find($questionData['id']);
                    
                    if ($question && $question->feedback_content_id == $feedbackContent->id) {
                        // Update existing question
                        $questionData['order'] = $index;

                        // Handle questionnaire type
                        if ($questionData['question_type'] === 'questionnaire') {
                            // Set answer_options JSON field
                            if (isset($questionData['answer_options']) && is_array($questionData['answer_options'])) {
                                $questionData['answer_options'] = $questionData['answer_options'];
                            }

                            // Clear options JSON field for questionnaire type
                            $questionData['options'] = null;

                            $question->update($questionData);

                            // Delete existing options for this question
                            FeedbackQuestionOption::where('feedback_question_id', $question->id)->delete();

                            // Create new options from the options array
                            if (isset($questionData['options']) && is_array($questionData['options'])) {
                                foreach ($questionData['options'] as $optionIndex => $optionData) {
                                    FeedbackQuestionOption::create([
                                        'feedback_question_id' => $question->id,
                                        'option_text' => $optionData['option_text'] ?? '',
                                        'subquestion_text' => $optionData['subquestion_text'] ?? null,
                                        'answer_option_id' => $optionData['answer_option_id'] ?? null,
                                        'points' => $optionData['points'] ?? 0,
                                        'order' => $optionData['order'] ?? $optionIndex,
                                        'created_by' => Auth::id()
                                    ]);
                                }
                            }

                            $updatedQuestionIds[] = $question->id;
                        } else {
                            // Handle legacy question types
                            // Set options directly - Laravel's attribute casting will handle JSON conversion
                            if (isset($questionData['options']) && is_array($questionData['options'])) {
                                $questionData['options'] = $questionData['options'];
                            }

                            // Clear answer_options for non-questionnaire types
                            $questionData['answer_options'] = null;

                            $question->update($questionData);
                            $updatedQuestionIds[] = $question->id;
                        }
                    }
                } else {
                    // Create new question
                    // If title is not provided, use question_text as the title
                    $questionText = $questionData['question_text'];
                    
                    $newQuestionData = [
                        'feedback_content_id' => $feedbackContent->id,
                        'question_text' => $questionText,
                        'question_type' => $questionData['question_type'],
                        'required' => $questionData['required'] ?? false,
                        'order' => $index,
                        'created_by' => Auth::id()
                    ];
                    
                    // Handle questionnaire type
                    if ($questionData['question_type'] === 'questionnaire') {
                        // Set answer_options JSON field
                        if (isset($questionData['answer_options']) && is_array($questionData['answer_options'])) {
                            $newQuestionData['answer_options'] = $questionData['answer_options'];
                        }

                        $newQuestion = FeedbackQuestion::create($newQuestionData);

                        // Create feedback_question_options records
                        if (isset($questionData['options']) && is_array($questionData['options'])) {
                            foreach ($questionData['options'] as $optionIndex => $optionData) {
                                FeedbackQuestionOption::create([
                                    'feedback_question_id' => $newQuestion->id,
                                    'option_text' => $optionData['option_text'] ?? '',
                                    'subquestion_text' => $optionData['subquestion_text'] ?? null,
                                    'answer_option_id' => $optionData['answer_option_id'] ?? null,
                                    'points' => $optionData['points'] ?? 0,
                                    'order' => $optionData['order'] ?? $optionIndex,
                                    'created_by' => Auth::id()
                                ]);
                            }
                        }

                        $updatedQuestionIds[] = $newQuestion->id;
                    } else {
                        // Handle legacy question types
                        // Set options directly - Laravel's attribute casting will handle JSON conversion
                        if (isset($questionData['options']) && is_array($questionData['options'])) {
                            $newQuestionData['options'] = $questionData['options'];
                        }

                        $newQuestion = FeedbackQuestion::create($newQuestionData);
                        $updatedQuestionIds[] = $newQuestion->id;
                    }
                }
            }
            
            // Delete questions that weren't included in the update
            $questionsToDelete = array_diff($existingQuestionIds, $updatedQuestionIds);
            if (!empty($questionsToDelete)) {
                FeedbackQuestion::whereIn('id', $questionsToDelete)->delete();
            }
        }
        
        // Get updated feedback content
        $updatedFeedbackContent = FeedbackContent::findOrFail($feedbackContent->id);
        
        // Load questions relationship - Laravel's attribute casting will handle JSON conversion
        $questions = $updatedFeedbackContent->questions()
            ->orderBy('order')
            ->get();
            
        // Add questions to the response
        $updatedFeedbackContent->questions = $questions;
        
        // No need to decode JSON fields - Laravel's attribute casting already handles this

        return response()->json([
            'status' => 'success',
            'message' => 'Feedback content updated successfully',
            'data' => $updatedFeedbackContent,
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
