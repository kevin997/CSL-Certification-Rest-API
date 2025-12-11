<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LiveSession;
use App\Models\LiveSessionParticipant;
use App\Models\EnvironmentLiveSettings;
use App\Services\LiveKitService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="LiveKit Webhooks",
 *     description="Webhook endpoints for LiveKit events"
 * )
 */
class LiveKitWebhookController extends Controller
{
    public function __construct(
        private LiveKitService $liveKitService
    ) {}

    /**
     * @OA\Post(
     *     path="/api/webhooks/livekit",
     *     summary="Handle LiveKit webhook events",
     *     tags={"LiveKit Webhooks"},
     *     @OA\Response(response=200, description="Webhook processed")
     * )
     */
    public function handle(Request $request): JsonResponse
    {
        $signature = $request->header('Authorization');
        $body = $request->getContent();

        // Verify webhook signature
        if (!$this->liveKitService->verifyWebhookSignature($body, $signature ?? '')) {
            Log::warning('LiveKit webhook signature verification failed');
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        $payload = json_decode($body, true);
        $event = $payload['event'] ?? null;

        Log::info('LiveKit webhook received', [
            'event' => $event,
            'room' => $payload['room']['name'] ?? null,
        ]);

        match ($event) {
            'room_started' => $this->handleRoomStarted($payload),
            'room_finished' => $this->handleRoomFinished($payload),
            'participant_joined' => $this->handleParticipantJoined($payload),
            'participant_left' => $this->handleParticipantLeft($payload),
            default => null,
        };

        return response()->json(['message' => 'Webhook processed']);
    }

    private function handleRoomStarted(array $payload): void
    {
        $roomName = $payload['room']['name'] ?? null;
        if (!$roomName) return;

        $session = LiveSession::where('room_name', $roomName)->first();
        if ($session && $session->status === 'scheduled') {
            $session->start();
        }
    }

    private function handleRoomFinished(array $payload): void
    {
        $roomName = $payload['room']['name'] ?? null;
        if (!$roomName) return;

        $session = LiveSession::where('room_name', $roomName)->first();
        if ($session && $session->status === 'live') {
            $session->end();

            // Update usage tracking
            $liveSettings = EnvironmentLiveSettings::where('environment_id', $session->environment_id)->first();
            if ($liveSettings) {
                $liveSettings->addUsage($session->duration_minutes);
            }
        }
    }

    private function handleParticipantJoined(array $payload): void
    {
        $roomName = $payload['room']['name'] ?? null;
        $participantIdentity = $payload['participant']['identity'] ?? null;

        if (!$roomName || !$participantIdentity) return;

        $session = LiveSession::where('room_name', $roomName)->first();
        if (!$session) return;

        // Extract user ID from identity (format: user_{id})
        if (preg_match('/user_(\d+)/', $participantIdentity, $matches)) {
            $userId = (int) $matches[1];
            
            $participant = LiveSessionParticipant::where('live_session_id', $session->id)
                ->where('user_id', $userId)
                ->first();

            if ($participant) {
                $participant->recordJoin();
            }
        }
    }

    private function handleParticipantLeft(array $payload): void
    {
        $roomName = $payload['room']['name'] ?? null;
        $participantIdentity = $payload['participant']['identity'] ?? null;

        if (!$roomName || !$participantIdentity) return;

        $session = LiveSession::where('room_name', $roomName)->first();
        if (!$session) return;

        if (preg_match('/user_(\d+)/', $participantIdentity, $matches)) {
            $userId = (int) $matches[1];
            
            $participant = LiveSessionParticipant::where('live_session_id', $session->id)
                ->where('user_id', $userId)
                ->first();

            if ($participant) {
                $participant->recordLeave();
            }
        }
    }
}
