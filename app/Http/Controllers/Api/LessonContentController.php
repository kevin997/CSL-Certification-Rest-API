<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Block;
use App\Models\LessonContent;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

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
        if ($activity->type !== 'lesson') {
            return response()->json([
                'status' => 'error',
                'message' => 'This activity is not of type lesson',
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

        $lessonContent = LessonContent::create([
            'activity_id' => $activityId,
            'title' => $request->title,
            'description' => $request->description,
            'content' => $request->content,
            'format' => $request->format,
            'estimated_duration' => $request->estimated_duration,
            'resources' => $request->has('resources') ? json_encode($request->resources) : null,
        ]);

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

        $lessonContent = LessonContent::where('activity_id', $activityId)->firstOrFail();
        
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
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Prepare data for update
        $updateData = $request->except('resources');
        
        // Handle resources separately to encode as JSON
        if ($request->has('resources')) {
            $updateData['resources'] = json_encode($request->resources);
        }

        $lessonContent->update($updateData);

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
