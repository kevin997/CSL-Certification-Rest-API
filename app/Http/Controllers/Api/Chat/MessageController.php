<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Services\Chat\ChatService;
use App\Http\Resources\Chat\MessageResource;
use App\Http\Requests\Chat\SendMessageRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class MessageController extends Controller implements HasMiddleware
{
    public function __construct(
        private ChatService $chatService
    ) {}

    /**
     * Get the middleware that should be assigned to the controller.
     */
    public static function middleware(): array
    {
        return [
            'auth:sanctum',
            new Middleware('chat.rate.messages', only: ['store']),
        ];
    }

    /**
     * Send a message to a discussion.
     */
    public function store(SendMessageRequest $request): JsonResponse
    {
        try {
            $message = $this->chatService->sendMessage(
                $request->discussion_id,
                $request->user()->id,
                $request->content,
                $request->type ?? 'text',
                $request->parent_message_id
            );

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
                'data' => new MessageResource($message)
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Mark messages as read.
     */
    public function markAsRead(string $discussionId, Request $request): JsonResponse
    {
        try {
            $this->chatService->markAsRead(
                $discussionId,
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Messages marked as read'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

}