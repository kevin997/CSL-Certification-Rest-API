<?php

namespace App\Services\Chat;

use App\Models\Discussion;
use App\Models\DiscussionMessage;
use App\Models\DiscussionParticipant;
use App\Models\Course;
use App\Models\User;
use App\Events\Chat\MessageSent;
use App\Events\Chat\UserJoinedDiscussion;
use App\Events\Chat\UserLeftDiscussion;
use Illuminate\Support\Facades\DB;
use Illuminate\Pagination\LengthAwarePaginator;
use Exception;

class ChatService
{
    /**
     * Create a new discussion for a course.
     */
    public function createDiscussion(string $courseId, string $userId, string $type = 'group'): Discussion
    {
        return DB::transaction(function () use ($courseId, $userId, $type) {
            $course = Course::findOrFail($courseId);

            $discussion = Discussion::create([
                'course_id' => $courseId,
                'environment_id' => $course->environment_id,
                'type' => $type,
            ]);

            $this->addParticipant($discussion->id, $userId);

            return $discussion->load(['course', 'participants.user']);
        });
    }

    /**
     * Send a message to a discussion.
     */
    public function sendMessage(string $discussionId, string $userId, string $content, string $type = 'text', ?string $parentMessageId = null): DiscussionMessage
    {
        return DB::transaction(function () use ($discussionId, $userId, $content, $type, $parentMessageId) {
            $discussion = Discussion::findOrFail($discussionId);

            // Verify user is participant
            $this->verifyParticipant($discussionId, $userId);

            $message = DiscussionMessage::create([
                'discussion_id' => $discussionId,
                'user_id' => $userId,
                'message_content' => $content,
                'message_type' => $type,
                'parent_message_id' => $parentMessageId,
            ]);

            // Update participant last read time
            DiscussionParticipant::where('discussion_id', $discussionId)
                ->where('user_id', $userId)
                ->update(['last_read_at' => now()]);

            // Broadcast message
            broadcast(new MessageSent($message->load('user'), $discussion->course_id));

            return $message->load('user');
        });
    }

    /**
     * Get messages for a discussion with pagination.
     */
    public function getMessages(string $discussionId, string $userId, int $page = 1, int $perPage = 50): LengthAwarePaginator
    {
        // Verify user is participant
        $this->verifyParticipant($discussionId, $userId);

        return DiscussionMessage::where('discussion_id', $discussionId)
            ->with(['user', 'parent'])
            ->orderBy('created_at', 'desc')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Join a discussion.
     */
    public function joinDiscussion(string $discussionId, string $userId): DiscussionParticipant
    {
        $discussion = Discussion::findOrFail($discussionId);

        // Verify user can join (enrolled in course or instructor)
        $this->verifyCanJoin($discussion->course_id, $userId);

        $participant = DiscussionParticipant::firstOrCreate([
            'discussion_id' => $discussionId,
            'user_id' => $userId,
        ], [
            'last_read_at' => now(),
            'is_online' => true,
        ]);

        // Update online status
        $participant->update(['is_online' => true]);

        // Broadcast user joined
        $user = User::find($userId);
        broadcast(new UserJoinedDiscussion($user, $discussion->course_id));

        return $participant->load('user');
    }

    /**
     * Leave a discussion.
     */
    public function leaveDiscussion(string $discussionId, string $userId): void
    {
        $discussion = Discussion::findOrFail($discussionId);

        DiscussionParticipant::where('discussion_id', $discussionId)
            ->where('user_id', $userId)
            ->update(['is_online' => false]);

        // Broadcast user left
        $user = User::find($userId);
        broadcast(new UserLeftDiscussion($user, $discussion->course_id));
    }

    /**
     * Mark messages as read.
     */
    public function markAsRead(string $discussionId, string $userId): void
    {
        DiscussionParticipant::where('discussion_id', $discussionId)
            ->where('user_id', $userId)
            ->update(['last_read_at' => now()]);
    }

    /**
     * Get discussion participants.
     */
    public function getDiscussionParticipants(string $discussionId, string $userId): array
    {
        $this->verifyParticipant($discussionId, $userId);

        return DiscussionParticipant::where('discussion_id', $discussionId)
            ->with('user')
            ->get()
            ->toArray();
    }

    /**
     * Get discussions for a course.
     */
    public function getCourseDiscussions(string $courseId, string $userId): array
    {
        // Verify user can access course
        $this->verifyCanJoin($courseId, $userId);

        $discussions = Discussion::where('course_id', $courseId)
            ->with(['participants.user'])
            ->get();

        return $discussions->map(function ($discussion) use ($userId) {
            // Check if user is participant
            $isParticipant = $discussion->participants->firstWhere('user_id', $userId);

            return [
                'id' => $discussion->id,
                'course_id' => $discussion->course_id,
                'type' => $discussion->type,
                'created_at' => $discussion->created_at,
                'updated_at' => $discussion->updated_at,
                'is_participant' => (bool) $isParticipant,
                'participant_count' => $discussion->participants->count(),
                'online_count' => $discussion->participants->where('is_online', true)->count(),
            ];
        })->toArray();
    }

    /**
     * Get or create course discussion.
     */
    public function getOrCreateCourseDiscussion(string $courseId, string $userId): Discussion
    {
        // First try to find existing group discussion for the course
        $discussion = Discussion::where('course_id', $courseId)
            ->where('type', 'group')
            ->first();

        if (!$discussion) {
            // Create new group discussion
            $discussion = $this->createDiscussion($courseId, $userId, 'group');
        } else {
            // Join existing discussion if not already a participant
            $isParticipant = DiscussionParticipant::where('discussion_id', $discussion->id)
                ->where('user_id', $userId)
                ->exists();

            if (!$isParticipant) {
                $this->joinDiscussion($discussion->id, $userId);
            }
        }

        return $discussion->load(['course', 'participants.user']);
    }

    /**
     * Add a participant to a discussion.
     */
    private function addParticipant(string $discussionId, string $userId): DiscussionParticipant
    {
        return DiscussionParticipant::create([
            'discussion_id' => $discussionId,
            'user_id' => $userId,
            'last_read_at' => now(),
            'is_online' => true,
        ]);
    }

    /**
     * Verify user is a participant in the discussion.
     */
    private function verifyParticipant(string $discussionId, string $userId): void
    {
        $participant = DiscussionParticipant::where('discussion_id', $discussionId)
            ->where('user_id', $userId)
            ->first();

        if (!$participant) {
            throw new Exception('User is not a participant in this discussion');
        }
    }

    /**
     * Verify user can join the discussion.
     */
    private function verifyCanJoin(string $courseId, string $userId): void
    {
        $course = Course::findOrFail($courseId);
        $user = User::findOrFail($userId);

        // Check enrollment
        $isEnrolled = $course->enrolledUsers()->where('users.id', $userId)->exists();

        // Check if user is instructor (teacher role)
        $isInstructor = $user->isTeacher();

        if (!$isEnrolled && !$isInstructor) {
            throw new Exception('User cannot join this course discussion');
        }
    }
}