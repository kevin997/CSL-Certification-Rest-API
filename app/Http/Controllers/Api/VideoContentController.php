<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Block;
use App\Models\Template;
use App\Models\VideoContent;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Schema(
 *     schema="VideoContent",
 *     required={"activity_id", "url", "provider", "duration"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="activity_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="url", type="string", example="https://www.youtube.com/watch?v=dQw4w9WgXcQ"),
 *     @OA\Property(property="provider", type="string", enum={"youtube", "vimeo", "wistia", "custom"}, example="youtube"),
 *     @OA\Property(property="duration", type="integer", example=180, description="Duration in seconds"),
 *     @OA\Property(property="thumbnail_url", type="string", example="https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg", nullable=true),
 *     @OA\Property(property="transcript", type="string", example="This is the video transcript...", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */

class VideoContentController extends Controller
{
    /**
     * Store a newly created video content in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/activities/{activityId}/video-content",
     *     summary="Create video content for an activity",
     *     description="Creates new video content for a video-type activity",
     *     operationId="storeVideoContent",
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
     *         description="Video content data",
     *         @OA\JsonContent(
     *             required={"url", "provider", "duration"},
     *             @OA\Property(property="url", type="string", example="https://www.youtube.com/watch?v=dQw4w9WgXcQ"),
     *             @OA\Property(property="provider", type="string", enum={"youtube", "vimeo", "wistia", "custom"}, example="youtube"),
     *             @OA\Property(property="duration", type="integer", example=180, description="Duration in seconds"),
     *             @OA\Property(property="thumbnail_url", type="string", example="https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg", nullable=true),
     *             @OA\Property(property="transcript", type="string", example="This is the video transcript...", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Video content created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Video content created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/VideoContent")
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
        if ($activity->type->value !== 'video') {
            return response()->json([
                'status' => 'error',
                'message' => 'This activity is not of type video',
            ], Response::HTTP_BAD_REQUEST);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'video_url' => 'required|string|url',
            'video_type' => 'required|string|in:youtube,vimeo,mp4,webm',
            'duration' => 'nullable|integer',
            'thumbnail_url' => 'nullable|string|url',
            'transcript' => 'nullable|string',
            'captions_url' => 'nullable|string|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Check if video content already exists for this activity
        $existingContent = VideoContent::where('activity_id', $activityId)->first();
        if ($existingContent) {
            return response()->json([
                'status' => 'error',
                'message' => 'Video content already exists for this activity',
            ], Response::HTTP_CONFLICT);
        }

        $videoContent = VideoContent::create([
            'activity_id' => $activityId,
            'title' => $request->title,
            'description' => $request->description,
            'video_url' => $request->video_url,
            'video_type' => $request->video_type,
            'duration' => $request->duration,
            'thumbnail_url' => $request->thumbnail_url,
            'transcript' => $request->transcript,
            'captions_url' => $request->captions_url,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Video content created successfully',
            'data' => $videoContent,
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified video content.
     *
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/activities/{activityId}/video-content",
     *     summary="Get video content for an activity",
     *     description="Gets video content for a video-type activity",
     *     operationId="getVideoContent",
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
     *         description="Video content retrieved successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/VideoContent")
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

        $videoContent = VideoContent::where('activity_id', $activityId)->firstOrFail();

        return response()->json([
            'status' => 'success',
            'data' => $videoContent,
        ]);
    }

    /**
     * Update the specified video content in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Put(
     *     path="/activities/{activityId}/video-content",
     *     summary="Update video content for an activity",
     *     description="Updates video content for a video-type activity",
     *     operationId="updateVideoContent",
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
     *         description="Video content data",
     *         @OA\JsonContent(
     *             @OA\Property(property="title", type="string", example="New title"),
     *             @OA\Property(property="description", type="string", example="New description", nullable=true),
     *             @OA\Property(property="video_url", type="string", example="https://www.youtube.com/watch?v=dQw4w9WgXcQ"),
     *             @OA\Property(property="video_type", type="string", enum={"youtube", "vimeo", "mp4", "webm"}, example="youtube"),
     *             @OA\Property(property="duration", type="integer", example=180, description="Duration in seconds", nullable=true),
     *             @OA\Property(property="thumbnail_url", type="string", example="https://img.youtube.com/vi/dQw4w9WgXcQ/maxresdefault.jpg", nullable=true),
     *             @OA\Property(property="transcript", type="string", example="This is the video transcript...", nullable=true),
     *             @OA\Property(property="captions_url", type="string", example="https://example.com/captions.vtt", nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Video content updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Video content updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/VideoContent")
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

        $videoContent = VideoContent::where('activity_id', $activityId)->firstOrFail();

        $validator = Validator::make($request->all(), [
            'title' => 'string|max:255',
            'description' => 'nullable|string',
            'video_url' => 'string|url',
            'video_type' => 'string|in:youtube,vimeo,mp4,webm',
            'duration' => 'nullable|integer',
            'thumbnail_url' => 'nullable|string|url',
            'transcript' => 'nullable|string',
            'captions_url' => 'nullable|string|url',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $videoContent->update($request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Video content updated successfully',
            'data' => $videoContent,
        ]);
    }

    /**
     * Remove the specified video content from storage.
     *
     * @param  int  $activityId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Delete(
     *     path="/activities/{activityId}/video-content",
     *     summary="Delete video content for an activity",
     *     description="Deletes video content for a video-type activity",
     *     operationId="deleteVideoContent",
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
     *         description="Video content deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Video content deleted successfully")
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

        $videoContent = VideoContent::where('activity_id', $activityId)->firstOrFail();
        $videoContent->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Video content deleted successfully',
        ]);
    }
}
