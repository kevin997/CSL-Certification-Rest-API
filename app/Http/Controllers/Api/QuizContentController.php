<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Block;
use App\Models\QuizContent;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Schema(
 *     schema="QuizContent",
 *     required={"activity_id", "title", "questions", "passing_score"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="activity_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="title", type="string", example="Module 1 Assessment"),
 *     @OA\Property(property="description", type="string", example="Test your knowledge of the concepts covered in Module 1", nullable=true),
 *     @OA\Property(property="time_limit", type="integer", example=600, description="Time limit in seconds", nullable=true),
 *     @OA\Property(property="passing_score", type="integer", example=70, description="Passing score percentage"),
 *     @OA\Property(property="allow_retakes", type="boolean", example=true),
 *     @OA\Property(property="randomize_questions", type="boolean", example=false),
 *     @OA\Property(
 *         property="questions",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             required={"question", "type", "options", "correct_answer"},
 *             @OA\Property(property="question", type="string", example="What is the purpose of a certification platform?"),
 *             @OA\Property(property="type", type="string", enum={"multiple_choice", "true_false", "matching", "short_answer", "essay"}, example="multiple_choice"),
 *             @OA\Property(
 *                 property="options",
 *                 type="array",
 *                 @OA\Items(type="string", example="To provide structured learning paths")
 *             ),
 *             @OA\Property(
 *                 property="correct_answer",
 *                 type="array",
 *                 @OA\Items(type="string")
 *             ),
 *             @OA\Property(property="points", type="integer", example=10)
 *         )
 *     ),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */

class QuizContentController extends Controller
{
    /**
     * Store a newly created quiz content in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/activities/{activityId}/quiz-content",
     *     summary="Create quiz content for an activity",
     *     description="Creates new quiz content for a quiz-type activity",
     *     operationId="storeQuizContent",
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
     *         description="Quiz content data",
     *         @OA\JsonContent(
     *             required={"title", "questions", "passing_score"},
     *             @OA\Property(property="title", type="string", example="Module 1 Assessment"),
     *             @OA\Property(property="description", type="string", example="Test your knowledge of the concepts covered in Module 1", nullable=true),
     *             @OA\Property(property="time_limit", type="integer", example=600, description="Time limit in seconds", nullable=true),
     *             @OA\Property(property="passing_score", type="integer", example=70, description="Passing score percentage"),
     *             @OA\Property(property="allow_retakes", type="boolean", example=true),
     *             @OA\Property(property="randomize_questions", type="boolean", example=false),
     *             @OA\Property(
     *                 property="questions",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"question", "type", "options", "correct_answer"},
     *                     @OA\Property(property="question", type="string", example="What is the purpose of a certification platform?"),
     *                     @OA\Property(property="type", type="string", enum={"multiple_choice", "true_false", "matching", "short_answer", "essay"}, example="multiple_choice"),
     *                     @OA\Property(
     *                         property="options",
     *                         type="array",
     *                         @OA\Items(type="string", example="To provide structured learning paths")
     *                     ),
     *                     @OA\Property(
     *                         property="correct_answer",
     *                         type="array",
     *                         @OA\Items(type="string")
     *                     ),
     *                     @OA\Property(property="points", type="integer", example=10)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Quiz content created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Quiz content created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/QuizContent")
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
        if ($activity->type->value !== 'quiz') {
            return response()->json([
                'status' => 'error',
                'message' => 'This activity is not of type quiz',
            ], Response::HTTP_BAD_REQUEST);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'instructions' => 'nullable|string',
            'instruction_format' => 'nullable|string|in:plain,markdown,html,wysiwyg',
            'time_limit' => 'nullable|integer',
            'passing_score' => 'required|integer|min:0|max:100',
            'show_correct_answers' => 'boolean',
            'randomize_questions' => 'boolean',
            'questions' => 'required|array|min:1',
            'questions.*.title' => 'required|string',
            'questions.*.question_text' => 'required|string',
            'questions.*.question_type' => 'required|string|in:multiple_choice,multiple_response,true_false,text,fill_blanks_text,fill_blanks_drag,matching,hotspot,essay,questionnaire,matrix',
            'questions.*.options' => 'required_if:questions.*.question_type,multiple_choice,multiple_response,matching,hotspot|array',
            'questions.*.options.*.text' => 'required_if:questions.*.question_type,multiple_choice,multiple_response,matching|string',
            'questions.*.options.*.is_correct' => 'required_if:questions.*.question_type,multiple_choice,multiple_response|boolean',
            'questions.*.options.*.match_text' => 'required_if:questions.*.question_type,matching|string',
            'questions.*.options.*.position' => 'required_if:questions.*.question_type,hotspot|array',
            'questions.*.options.*.position.x' => 'required_if:questions.*.question_type,hotspot|numeric',
            'questions.*.options.*.position.y' => 'required_if:questions.*.question_type,hotspot|numeric',
            'questions.*.blanks' => 'required_if:questions.*.question_type,fill_blanks_text,fill_blanks_drag|array',
            'questions.*.blanks.*.text' => 'required_if:questions.*.question_type,fill_blanks_text,fill_blanks_drag|string',
            'questions.*.blanks.*.correct_answer' => 'required_if:questions.*.question_type,fill_blanks_text|string',
            'questions.*.blanks.*.position' => 'required_if:questions.*.question_type,fill_blanks_drag|array',
            'questions.*.blanks.*.position.x' => 'required_if:questions.*.question_type,fill_blanks_drag|numeric',
            'questions.*.blanks.*.position.y' => 'required_if:questions.*.question_type,fill_blanks_drag|numeric',
            'questions.*.matrix_rows' => 'required_if:questions.*.question_type,matrix|array',
            'questions.*.matrix_columns' => 'required_if:questions.*.question_type,matrix|array',
            'questions.*.matrix_options' => 'required_if:questions.*.question_type,matrix|array',
            'questions.*.matrix_options.*.row' => 'required_if:questions.*.question_type,matrix|string',
            'questions.*.matrix_options.*.column' => 'required_if:questions.*.question_type,matrix|string',
            'questions.*.matrix_options.*.is_selected' => 'required_if:questions.*.question_type,matrix|boolean',
            'questions.*.explanation' => 'nullable|string',
            'questions.*.points' => 'required|integer|min:1',
            'questions.*.is_scorable' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check if quiz content already exists for this activity
        $existingContent = QuizContent::where('activity_id', $activityId)->first();
        if ($existingContent) {
            return response()->json([
                'status' => 'error',
                'message' => 'Quiz content already exists for this activity',
            ], Response::HTTP_CONFLICT);
        }

        $quizContent = QuizContent::create([
            'activity_id' => $activityId,
            'title' => $request->title,
            'description' => $request->description,
            'instructions' => $request->instructions,
            'instruction_format' => $request->instruction_format ?? 'markdown',
            'time_limit' => $request->time_limit,
            'passing_score' => $request->passing_score,
            'show_correct_answers' => $request->show_correct_answers ?? false,
            'randomize_questions' => $request->randomize_questions ?? false,
            'questions' => json_encode($request->questions),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Quiz content created successfully',
            'data' => $quizContent,
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified quiz content.
     *
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/activities/{activityId}/quiz-content",
     *     summary="Get quiz content for an activity",
     *     description="Retrieves quiz content for a quiz-type activity",
     *     operationId="getQuizContent",
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
     *         description="Quiz content retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/QuizContent")
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

        $quizContent = QuizContent::where('activity_id', $activityId)->firstOrFail();
        
        // Decode the questions JSON for the response
        $quizContent->questions = json_decode($quizContent->questions);

        return response()->json([
            'status' => 'success',
            'data' => $quizContent,
        ]);
    }

    /**
     * Update the specified quiz content in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Put(
     *     path="/activities/{activityId}/quiz-content",
     *     summary="Update quiz content for an activity",
     *     description="Updates existing quiz content for a quiz-type activity",
     *     operationId="updateQuizContent",
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
     *         description="Quiz content data",
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="Module 1 Assessment"),
     *             @OA\Property(property="description", type="string", example="Test your knowledge of the concepts covered in Module 1", nullable=true),
     *             @OA\Property(property="time_limit", type="integer", example=600, description="Time limit in seconds", nullable=true),
     *             @OA\Property(property="passing_score", type="integer", example=70, description="Passing score percentage"),
     *             @OA\Property(property="allow_retakes", type="boolean", example=true),
     *             @OA\Property(property="randomize_questions", type="boolean", example=false),
     *             @OA\Property(
     *                 property="questions",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     required={"question", "type", "options", "correct_answer"},
     *                     @OA\Property(property="question", type="string", example="What is the purpose of a certification platform?"),
     *                     @OA\Property(property="type", type="string", enum={"multiple_choice", "true_false", "matching", "short_answer", "essay"}, example="multiple_choice"),
     *                     @OA\Property(
     *                         property="options",
     *                         type="array",
     *                         @OA\Items(type="string", example="To provide structured learning paths")
     *                     ),
     *                     @OA\Property(
     *                         property="correct_answer",
     *                         type="array",
     *                         @OA\Items(type="string")
     *                     ),
     *                     @OA\Property(property="points", type="integer", example=10)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Quiz content updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Quiz content updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/QuizContent")
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

        $quizContent = QuizContent::where('activity_id', $activityId)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'time_limit' => 'nullable|integer',
            'passing_score' => 'integer|min:0|max:100',
            'show_correct_answers' => 'boolean',
            'randomize_questions' => 'boolean',
            'questions' => 'array|min:1',
            'questions.*.title' => 'required_with:questions|string',
            'questions.*.question_text' => 'required_with:questions|string',
            'questions.*.question_type' => 'required_with:questions|string|in:multiple_choice,multiple_response,true_false,text,fill_blanks_text,fill_blanks_drag,matching,hotspot,essay,questionnaire,matrix',
            'questions.*.options' => 'required_if:questions.*.question_type,multiple_choice,multiple_response,matching,hotspot|array',
            'questions.*.options.*.text' => 'required_if:questions.*.question_type,multiple_choice,multiple_response,matching|string',
            'questions.*.options.*.is_correct' => 'required_if:questions.*.question_type,multiple_choice,multiple_response|boolean',
            'questions.*.options.*.match_text' => 'required_if:questions.*.question_type,matching|string',
            'questions.*.options.*.position' => 'required_if:questions.*.question_type,hotspot|array',
            'questions.*.options.*.position.x' => 'required_if:questions.*.question_type,hotspot|numeric',
            'questions.*.options.*.position.y' => 'required_if:questions.*.question_type,hotspot|numeric',
            'questions.*.blanks' => 'required_if:questions.*.question_type,fill_blanks_text,fill_blanks_drag|array',
            'questions.*.blanks.*.text' => 'required_if:questions.*.question_type,fill_blanks_text,fill_blanks_drag|string',
            'questions.*.blanks.*.correct_answer' => 'required_if:questions.*.question_type,fill_blanks_text|string',
            'questions.*.blanks.*.position' => 'required_if:questions.*.question_type,fill_blanks_drag|array',
            'questions.*.blanks.*.position.x' => 'required_if:questions.*.question_type,fill_blanks_drag|numeric',
            'questions.*.blanks.*.position.y' => 'required_if:questions.*.question_type,fill_blanks_drag|numeric',
            'questions.*.matrix_rows' => 'required_if:questions.*.question_type,matrix|array',
            'questions.*.matrix_columns' => 'required_if:questions.*.question_type,matrix|array',
            'questions.*.matrix_options' => 'required_if:questions.*.question_type,matrix|array',
            'questions.*.matrix_options.*.row' => 'required_if:questions.*.question_type,matrix|string',
            'questions.*.matrix_options.*.column' => 'required_if:questions.*.question_type,matrix|string',
            'questions.*.matrix_options.*.is_selected' => 'required_if:questions.*.question_type,matrix|boolean',
            'questions.*.explanation' => 'nullable|string',
            'questions.*.points' => 'required_with:questions|integer|min:1',
            'questions.*.is_scorable' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Prepare data for update
        $updateData = $request->except('questions');
        
        // Handle questions separately to encode as JSON
        if ($request->has('questions')) {
            $updateData['questions'] = json_encode($request->questions);
        }

        $quizContent->update($updateData);

        // Decode the questions JSON for the response
        $quizContent->questions = json_decode($quizContent->questions);

        return response()->json([
            'status' => 'success',
            'message' => 'Quiz content updated successfully',
            'data' => $quizContent,
        ]);
    }

    /**
     * Remove the specified quiz content from storage.
     *
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Delete(
     *     path="/activities/{activityId}/quiz-content",
     *     summary="Delete quiz content for an activity",
     *     description="Deletes existing quiz content for a quiz-type activity",
     *     operationId="deleteQuizContent",
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
     *         description="Quiz content deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Quiz content deleted successfully")
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

        $quizContent = QuizContent::where('activity_id', $activityId)->firstOrFail();
        $quizContent->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Quiz content deleted successfully',
        ]);
    }
}
