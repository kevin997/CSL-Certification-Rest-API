<?php

namespace App\Http\Controllers\Api\Chat;

use App\Http\Controllers\Controller;
use App\Services\Chat\ChatService;
use App\Http\Resources\Chat\DiscussionResource;
use App\Http\Resources\Chat\MessageResource;
use App\Http\Requests\Chat\CreateDiscussionRequest;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class DiscussionController extends Controller
{
    public function __construct(private ChatService $chatService)
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * Create a new discussion.
     */
    public function store(CreateDiscussionRequest $request): JsonResponse
    {
        try {
            $discussion = $this->chatService->createDiscussion(
                $request->course_id,
                $request->user()->id,
                $request->type ?? 'group'
            );

            return response()->json([
                'success' => true,
                'message' => 'Discussion created successfully',
                'data' => new DiscussionResource($discussion)
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get messages for a discussion.
     */
    public function show(string $discussionId, Request $request): JsonResponse
    {
        try {
            $messages = $this->chatService->getMessages(
                $discussionId,
                $request->user()->id,
                $request->get('page', 1),
                $request->get('per_page', 50)
            );

            return response()->json([
                'success' => true,
                'data' => MessageResource::collection($messages),
                'meta' => [
                    'current_page' => $messages->currentPage(),
                    'last_page' => $messages->lastPage(),
                    'per_page' => $messages->perPage(),
                    'total' => $messages->total()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Join a discussion.
     */
    public function join(string $discussionId, Request $request): JsonResponse
    {
        try {
            $participant = $this->chatService->joinDiscussion(
                $discussionId,
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Successfully joined discussion',
                'data' => [
                    'participant' => $participant
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Leave a discussion.
     */
    public function leave(string $discussionId, Request $request): JsonResponse
    {
        try {
            $this->chatService->leaveDiscussion(
                $discussionId,
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'message' => 'Successfully left discussion'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get discussion participants.
     */
    public function participants(string $discussionId, Request $request): JsonResponse
    {
        try {
            $participants = $this->chatService->getDiscussionParticipants(
                $discussionId,
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'participants' => $participants
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get discussions for a course.
     */
    public function courseDiscussions(string $courseId, Request $request): JsonResponse
    {
        try {
            $discussions = $this->chatService->getCourseDiscussions(
                $courseId,
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'data' => [
                    'discussions' => $discussions
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Get or create course discussion.
     */
    public function getOrCreate(string $courseId, Request $request): JsonResponse
    {
        try {
            $discussion = $this->chatService->getOrCreateCourseDiscussion(
                $courseId,
                $request->user()->id
            );

            return response()->json([
                'success' => true,
                'data' => new DiscussionResource($discussion)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}