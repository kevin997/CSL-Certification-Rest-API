<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LiveSession;
use App\Models\LiveSessionParticipant;
use App\Models\EnvironmentLiveSettings;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * @OA\Tag(
 *     name="Live Sessions",
 *     description="API endpoints for managing live sessions/webinars"
 * )
 */
class LiveSessionController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/live-sessions",
     *     summary="List live sessions",
     *     tags={"Live Sessions"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(
     *         name="environment_id",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         @OA\Schema(type="string", enum={"scheduled", "live", "ended", "cancelled"})
     *     ),
     *     @OA\Response(response=200, description="List of live sessions")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'environment_id' => 'required|exists:environments,id',
            'status' => 'nullable|in:scheduled,live,ended,cancelled',
        ]);

        $query = LiveSession::where('environment_id', $request->environment_id)
            ->with(['creator:id,name,email', 'course:id,title'])
            ->withCount('participants');

        if ($request->status) {
            $query->where('status', $request->status);
        }

        if ($request->status === 'scheduled') {
            $query->upcoming();
        } elseif ($request->status === 'ended' || $request->status === 'cancelled') {
            $query->past();
        }

        $sessions = $query->latest('scheduled_at')->paginate(15);

        return response()->json($sessions);
    }

    /**
     * @OA\Post(
     *     path="/api/live-sessions",
     *     summary="Create a new live session",
     *     tags={"Live Sessions"},
     *     security={{"bearerAuth": {}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"environment_id", "title", "scheduled_at"},
     *             @OA\Property(property="environment_id", type="integer"),
     *             @OA\Property(property="title", type="string"),
     *             @OA\Property(property="description", type="string"),
     *             @OA\Property(property="course_id", type="integer"),
     *             @OA\Property(property="scheduled_at", type="string", format="date-time"),
     *             @OA\Property(property="max_participants", type="integer"),
     *             @OA\Property(property="settings", type="object")
     *         )
     *     ),
     *     @OA\Response(response=201, description="Session created successfully"),
     *     @OA\Response(response=403, description="Live sessions not enabled")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'environment_id' => 'required|exists:environments,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'course_id' => 'nullable|exists:courses,id',
            'scheduled_at' => 'required|date|after:now',
            'max_participants' => 'nullable|integer|min:1|max:1000',
            'settings' => 'nullable|array',
        ]);

        // Check if live sessions are enabled for this environment
        $liveSettings = EnvironmentLiveSettings::where('environment_id', $validated['environment_id'])->first();
        
        if (!$liveSettings || !$liveSettings->isEnabled()) {
            return response()->json([
                'message' => 'Live sessions are not enabled for this environment.',
            ], 403);
        }

        $session = LiveSession::create([
            ...$validated,
            'created_by' => $request->user()->id,
            'status' => 'scheduled',
        ]);

        // Add creator as host participant
        LiveSessionParticipant::create([
            'live_session_id' => $session->id,
            'user_id' => $request->user()->id,
            'role' => 'host',
        ]);

        return response()->json([
            'message' => 'Live session created successfully.',
            'data' => $session->load(['creator:id,name,email', 'course:id,title']),
        ], 201);
    }

    /**
     * @OA\Get(
     *     path="/api/live-sessions/{id}",
     *     summary="Get a specific live session",
     *     tags={"Live Sessions"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Session details")
     * )
     */
    public function show(LiveSession $liveSession): JsonResponse
    {
        return response()->json([
            'data' => $liveSession->load([
                'creator:id,name,email',
                'course:id,title',
                'participants.user:id,name,email',
            ]),
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/live-sessions/{id}",
     *     summary="Update a live session",
     *     tags={"Live Sessions"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Session updated")
     * )
     */
    public function update(Request $request, LiveSession $liveSession): JsonResponse
    {
        if ($liveSession->status !== 'scheduled') {
            return response()->json([
                'message' => 'Only scheduled sessions can be updated.',
            ], 422);
        }

        $validated = $request->validate([
            'title' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'course_id' => 'nullable|exists:courses,id',
            'scheduled_at' => 'sometimes|date|after:now',
            'max_participants' => 'nullable|integer|min:1|max:1000',
            'settings' => 'nullable|array',
        ]);

        $liveSession->update($validated);

        return response()->json([
            'message' => 'Live session updated successfully.',
            'data' => $liveSession->fresh(['creator:id,name,email', 'course:id,title']),
        ]);
    }

    /**
     * @OA\Delete(
     *     path="/api/live-sessions/{id}",
     *     summary="Cancel/delete a live session",
     *     tags={"Live Sessions"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Session cancelled")
     * )
     */
    public function destroy(LiveSession $liveSession): JsonResponse
    {
        if ($liveSession->status === 'live') {
            return response()->json([
                'message' => 'Cannot delete a live session that is currently in progress.',
            ], 422);
        }

        if ($liveSession->status === 'scheduled') {
            $liveSession->update(['status' => 'cancelled']);
            return response()->json(['message' => 'Live session cancelled.']);
        }

        $liveSession->delete();

        return response()->json(['message' => 'Live session deleted.']);
    }

    /**
     * @OA\Post(
     *     path="/api/live-sessions/{id}/start",
     *     summary="Start a live session",
     *     tags={"Live Sessions"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Session started")
     * )
     */
    public function start(LiveSession $liveSession): JsonResponse
    {
        // Check environment limits
        $liveSettings = EnvironmentLiveSettings::where('environment_id', $liveSession->environment_id)->first();
        
        if (!$liveSettings || !$liveSettings->canStartNewSession()) {
            return response()->json([
                'message' => 'Cannot start session: limit reached or live sessions disabled.',
            ], 403);
        }

        if (!$liveSession->start()) {
            return response()->json([
                'message' => 'Session cannot be started. It may already be live or ended.',
            ], 422);
        }

        return response()->json([
            'message' => 'Live session started.',
            'data' => $liveSession->fresh(),
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/live-sessions/{id}/end",
     *     summary="End a live session",
     *     tags={"Live Sessions"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Session ended")
     * )
     */
    public function end(LiveSession $liveSession): JsonResponse
    {
        if (!$liveSession->end()) {
            return response()->json([
                'message' => 'Session cannot be ended. It may not be live.',
            ], 422);
        }

        // Update usage tracking
        $liveSettings = EnvironmentLiveSettings::where('environment_id', $liveSession->environment_id)->first();
        if ($liveSettings) {
            $liveSettings->addUsage($liveSession->duration_minutes);
        }

        // Mark all participants as left
        $liveSession->participants()->whereNull('left_at')->update([
            'left_at' => now(),
        ]);

        return response()->json([
            'message' => 'Live session ended.',
            'data' => $liveSession->fresh(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/live-sessions/stats",
     *     summary="Get live session statistics for an environment",
     *     tags={"Live Sessions"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="environment_id", in="query", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(response=200, description="Session statistics")
     * )
     */
    public function stats(Request $request): JsonResponse
    {
        $request->validate([
            'environment_id' => 'required|exists:environments,id',
        ]);

        $environmentId = $request->environment_id;

        $stats = [
            'upcoming' => LiveSession::where('environment_id', $environmentId)
                ->where('status', 'scheduled')
                ->where('scheduled_at', '>=', now())
                ->count(),
            'live_now' => LiveSession::where('environment_id', $environmentId)
                ->where('status', 'live')
                ->count(),
            'completed' => LiveSession::where('environment_id', $environmentId)
                ->where('status', 'ended')
                ->count(),
            'total_participants' => LiveSessionParticipant::whereHas('session', function ($q) use ($environmentId) {
                $q->where('environment_id', $environmentId);
            })->distinct('user_id')->count('user_id'),
        ];

        return response()->json(['data' => $stats]);
    }
}
