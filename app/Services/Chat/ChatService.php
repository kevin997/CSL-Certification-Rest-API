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
use Illuminate\Support\Facades\Log;

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
            broadcast(new MessageSent($message->load('user'), $discussion->course_id))->toOthers();

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
        try {
            // Verify user can access course
            $this->verifyCanJoin($courseId, $userId);

            $discussions = Discussion::where('course_id', $courseId)
                ->with(['participants.user'])
                ->get();

            $result = $discussions->map(function ($discussion) use ($userId, $courseId) {
                if (!$discussion || !$discussion->id) {
                    Log::warning('Invalid discussion found', [
                        'discussion' => $discussion,
                        'course_id' => $courseId,
                        'user_id' => $userId
                    ]);
                    return null;
                }

                // Check if user is participant
                $isParticipant = $discussion->participants ? $discussion->participants->firstWhere('user_id', $userId) : null;

                return [
                    'id' => $discussion->id,
                    'course_id' => $discussion->course_id,
                    'type' => $discussion->type ?? 'group',
                    'created_at' => $discussion->created_at,
                    'updated_at' => $discussion->updated_at,
                    'is_participant' => (bool) $isParticipant,
                    'participant_count' => $discussion->participants ? $discussion->participants->count() : 0,
                    'online_count' => $discussion->participants ? $discussion->participants->where('is_online', true)->count() : 0,
                ];
            })->filter()->values()->toArray();

            Log::info('getCourseDiscussions result', [
                'course_id' => $courseId,
                'user_id' => $userId,
                'discussions_count' => count($result)
            ]);

            return $result;
        } catch (\Exception $e) {
            Log::error('Error in getCourseDiscussions', [
                'course_id' => $courseId,
                'user_id' => $userId,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
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

    /**
     * Get all courses where user is instructor/owner.
     */
    public function getInstructorCourses(string $userId): array
    {
        $user = User::findOrFail($userId);

        // Verify user is actually a teacher
        if (!$user->isTeacher() && !$user->isAdmin()) {
            throw new Exception('User is not authorized to access instructor features');
        }

        // Get courses where user is the creator (instructor)
        // Note: Course model automatically applies environment scoping via BelongsToEnvironment trait
        $courses = Course::where('created_by', $userId)
            ->with(['enrollments'])
            ->orderBy('created_at', 'desc')
            ->get();

        $result = $courses->map(function ($course) use ($userId) {
            // Ensure we have a valid course object
            if (!$course || !$course->id) {
                Log::warning('Invalid course found in getInstructorCourses', [
                    'course' => $course,
                    'user_id' => $userId
                ]);
                return null;
            }

            return [
                'id' => $course->id,
                'title' => $course->title ?? '',
                'course_code' => $course->course_code ?? '',
                'enrollment_count' => $course->enrollments ? $course->enrollments->count() : 0,
                'status' => $course->status ?? 'draft',
                'difficulty_level' => $course->difficulty_level ?? null,
                'is_self_paced' => $course->is_self_paced ?? false,
                'start_date' => $course->start_date,
                'end_date' => $course->end_date,
                'created_at' => $course->created_at,
                'updated_at' => $course->updated_at,
            ];
        })->filter()->values()->toArray();

        Log::info('getInstructorCourses result', [
            'user_id' => $userId,
            'courses_count' => count($result),
            'courses' => $result
        ]);

        return $result;
    }

    /**
     * Get instructor discussion summaries formatted for frontend.
     */
    public function getInstructorDiscussionSummaries(string $userId): array
    {
        $user = User::findOrFail($userId);

        // Verify user is actually a teacher
        if (!$user->isTeacher() && !$user->isAdmin()) {
            throw new Exception('User is not authorized to access instructor features');
        }

        // Get all courses where user is instructor/owner
        $instructorCourses = $this->getInstructorCourses($userId);

        $summaries = [];
        foreach ($instructorCourses as $course) {
            if (!is_array($course) || !isset($course['id'])) {
                continue;
            }

            try {
                // Get discussions for this course
                $discussions = Discussion::where('course_id', $course['id'])
                    ->with(['course', 'participants.user'])
                    ->get();

                if ($discussions->isEmpty()) {
                    // Create a summary even if no discussions exist
                    $summaries[] = [
                        'courseId' => $course['id'],
                        'courseTitle' => $course['title'] ?? 'Untitled Course',
                        'totalMessages' => 0,
                        'unreadMessages' => 0,
                        'activeParticipants' => 0,
                        'lastActivity' => $course['created_at'],
                        'lastMessage' => null
                    ];
                    continue;
                }

                // Aggregate data across all discussions for this course
                $totalMessages = 0;
                $totalUnreadMessages = 0;
                $allParticipants = collect();
                $latestMessage = null;
                $latestActivityTime = null;

                foreach ($discussions as $discussion) {
                    // Count messages for this discussion
                    $messageCount = DiscussionMessage::where('discussion_id', $discussion->id)->count();
                    $totalMessages += $messageCount;

                    // Count unread messages (simplified - would need proper read tracking)
                    $totalUnreadMessages += 0; // Placeholder for now

                    // Collect participants (deduplicate later)
                    if ($discussion->participants) {
                        $allParticipants = $allParticipants->merge($discussion->participants);
                    }

                    // Get last message from this discussion
                    $lastMessage = DiscussionMessage::where('discussion_id', $discussion->id)
                        ->with('user')
                        ->latest()
                        ->first();

                    if ($lastMessage && (!$latestMessage || $lastMessage->created_at > $latestMessage->created_at)) {
                        $latestMessage = $lastMessage;
                    }

                    if (!$latestActivityTime || $discussion->updated_at > $latestActivityTime) {
                        $latestActivityTime = $discussion->updated_at;
                    }
                }

                // Deduplicate participants by user_id (online only)
                $uniqueParticipants = $allParticipants
                    ->where('is_online', true)
                    ->unique('user_id');

                $summaries[] = [
                    'courseId' => $course['id'],
                    'courseTitle' => $course['title'] ?? 'Untitled Course',
                    'totalMessages' => $totalMessages,
                    'unreadMessages' => $totalUnreadMessages,
                    'activeParticipants' => $uniqueParticipants->count(),
                    'lastActivity' => $latestMessage ? $latestMessage->created_at->toISOString() : $latestActivityTime->toISOString(),
                    'lastMessage' => $latestMessage ? [
                        'content' => $latestMessage->content,
                        'user' => $latestMessage->user->name ?? 'Unknown User',
                        'timestamp' => $latestMessage->created_at->toISOString()
                    ] : null
                ];
            } catch (\Exception $e) {
                Log::warning('Failed to get discussion summary for course', [
                    'course_id' => $course['id'],
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);

                // Add a basic summary for courses with errors
                $summaries[] = [
                    'courseId' => $course['id'],
                    'courseTitle' => $course['title'] ?? 'Untitled Course',
                    'totalMessages' => 0,
                    'unreadMessages' => 0,
                    'activeParticipants' => 0,
                    'lastActivity' => $course['created_at'],
                    'lastMessage' => null
                ];
            }
        }

        return $summaries;
    }

    /**
     * Get discussion analytics for instructor.
     */
    public function getInstructorDiscussionAnalytics(string $userId): array
    {
        $instructorCourses = $this->getInstructorCourses($userId);
        $courseIds = array_column($instructorCourses, 'id');

        if (empty($courseIds)) {
            return [
                'total_discussions' => 0,
                'total_messages' => 0,
                'active_participants' => 0,
                'recent_activity' => [],
                'top_courses' => []
            ];
        }

        // Get discussions for instructor's courses
        $discussions = Discussion::whereIn('course_id', $courseIds)
            ->with(['messages', 'participants', 'course'])
            ->get();

        // Calculate analytics
        $totalMessages = $discussions->sum(function ($discussion) {
            return $discussion->messages->count();
        });

        $activeParticipants = DiscussionParticipant::whereIn('discussion_id', $discussions->pluck('id'))
            ->where('last_read_at', '>=', now()->subDays(7))
            ->distinct('user_id')
            ->count();

        // Get recent activity (last 7 days)
        $recentActivity = DiscussionMessage::whereIn('discussion_id', $discussions->pluck('id'))
            ->where('created_at', '>=', now()->subDays(7))
            ->with(['discussion.course', 'user'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($message) {
                return [
                    'message' => $message->message_content,
                    'user' => $message->user->name ?? 'Unknown',
                    'course' => $message->discussion->course->title ?? 'Unknown Course',
                    'created_at' => $message->created_at
                ];
            });

        // Get top courses by activity
        $topCourses = $discussions->groupBy('course_id')
            ->map(function ($courseDiscussions, $courseId) {
                $course = $courseDiscussions->first()->course;
                $totalMessages = $courseDiscussions->sum(function ($discussion) {
                    return $discussion->messages->count();
                });

                return [
                    'course_id' => $courseId,
                    'course_title' => $course->title ?? 'Unknown Course',
                    'discussion_count' => $courseDiscussions->count(),
                    'message_count' => $totalMessages
                ];
            })
            ->sortByDesc('message_count')
            ->take(5)
            ->values();

        return [
            'total_discussions' => $discussions->count(),
            'total_messages' => $totalMessages,
            'active_participants' => $activeParticipants,
            'recent_activity' => $recentActivity->toArray(),
            'top_courses' => $topCourses->toArray()
        ];
    }
}
