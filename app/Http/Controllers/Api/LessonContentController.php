<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Block;
use App\Models\LessonContent;
use App\Models\LessonContentPart;
use App\Models\LessonQuestion;
use App\Models\LessonQuestionOption;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Schema(
 *     schema="LessonContent",
 *     required={"activity_id", "title", "content"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="activity_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="title", type="string", example="Introduction to Certification Standards"),
 *     @OA\Property(property="content", type="string", example="This lesson covers the fundamental concepts of certification standards..."),
 *     @OA\Property(property="estimated_duration", type="integer", example=30, description="Estimated duration in minutes"),
 *     @OA\Property(
 *         property="resources",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="title", type="string", example="Certification Standards Guide"),
 *             @OA\Property(property="type", type="string", enum={"pdf", "link", "image", "document"}, example="pdf"),
 *             @OA\Property(property="url", type="string", example="https://example.com/resources/guide.pdf"),
 *             @OA\Property(property="description", type="string", example="Comprehensive guide to certification standards", nullable=true)
 *         )
 *     ),
 *     @OA\Property(
 *         property="objectives",
 *         type="array",
 *         @OA\Items(type="string", example="Understand the purpose of certification standards")
 *     ),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */

class LessonContentController extends Controller
{
    /**
     * Store a newly created lesson content in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/activities/{activityId}/lesson-content",
     *     summary="Create lesson content for an activity",
     *     description="Creates new lesson content for a lesson-type activity",
     *     operationId="storeLessonContent",
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
     *         description="Lesson content data",
     *         @OA\JsonContent(
     *             required={"title", "content"},
     *             @OA\Property(property="title", type="string", example="Introduction to Certification Standards"),
     *             @OA\Property(property="content", type="string", example="This lesson covers the fundamental concepts of certification standards..."),
     *             @OA\Property(property="estimated_duration", type="integer", example=30, description="Estimated duration in minutes"),
     *             @OA\Property(
     *                 property="resources",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="title", type="string", example="Certification Standards Guide"),
     *                     @OA\Property(property="type", type="string", enum={"pdf", "link", "image", "document"}, example="pdf"),
     *                     @OA\Property(property="url", type="string", example="https://example.com/resources/guide.pdf"),
     *                     @OA\Property(property="description", type="string", example="Comprehensive guide to certification standards", nullable=true)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="objectives",
     *                 type="array",
     *                 @OA\Items(type="string", example="Understand the purpose of certification standards")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Lesson content created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Lesson content created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/LessonContent")
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
        if ($activity->type->value !== 'lesson') {
            return response()->json([
                'status' => 'error',
                'message' => 'This activity is not of type lesson',
                'activity_type' => $activity->type->value,
                'expected_type' => 'lesson'
            ], Response::HTTP_BAD_REQUEST);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'required|string',
            'format' => 'required|string|in:plain,markdown,html,wysiwyg',
            'estimated_duration' => 'nullable|integer', // in minutes
            'resources' => 'nullable|array',
            'resources.*.title' => 'required_with:resources|string|max:255',
            'resources.*.url' => 'required_with:resources|string|url',
            'resources.*.type' => 'required_with:resources|string|in:pdf,video,link,image,audio',
            'resources.*.description' => 'nullable|string',
            'introduction' => 'nullable|string',
            'conclusion' => 'nullable|string',
            'enable_discussion' => 'nullable|boolean',
            'enable_instructor_feedback' => 'nullable|boolean',
            'enable_questions' => 'nullable|boolean',
            'show_results' => 'nullable|boolean',
            'content_parts' => 'nullable|array',
            'content_parts.*.title' => 'required_with:content_parts|string|max:255',
            'content_parts.*.content_type' => 'required_with:content_parts|string|in:wysiwyg,video',
            'content_parts.*.content' => 'nullable|string',
            'content_parts.*.video_url' => 'nullable|string|url',
            'content_parts.*.video_provider' => 'nullable|string',
            'content_parts.*.order' => 'nullable|integer',
            'questions' => 'nullable|array',
            'questions.*.question' => 'required_with:questions|string',
            'questions.*.question_type' => 'required|string|in:multiple_choice,multiple_response,true_false,text,fill_blanks_text,fill_blanks_drag,matching,hotspot,essay,questionnaire,matrix,drag_and_drop,ordering,code_snippet,multi_select,short_answer,fill_in_blank',
            'questions.*.is_scorable' => 'nullable|boolean',
            'questions.*.points' => 'nullable|integer',
            'questions.*.order' => 'nullable|integer',
            'questions.*.content_part_id' => 'nullable|integer',
            'questions.*.options' => 'nullable|array',
            'questions.*.options.*.option_text' => 'required_with:questions.*.options|string',
            'questions.*.options.*.is_correct' => 'nullable|boolean',
            'questions.*.options.*.feedback' => 'nullable|string',
            'questions.*.options.*.order' => 'nullable|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check if lesson content already exists for this activity
        $existingContent = LessonContent::where('activity_id', $activityId)->first();
        if ($existingContent) {
            return response()->json([
                'status' => 'error',
                'message' => 'Lesson content already exists for this activity',
            ], Response::HTTP_CONFLICT);
        }

        // Create the lesson content
        $lessonContent = LessonContent::create([
            'activity_id' => $activityId,
            'title' => $request->title,
            'description' => $request->description,
            'content' => $request->content,
            'format' => $request->format,
            'estimated_duration' => $request->estimated_duration,
            'resources' => $request->has('resources') ? json_encode($request->resources) : null,
            'introduction' => $request->introduction,
            'conclusion' => $request->conclusion,
            'enable_discussion' => $request->enable_discussion ?? false,
            'enable_instructor_feedback' => $request->enable_instructor_feedback ?? false,
            'enable_questions' => $request->enable_questions ?? false,
            'show_results' => $request->show_results ?? false,
        ]);

        // Create content parts if provided
        if ($request->has('content_parts') && is_array($request->content_parts)) {
            foreach ($request->content_parts as $index => $partData) {
                $lessonContent->contentParts()->create([
                    'title' => $partData['title'],
                    'content_type' => $partData['content_type'],
                    'content' => $partData['content'] ?? null,
                    'video_url' => $partData['video_url'] ?? null,
                    'video_provider' => $partData['video_provider'] ?? null,
                    'order' => $partData['order'] ?? $index,
                    'created_by' => Auth::id(),
                ]);
            }
        }

        // Create questions if provided
        if ($request->has('questions') && is_array($request->questions)) {
            foreach ($request->questions as $index => $questionData) {
                $question = $lessonContent->questions()->create([
                    'question' => $questionData['question'],
                    'question_type' => $questionData['question_type'],
                    'is_scorable' => $questionData['is_scorable'] ?? false,
                    'points' => $questionData['points'] ?? 0,
                    'order' => $questionData['order'] ?? $index,
                    'content_part_id' => $questionData['content_part_id'] ?? null,
                    'created_by' => Auth::id(),
                ]);

                // Store additional question data based on question type
                if (isset($questionData['explanation'])) {
                    $question->explanation = $questionData['explanation'];
                }
                if (isset($questionData['question_text'])) {
                    $question->question_text = $questionData['question_text'];
                }
                if (isset($questionData['title'])) {
                    $question->title = $questionData['title'];
                }
                if (isset($questionData['image_url'])) {
                    $question->image_url = $questionData['image_url'];
                }
                if (isset($questionData['image_alt'])) {
                    $question->image_alt = $questionData['image_alt'];
                }

                // Handle question type specific data
                switch ($questionData['question_type']) {
                    case 'fill_blanks_text':
                    case 'fill_blanks_drag':
                    case 'fill_in_blank':
                        if (isset($questionData['blanks']) && is_array($questionData['blanks'])) {
                            $question->blanks = $questionData['blanks'];
                        }
                        break;

                    case 'matrix':
                        if (isset($questionData['matrix_rows']) && is_array($questionData['matrix_rows'])) {
                            $question->matrix_rows = $questionData['matrix_rows'];
                        }
                        if (isset($questionData['matrix_columns']) && is_array($questionData['matrix_columns'])) {
                            $question->matrix_columns = $questionData['matrix_columns'];
                        }
                        if (isset($questionData['matrix_options']) && is_array($questionData['matrix_options'])) {
                            $question->matrix_options = $questionData['matrix_options'];
                        }
                        break;
                }

                $question->save();

                // Create options for questions that use options
                $optionTypes = [
                    'multiple_choice',
                    'multiple_response',
                    'true_false',
                    'matching',
                    'hotspot',
                    'drag_and_drop',
                    'ordering',
                    'multi_select'
                ];

                if (
                    in_array($questionData['question_type'], $optionTypes) &&
                    isset($questionData['options']) && is_array($questionData['options'])
                ) {
                    foreach ($questionData['options'] as $optIndex => $optionData) {
                        $optionData = is_array($optionData) ? $optionData : [];
                        $optionAttributes = [
                            'option_text' => $optionData['option_text'] ?? '',
                            'is_correct' => $optionData['is_correct'] ?? false,
                            'order' => $optionData['order'] ?? $optIndex,
                        ];

                        // Add type-specific option attributes
                        if (isset($optionData['feedback'])) {
                            $optionAttributes['feedback'] = $optionData['feedback'];
                        }

                        if ($questionData['question_type'] === 'matching' && isset($optionData['match_text'])) {
                            $optionAttributes['match_text'] = $optionData['match_text'];
                        }

                        if ($questionData['question_type'] === 'hotspot' && isset($optionData['position'])) {
                            $optionAttributes['position'] = $optionData['position'];
                        }

                        $question->options()->create($optionAttributes);
                    }
                }
            }
        }

        // Load relationships for the response
        $lessonContent->load(['contentParts', 'questions.options']);

        return response()->json([
            'status' => 'success',
            'message' => 'Lesson content created successfully',
            'data' => $lessonContent,
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified lesson content.
     *
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/activities/{activityId}/lesson-content",
     *     summary="Get lesson content for an activity",
     *     description="Retrieves lesson content for a lesson-type activity",
     *     operationId="getLessonContent",
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
     *         description="Lesson content retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/LessonContent")
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

        $lessonContent = LessonContent::with(['contentParts', 'questions.options'])
            ->where('activity_id', $activityId)
            ->firstOrFail();

        // Decode the resources JSON for the response
        if ($lessonContent->resources) {
            $lessonContent->resources = json_decode($lessonContent->resources);
        }

        return response()->json([
            'status' => 'success',
            'data' => $lessonContent,
        ]);
    }

    /**
     * Update the specified lesson content in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Put(
     *     path="/activities/{activityId}/lesson-content",
     *     summary="Update lesson content for an activity",
     *     description="Updates lesson content for a lesson-type activity",
     *     operationId="updateLessonContent",
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
     *         description="Lesson content data",
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="Introduction to Certification Standards"),
     *             @OA\Property(property="content", type="string", example="This lesson covers the fundamental concepts of certification standards..."),
     *             @OA\Property(property="estimated_duration", type="integer", example=30, description="Estimated duration in minutes"),
     *             @OA\Property(
     *                 property="resources",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="title", type="string", example="Certification Standards Guide"),
     *                     @OA\Property(property="type", type="string", enum={"pdf", "link", "image", "document"}, example="pdf"),
     *                     @OA\Property(property="url", type="string", example="https://example.com/resources/guide.pdf"),
     *                     @OA\Property(property="description", type="string", example="Comprehensive guide to certification standards", nullable=true)
     *                 )
     *             ),
     *             @OA\Property(
     *                 property="objectives",
     *                 type="array",
     *                 @OA\Items(type="string", example="Understand the purpose of certification standards")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Lesson content updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Lesson content updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/LessonContent")
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

        $lessonContent = LessonContent::where('activity_id', $activityId)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'content' => 'string',
            'format' => 'string|in:plain,markdown,html,wysiwyg',
            'estimated_duration' => 'nullable|integer',
            'resources' => 'nullable|array',
            'resources.*.title' => 'required_with:resources|string|max:255',
            'resources.*.url' => 'required_with:resources|string|url',
            'resources.*.type' => 'required_with:resources|string|in:pdf,video,link,image,audio',
            'resources.*.description' => 'nullable|string',
            'introduction' => 'nullable|string',
            'conclusion' => 'nullable|string',
            'enable_discussion' => 'nullable|boolean',
            'enable_instructor_feedback' => 'nullable|boolean',
            'enable_questions' => 'nullable|boolean',
            'show_results' => 'nullable|boolean',
            'content_parts' => 'nullable|array',
            'content_parts.*.id' => 'nullable|integer|exists:lesson_content_parts,id',
            'content_parts.*.title' => 'required_with:content_parts|string|max:255',
            'content_parts.*.content_type' => 'required_with:content_parts|string|in:wysiwyg,video',
            'content_parts.*.content' => 'nullable|string',
            'content_parts.*.video_url' => 'nullable|string|url',
            'content_parts.*.video_provider' => 'nullable|string',
            'content_parts.*.order' => 'nullable|integer',
            'content_parts.*.deleted' => 'nullable|boolean',
            'questions' => 'nullable|array',
            'questions.*.id' => 'nullable|integer|exists:lesson_questions,id',
            'questions.*.question' => 'required_with:questions|string',
            'questions.*.question_type' => 'required|string|in:multiple_choice,multiple_response,true_false,text,fill_blanks_text,fill_blanks_drag,matching,hotspot,essay,questionnaire,matrix,drag_and_drop,ordering,code_snippet,multi_select,short_answer,fill_in_blank',
            'questions.*.is_scorable' => 'nullable|boolean',
            'questions.*.points' => 'nullable|integer',
            'questions.*.order' => 'nullable|integer',
            'questions.*.content_part_id' => 'nullable|integer',
            'questions.*.deleted' => 'nullable|boolean',
            'questions.*.options' => 'nullable|array',
            'questions.*.options.*.id' => 'nullable|integer|exists:lesson_question_options,id',
            'questions.*.options.*.option_text' => 'required_with:questions.*.options|string',
            'questions.*.options.*.is_correct' => 'nullable|boolean',
            'questions.*.options.*.feedback' => 'nullable|string',
            'questions.*.options.*.match_text' => 'nullable|string',
            'questions.*.options.*.position' => 'nullable|json',
            'questions.*.options.*.order' => 'nullable|integer',
            'questions.*.options.*.deleted' => 'nullable|boolean',
            'questions.*.image_url' => 'nullable|string',
            'questions.*.image_alt' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Prepare data for update - exclude relationships
        $updateData = $request->except(['resources', 'content_parts', 'questions']);

        // Handle resources separately to encode as JSON
        if ($request->has('resources')) {
            $updateData['resources'] = json_encode($request->resources);
        }

        // Update the lesson content
        $lessonContent->update($updateData);

        // Handle content parts
        if ($request->has('content_parts') && is_array($request->content_parts)) {
            foreach ($request->content_parts as $partData) {
                // Check if this is an existing content part or a new one
                if (isset($partData['id'])) {
                    $contentPart = $lessonContent->contentParts()->find($partData['id']);

                    // If marked for deletion, delete it
                    if (isset($partData['deleted']) && $partData['deleted']) {
                        if ($contentPart) {
                            $contentPart->delete();
                        }
                        continue;
                    }

                    // Otherwise update it
                    if ($contentPart) {
                        $contentPart->update([
                            'title' => $partData['title'],
                            'content_type' => $partData['content_type'],
                            'content' => $partData['content'] ?? $contentPart->content,
                            'video_url' => $partData['video_url'] ?? $contentPart->video_url,
                            'video_provider' => $partData['video_provider'] ?? $contentPart->video_provider,
                            'order' => $partData['order'] ?? $contentPart->order,
                        ]);
                    }
                } else {
                    // Create new content part
                    $lessonContent->contentParts()->create([
                        'title' => $partData['title'],
                        'content_type' => $partData['content_type'],
                        'content' => $partData['content'] ?? null,
                        'video_url' => $partData['video_url'] ?? null,
                        'video_provider' => $partData['video_provider'] ?? null,
                        'order' => $partData['order'] ?? 0,
                        'created_by' => Auth::id(),
                    ]);
                }
            }
        }

        // Handle questions
        if ($request->has('questions') && is_array($request->questions)) {
            // Use transaction for consistency
            DB::transaction(function () use ($lessonContent, $request) {
                // Get all existing question IDs
                $existingQuestionIds = $lessonContent->questions()->pluck('id')->toArray();

                // Get question IDs from request (filter out null IDs and deleted questions)
                $requestQuestionIds = collect($request->questions ?? [])
                    ->filter(function ($questionData) {
                        // Filter out questions marked for deletion
                        return !(isset($questionData['deleted']) && $questionData['deleted']);
                    })
                    ->pluck('id')
                    ->filter() // Remove any null values
                    ->toArray();

                // Find questions that exist in DB but not in request (to be deleted)
                $questionsToDelete = array_diff($existingQuestionIds, $requestQuestionIds);

                // Delete questions not in request
                if (!empty($questionsToDelete)) {
                    $lessonContent->questions()->whereIn('id', $questionsToDelete)->delete();
                }

                // Process questions from request
                if (is_array($request->questions)) {
                    foreach ($request->questions as $questionData) {
                        // Skip questions explicitly marked for deletion
                        if (isset($questionData['deleted']) && $questionData['deleted']) {
                            if (isset($questionData['id'])) {
                                $question = $lessonContent->questions()->find($questionData['id']);
                                if ($question) {
                                    $question->delete();
                                }
                            }
                            continue;
                        }

                        // Check if this is an existing question or a new one
                        if (isset($questionData['id'])) {
                            $question = $lessonContent->questions()->find($questionData['id']);

                            // Update existing question
                            if ($question) {
                                $question->update([
                                    'question' => $questionData['question'],
                                    'question_type' => $questionData['question_type'],
                                    'is_scorable' => $questionData['is_scorable'] ?? $question->is_scorable,
                                    'points' => $questionData['points'] ?? $question->points,
                                    'order' => $questionData['order'] ?? $question->order,
                                    'content_part_id' => $questionData['content_part_id'] ?? $question->content_part_id,
                                    'image_url' => $questionData['image_url'] ?? $question->image_url,
                                    'image_alt' => $questionData['image_alt'] ?? $question->image_alt,
                                    'explanation' => $questionData['explanation'] ?? $question->explanation,
                                    'title' => $questionData['title'] ?? $question->title,
                                ]);

                                // Handle options for this question
                                if (isset($questionData['options']) && is_array($questionData['options'])) {
                                    foreach ($questionData['options'] as $optionData) {
                                        // Check if this is an existing option or a new one
                                        if (isset($optionData['id'])) {
                                            $option = $question->options()->find($optionData['id']);

                                            // If marked for deletion, delete it
                                            if (isset($optionData['deleted']) && $optionData['deleted']) {
                                                if ($option) {
                                                    $option->delete();
                                                }
                                                continue;
                                            }

                                            // Otherwise update it
                                            if ($option) {
                                                $option->update([
                                                    'option_text' => $optionData['option_text'],
                                                    'is_correct' => $optionData['is_correct'] ?? $option->is_correct,
                                                    'feedback' => $optionData['feedback'] ?? $option->feedback,
                                                    'match_text' => $optionData['match_text'] ?? $option->match_text,
                                                    'position' => $optionData['position'] ?? $option->position,
                                                    'order' => $optionData['order'] ?? $option->order,
                                                ]);
                                            }
                                        } else {
                                            // Create new option
                                            $question->options()->create([
                                                'option_text' => $optionData['option_text'],
                                                'is_correct' => $optionData['is_correct'] ?? false,
                                                'feedback' => $optionData['feedback'] ?? null,
                                                'match_text' => $optionData['match_text'] ?? null,
                                                'position' => $optionData['position'] ?? null,
                                                'order' => $optionData['order'] ?? 0,
                                            ]);
                                        }
                                    }

                                    // Also handle option deletion for existing questions
                                    if (isset($questionData['options']) && is_array($questionData['options'])) {
                                        // Get existing option IDs for this question
                                        $existingOptionIds = $question->options()->pluck('id')->toArray();

                                        // Get option IDs from request
                                        $requestOptionIds = collect($questionData['options'])->filter(function ($optionData) {
                                            // Filter out options marked for deletion
                                            return !(isset($optionData['deleted']) && $optionData['deleted']);
                                        })->pluck('id')->filter()->toArray();

                                        // Find options to delete (in DB but not in request)
                                        $optionsToDelete = array_diff($existingOptionIds, $requestOptionIds);

                                        // Delete options not in request
                                        if (!empty($optionsToDelete)) {
                                            $question->options()->whereIn('id', $optionsToDelete)->delete();
                                        }
                                    }
                                }
                            } else {
                                // Create new question
                                $question = $lessonContent->questions()->create([
                                    'question' => $questionData['question'],
                                    'question_type' => $questionData['question_type'],
                                    'is_scorable' => $questionData['is_scorable'] ?? false,
                                    'points' => $questionData['points'] ?? 1,
                                    'order' => $questionData['order'] ?? 0,
                                    'content_part_id' => $questionData['content_part_id'] ?? null,
                                    'image_url' => $questionData['image_url'] ?? null,
                                    'image_alt' => $questionData['image_alt'] ?? null,
                                    'explanation' => $questionData['explanation'] ?? null,
                                    'title' => $questionData['title'] ?? null,
                                    'created_by' => Auth::id(),
                                ]);

                                // Handle options for the new question
                                if (isset($questionData['options']) && is_array($questionData['options'])) {
                                    foreach ($questionData['options'] as $optionData) {
                                        $question->options()->create([
                                            'option_text' => $optionData['option_text'],
                                            'is_correct' => $optionData['is_correct'] ?? false,
                                            'feedback' => $optionData['feedback'] ?? null,
                                            'match_text' => $optionData['match_text'] ?? null,
                                            'position' => $optionData['position'] ?? null,
                                            'order' => $optionData['order'] ?? 0,
                                        ]);
                                    } // End foreach options
                                } // End if has options
                            } // End else (new question)
                        } // End foreach questions
                    }; 
                } 
            }); // End transaction
        }

        // Reload the lesson content with its relationships
        $lessonContent->load(['contentParts', 'questions.options']);

        // Decode the resources JSON for the response
        if ($lessonContent->resources) {
            $lessonContent->resources = json_decode($lessonContent->resources);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Lesson content updated successfully',
            'data' => $lessonContent,
        ]);
    }

    /**
     * Remove the specified lesson content from storage.
     *
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Delete(
     *     path="/activities/{activityId}/lesson-content",
     *     summary="Delete lesson content for an activity",
     *     description="Deletes lesson content for a lesson-type activity",
     *     operationId="deleteLessonContent",
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
     *         description="Lesson content deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Lesson content deleted successfully")
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

        $lessonContent = LessonContent::where('activity_id', $activityId)->firstOrFail();
        $lessonContent->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Lesson content deleted successfully',
        ]);
    }
}
