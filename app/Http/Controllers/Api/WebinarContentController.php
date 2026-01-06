<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Block;
use App\Models\LiveSession;
use App\Models\LiveSessionParticipant;
use App\Models\Template;
use App\Models\WebinarContent;
use App\Models\EnvironmentLiveSettings;
use App\Services\LiveKitService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Tag(
 *     name="Webinar Content",
 *     description="API endpoints for managing webinar activity content"
 * )
 */
class WebinarContentController extends Controller
{
    /**
     * @OA\Post(
     *     path="/activities/{activityId}/webinar-content",
     *     summary="Create webinar content for an activity",
     *     tags={"Webinar Content"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="activityId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=201, description="Webinar content created successfully"),
     *     @OA\Response(response=400, description="Invalid activity type"),
     *     @OA\Response(response=403, description="Forbidden")
     * )
     */
    public function store(Request $request, $activityId): JsonResponse
    {
        $activity = Activity::findOrFail($activityId);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to add content to this activity',
            ], Response::HTTP_FORBIDDEN);
        }

        if ($activity->type->value !== 'webinar') {
            return response()->json([
                'status' => 'error',
                'message' => 'This activity is not of type webinar',
            ], Response::HTTP_BAD_REQUEST);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'scheduled_at' => 'required|date|after:now',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'max_participants' => 'nullable|integer|min:1|max:1000',
            'allow_recording' => 'nullable|boolean',
            'enable_chat' => 'nullable|boolean',
            'enable_qa' => 'nullable|boolean',
            'enable_reactions' => 'nullable|boolean',
            'mute_participants_on_join' => 'nullable|boolean',
            'disable_participant_video' => 'nullable|boolean',
            'access_type' => 'nullable|string|in:enrolled,public,invited',
            'settings' => 'nullable|array',
            'hosts' => 'nullable|array',
            'hosts.*' => 'integer|exists:users,id',
            'co_hosts' => 'nullable|array',
            'co_hosts.*' => 'integer|exists:users,id',
            'join_instructions' => 'nullable|string',
            'prerequisites' => 'nullable|string',
            'create_live_session' => 'nullable|boolean',
            'environment_id' => 'required_if:create_live_session,true|integer|exists:environments,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $existingContent = WebinarContent::where('activity_id', $activityId)->first();
        if ($existingContent) {
            return response()->json([
                'status' => 'error',
                'message' => 'Webinar content already exists for this activity',
            ], Response::HTTP_CONFLICT);
        }

        DB::beginTransaction();
        try {
            $liveSessionId = null;

            if ($request->boolean('create_live_session') && $request->environment_id) {
                $liveSettings = EnvironmentLiveSettings::where('environment_id', $request->environment_id)->first();

                if (!$liveSettings || !$liveSettings->isEnabled()) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Live sessions are not enabled for this environment.',
                    ], Response::HTTP_FORBIDDEN);
                }

                $liveSession = LiveSession::create([
                    'environment_id' => $request->environment_id,
                    'created_by' => Auth::id(),
                    'title' => $request->title,
                    'description' => $request->description,
                    'scheduled_at' => $request->scheduled_at,
                    'max_participants' => $request->max_participants ?? 100,
                    'status' => 'scheduled',
                    'settings' => [
                        'allow_recording' => $request->boolean('allow_recording'),
                        'enable_chat' => $request->boolean('enable_chat', true),
                        'enable_qa' => $request->boolean('enable_qa', true),
                        'mute_participants_on_join' => $request->boolean('mute_participants_on_join', true),
                        'source' => 'webinar_activity',
                        'activity_id' => $activityId,
                    ],
                ]);

                $liveSessionId = $liveSession->id;
            }

            $webinarContent = WebinarContent::create([
                'activity_id' => $activityId,
                'live_session_id' => $liveSessionId,
                'title' => $request->title,
                'description' => $request->description,
                'scheduled_at' => $request->scheduled_at,
                'duration_minutes' => $request->duration_minutes ?? 60,
                'max_participants' => $request->max_participants ?? 100,
                'allow_recording' => $request->boolean('allow_recording'),
                'enable_chat' => $request->boolean('enable_chat', true),
                'enable_qa' => $request->boolean('enable_qa', true),
                'enable_reactions' => $request->boolean('enable_reactions', true),
                'mute_participants_on_join' => $request->boolean('mute_participants_on_join', true),
                'disable_participant_video' => $request->boolean('disable_participant_video'),
                'access_type' => $request->access_type ?? 'enrolled',
                'settings' => $request->settings,
                'hosts' => $request->hosts ?? [Auth::id()],
                'co_hosts' => $request->co_hosts,
                'join_instructions' => $request->join_instructions,
                'prerequisites' => $request->prerequisites,
                'status' => 'scheduled',
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Webinar content created successfully',
                'data' => $webinarContent->load(['liveSession', 'creator']),
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create webinar content: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Get(
     *     path="/activities/{activityId}/webinar-content",
     *     summary="Get webinar content for an activity",
     *     tags={"Webinar Content"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="activityId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Webinar content retrieved")
     * )
     */
    public function show($activityId): JsonResponse
    {
        $activity = Activity::findOrFail($activityId);

        $webinarContent = WebinarContent::where('activity_id', $activityId)
            ->with(['liveSession', 'creator'])
            ->first();

        if (!$webinarContent) {
            return response()->json([
                'status' => 'error',
                'message' => 'Webinar content not found for this activity',
            ], Response::HTTP_NOT_FOUND);
        }

        $webinarContent->syncWithLiveSession();

        return response()->json([
            'status' => 'success',
            'data' => $webinarContent,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/activities/{activityId}/webinar-content",
     *     summary="Update webinar content for an activity",
     *     tags={"Webinar Content"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="activityId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Webinar content updated")
     * )
     */
    public function update(Request $request, $activityId): JsonResponse
    {
        $activity = Activity::findOrFail($activityId);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to update this content',
            ], Response::HTTP_FORBIDDEN);
        }

        $webinarContent = WebinarContent::where('activity_id', $activityId)->first();

        if (!$webinarContent) {
            return response()->json([
                'status' => 'error',
                'message' => 'Webinar content not found for this activity',
            ], Response::HTTP_NOT_FOUND);
        }

        if (in_array($webinarContent->status, ['live', 'completed'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot update webinar that is live or completed',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'scheduled_at' => 'sometimes|date|after:now',
            'duration_minutes' => 'nullable|integer|min:5|max:480',
            'max_participants' => 'nullable|integer|min:1|max:1000',
            'allow_recording' => 'nullable|boolean',
            'enable_chat' => 'nullable|boolean',
            'enable_qa' => 'nullable|boolean',
            'enable_reactions' => 'nullable|boolean',
            'mute_participants_on_join' => 'nullable|boolean',
            'disable_participant_video' => 'nullable|boolean',
            'access_type' => 'nullable|string|in:enrolled,public,invited',
            'settings' => 'nullable|array',
            'hosts' => 'nullable|array',
            'co_hosts' => 'nullable|array',
            'join_instructions' => 'nullable|string',
            'prerequisites' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        DB::beginTransaction();
        try {
            $webinarContent->update($request->only([
                'title',
                'description',
                'scheduled_at',
                'duration_minutes',
                'max_participants',
                'allow_recording',
                'enable_chat',
                'enable_qa',
                'enable_reactions',
                'mute_participants_on_join',
                'disable_participant_video',
                'access_type',
                'settings',
                'hosts',
                'co_hosts',
                'join_instructions',
                'prerequisites',
            ]));

            if ($webinarContent->liveSession) {
                $webinarContent->liveSession->update([
                    'title' => $request->title ?? $webinarContent->title,
                    'description' => $request->description ?? $webinarContent->description,
                    'scheduled_at' => $request->scheduled_at ?? $webinarContent->scheduled_at,
                    'max_participants' => $request->max_participants ?? $webinarContent->max_participants,
                ]);
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => 'Webinar content updated successfully',
                'data' => $webinarContent->fresh(['liveSession', 'creator']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update webinar content: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * @OA\Delete(
     *     path="/activities/{activityId}/webinar-content",
     *     summary="Delete webinar content for an activity",
     *     tags={"Webinar Content"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="activityId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Webinar content deleted")
     * )
     */
    public function destroy($activityId): JsonResponse
    {
        $activity = Activity::findOrFail($activityId);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to delete this content',
            ], Response::HTTP_FORBIDDEN);
        }

        $webinarContent = WebinarContent::where('activity_id', $activityId)->first();

        if (!$webinarContent) {
            return response()->json([
                'status' => 'error',
                'message' => 'Webinar content not found for this activity',
            ], Response::HTTP_NOT_FOUND);
        }

        if ($webinarContent->status === 'live') {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete webinar that is currently live',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $webinarContent->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Webinar content deleted successfully',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/activities/{activityId}/webinar-content/link-session",
     *     summary="Link an existing live session to webinar content",
     *     tags={"Webinar Content"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="activityId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Live session linked successfully")
     * )
     */
    public function linkSession(Request $request, $activityId): JsonResponse
    {
        $activity = Activity::findOrFail($activityId);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to modify this content',
            ], Response::HTTP_FORBIDDEN);
        }

        $validator = Validator::make($request->all(), [
            'live_session_id' => 'required|integer|exists:live_sessions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $webinarContent = WebinarContent::where('activity_id', $activityId)->first();

        if (!$webinarContent) {
            return response()->json([
                'status' => 'error',
                'message' => 'Webinar content not found for this activity',
            ], Response::HTTP_NOT_FOUND);
        }

        $liveSession = LiveSession::findOrFail($request->live_session_id);

        $webinarContent->update([
            'live_session_id' => $liveSession->id,
            'scheduled_at' => $liveSession->scheduled_at,
            'max_participants' => $liveSession->max_participants,
        ]);

        $webinarContent->syncWithLiveSession();

        return response()->json([
            'status' => 'success',
            'message' => 'Live session linked successfully',
            'data' => $webinarContent->fresh(['liveSession', 'creator']),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/activities/{activityId}/webinar-content/token",
     *     summary="Get token to join the webinar",
     *     tags={"Webinar Content"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="activityId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Token generated successfully")
     * )
     */
    public function getToken($activityId): JsonResponse
    {
        $activity = Activity::findOrFail($activityId);

        $webinarContent = WebinarContent::where('activity_id', $activityId)
            ->with('liveSession')
            ->first();

        if (!$webinarContent) {
            return response()->json([
                'status' => 'error',
                'message' => 'Webinar content not found for this activity',
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$webinarContent->liveSession) {
            return response()->json([
                'status' => 'error',
                'message' => 'No live session linked to this webinar',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (!$webinarContent->canJoin()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Webinar is not available for joining at this time',
                'can_join_at' => $webinarContent->scheduled_at?->subMinutes(15)->toIso8601String(),
            ], Response::HTTP_FORBIDDEN);
        }

        $user = Auth::user();
        $liveSession = $webinarContent->liveSession;
        $liveKitService = app(LiveKitService::class);

        // Get or create participant record
        $participant = LiveSessionParticipant::firstOrCreate(
            [
                'live_session_id' => $liveSession->id,
                'user_id' => $user->id,
            ],
            [
                'role' => $liveSession->created_by === $user->id ? 'host' : 'viewer',
            ]
        );

        // Generate appropriate token based on role
        $canPublish = $participant->canPublish();
        $participantIdentity = "user_{$user->id}";
        $participantName = $user->name;

        $token = $liveKitService->generateToken(
            $liveSession->room_name,
            $participantIdentity,
            $participantName,
            $canPublish
        );

        return response()->json([
            'token' => $token,
            'server_url' => $liveKitService->getServerUrl(),
            'room_name' => $liveSession->room_name,
            'role' => $participant->role,
            'can_publish' => $canPublish,
        ]);
    }

    /**
     * @OA\Get(
     *     path="/activities/{activityId}/webinar-content/status",
     *     summary="Get current status of the webinar",
     *     tags={"Webinar Content"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="activityId", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Webinar status retrieved")
     * )
     */
    public function status($activityId): JsonResponse
    {
        $webinarContent = WebinarContent::where('activity_id', $activityId)
            ->with('liveSession')
            ->first();

        if (!$webinarContent) {
            return response()->json([
                'status' => 'error',
                'message' => 'Webinar content not found for this activity',
            ], Response::HTTP_NOT_FOUND);
        }

        $webinarContent->syncWithLiveSession();

        $participantsCount = 0;
        if ($webinarContent->liveSession) {
            $participantsCount = $webinarContent->liveSession->participants_count;
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'webinar_status' => $webinarContent->status,
                'is_live' => $webinarContent->isLive(),
                'can_join' => $webinarContent->canJoin(),
                'has_ended' => $webinarContent->hasEnded(),
                'scheduled_at' => $webinarContent->scheduled_at?->toIso8601String(),
                'participants_count' => $participantsCount,
                'recording_available' => !empty($webinarContent->recording_url),
                'recording_url' => $webinarContent->recording_url,
            ],
        ]);
    }
}
