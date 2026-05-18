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
    private function getActivityContext($activityId): array
    {
        $activity = Activity::findOrFail($activityId);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        return [$activity, $template];
    }

    private function normalizeNullableVideoFields(Request $request): void
    {
        foreach (['video_url', 'thumbnail_url', 'captions_url'] as $field) {
            if ($request->has($field) && $request->input($field) === '') {
                $request->merge([$field => null]);
            }
        }
    }

    private function validateVideoPayload(array $payload, bool $creating = true)
    {
        return Validator::make($payload, [
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'video_url' => ($creating ? 'nullable|string|url|required_without:media_asset_id' : 'nullable|string|url'),
            'media_asset_id' => ($creating ? 'nullable|integer|required_without:video_url' : 'nullable|integer'),
            'video_type' => ($creating ? 'required|string|in:youtube,vimeo,mp4,webm' : 'nullable|string|in:youtube,vimeo,mp4,webm'),
            'duration' => 'nullable|integer',
            'sort_order' => 'nullable|integer|min:0',
            'thumbnail_url' => 'nullable|string|url',
            'transcript' => 'nullable|string',
            'captions_url' => 'nullable|string|url',
        ]);
    }

    private function videoDataFromPayload(array $payload, Activity $activity, ?VideoContent $existing = null): array
    {
        $resolvedTitle = $payload['title'] ?? null;
        if ($resolvedTitle === null || (is_string($resolvedTitle) && trim($resolvedTitle) === '')) {
            $resolvedTitle = $activity->title ?? $existing?->title;
        }

        $data = [
            'title' => $resolvedTitle,
            'description' => $payload['description'] ?? null,
            'video_url' => $payload['video_url'] ?? null,
            'media_asset_id' => $payload['media_asset_id'] ?? null,
            'video_type' => $payload['video_type'] ?? null,
            'duration' => $payload['duration'] ?? null,
            'sort_order' => $payload['sort_order'] ?? null,
            'thumbnail_url' => $payload['thumbnail_url'] ?? null,
            'transcript' => $payload['transcript'] ?? null,
            'captions_url' => $payload['captions_url'] ?? null,
        ];

        return array_filter($data, fn($value) => $value !== null);
    }

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
        [$activity, $template] = $this->getActivityContext($activityId);
        $this->normalizeNullableVideoFields($request);

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

        $payloads = $request->has('videos') ? $request->input('videos') : [$request->all()];
        $catalogueValidator = Validator::make($request->all(), [
            'videos' => 'nullable|array|min:1',
            'videos.*' => 'array',
        ]);

        if ($catalogueValidator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $catalogueValidator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $createdVideos = [];
        $nextSortOrder = (int) VideoContent::where('activity_id', $activityId)->max('sort_order');
        if (VideoContent::where('activity_id', $activityId)->exists()) {
            $nextSortOrder++;
        }

        foreach ($payloads as $index => $payload) {
            $payload = array_map(fn($value) => $value === '' ? null : $value, $payload);
            $validator = $this->validateVideoPayload($payload);

            if ($validator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => [
                        'videos.' . $index => $validator->errors(),
                    ],
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $data = $this->videoDataFromPayload($payload, $activity);
            $data['activity_id'] = $activityId;
            $data['sort_order'] = $payload['sort_order'] ?? $nextSortOrder++;

            $createdVideos[] = VideoContent::create($data);
        }

        $videos = VideoContent::where('activity_id', $activityId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => count($createdVideos) === 1
                ? 'Video content created successfully'
                : 'Video catalogue created successfully',
            'data' => $createdVideos[0],
            'videos' => $videos,
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
        [$activity, $template] = $this->getActivityContext($activityId);

        // Check if user has access to this template
        if (!$template->is_public && $template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to view this content',
            ], Response::HTTP_FORBIDDEN);
        }

        $videos = VideoContent::where('activity_id', $activityId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        if ($videos->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Video content not found for this activity',
            ], Response::HTTP_NOT_FOUND);
        }

        return response()->json([
            'status' => 'success',
            'data' => $videos->first(),
            'videos' => $videos,
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
        [$activity, $template] = $this->getActivityContext($activityId);
        $this->normalizeNullableVideoFields($request);

        // Check if user has permission to update this content
        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to update this content',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($request->has('videos')) {
            $catalogueValidator = Validator::make($request->all(), [
                'videos' => 'required|array|min:1',
                'videos.*' => 'array',
                'videos.*.id' => 'nullable|integer|exists:video_contents,id',
            ]);

            if ($catalogueValidator->fails()) {
                return response()->json([
                    'status' => 'error',
                    'errors' => $catalogueValidator->errors(),
                ], Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $nextSortOrder = (int) VideoContent::where('activity_id', $activityId)->max('sort_order');
            if (VideoContent::where('activity_id', $activityId)->exists()) {
                $nextSortOrder++;
            }

            foreach ($request->input('videos') as $index => $payload) {
                $payload = array_map(fn($value) => $value === '' ? null : $value, $payload);
                $videoContent = isset($payload['id'])
                    ? VideoContent::where('activity_id', $activityId)->findOrFail($payload['id'])
                    : null;

                $validator = $this->validateVideoPayload($payload, $videoContent === null);

                if ($validator->fails()) {
                    return response()->json([
                        'status' => 'error',
                        'errors' => [
                            'videos.' . $index => $validator->errors(),
                        ],
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                $data = $this->videoDataFromPayload($payload, $activity, $videoContent);

                if ($videoContent) {
                    $videoContent->update($data);
                } else {
                    $data['activity_id'] = $activityId;
                    $data['sort_order'] = $payload['sort_order'] ?? $nextSortOrder++;
                    VideoContent::create($data);
                }
            }

            $videos = VideoContent::where('activity_id', $activityId)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get();

            return response()->json([
                'status' => 'success',
                'message' => 'Video catalogue updated successfully',
                'data' => $videos->first(),
                'videos' => $videos,
            ]);
        }

        $videoContent = VideoContent::where('activity_id', $activityId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->firstOrFail();

        $validator = $this->validateVideoPayload($request->all(), false);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $updateData = $this->videoDataFromPayload($request->all(), $activity, $videoContent);
        $videoContent->update($updateData);

        $videos = VideoContent::where('activity_id', $activityId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Video content updated successfully',
            'data' => $videoContent,
            'videos' => $videos,
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
        [$activity, $template] = $this->getActivityContext($activityId);

        // Check if user has permission to delete this content
        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to delete this content',
            ], Response::HTTP_FORBIDDEN);
        }

        $deleted = VideoContent::where('activity_id', $activityId)->delete();

        return response()->json([
            'status' => 'success',
            'message' => $deleted === 1
                ? 'Video content deleted successfully'
                : 'Video catalogue deleted successfully',
        ]);
    }

    /**
     * Remove one video from an activity catalogue.
     */
    public function destroyVideo($activityId, $videoContentId)
    {
        [$activity, $template] = $this->getActivityContext($activityId);

        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to delete this content',
            ], Response::HTTP_FORBIDDEN);
        }

        $videoContent = VideoContent::where('activity_id', $activityId)->findOrFail($videoContentId);
        $videoContent->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Video content deleted successfully',
        ]);
    }
}
