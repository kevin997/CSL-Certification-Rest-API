<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LiveSession;
use App\Models\LiveSessionParticipant;
use App\Services\LiveKitService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Live Session Tokens",
 *     description="API endpoints for generating LiveKit tokens"
 * )
 */
class LiveSessionTokenController extends Controller
{
    public function __construct(
        private LiveKitService $liveKitService
    ) {}

    /**
     * @OA\Post(
     *     path="/api/live-sessions/{id}/token",
     *     summary="Generate a token to join a live session",
     *     tags={"Live Session Tokens"},
     *     security={{"bearerAuth": {}}},
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Token generated",
     *         @OA\JsonContent(
     *             @OA\Property(property="token", type="string"),
     *             @OA\Property(property="server_url", type="string"),
     *             @OA\Property(property="room_name", type="string")
     *         )
     *     ),
     *     @OA\Response(response=403, description="Cannot join session")
     * )
     */
    public function generateToken(Request $request, LiveSession $liveSession): JsonResponse
    {
        $user = $request->user();

        // Check if session is live or scheduled to start soon
        if (!in_array($liveSession->status, ['live', 'scheduled'])) {
            return response()->json([
                'message' => 'This session is not available to join.',
            ], 403);
        }

        // For scheduled sessions, only allow joining 15 minutes before start
        if ($liveSession->status === 'scheduled') {
            $fifteenMinutesBefore = $liveSession->scheduled_at->subMinutes(15);
            if (now()->lt($fifteenMinutesBefore)) {
                return response()->json([
                    'message' => 'Session has not started yet. You can join 15 minutes before the scheduled time.',
                    'scheduled_at' => $liveSession->scheduled_at->toIso8601String(),
                ], 403);
            }
        }

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

        $token = $this->liveKitService->generateToken(
            $liveSession->room_name,
            $participantIdentity,
            $participantName,
            $canPublish
        );

        return response()->json([
            'token' => $token,
            'server_url' => $this->liveKitService->getServerUrl(),
            'room_name' => $liveSession->room_name,
            'role' => $participant->role,
            'can_publish' => $canPublish,
        ]);
    }
}
