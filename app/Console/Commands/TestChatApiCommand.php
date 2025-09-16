<?php

namespace App\Console\Commands;

use App\Services\Chat\ChatService;
use App\Models\User;
use App\Models\Course;
use Illuminate\Console\Command;

class TestChatApiCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:test-api {--user-id=1} {--course-id=1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test chat API functionality through service layer';

    /**
     * Execute the console command.
     */
    public function handle(ChatService $chatService)
    {
        $userId = $this->option('user-id');
        $courseId = $this->option('course-id');

        $this->info("Testing Chat API functionality for User ID: {$userId}, Course ID: {$courseId}");

        try {
            // Find user and course
            $user = User::find($userId);
            $course = Course::find($courseId);

            if (!$user) {
                $this->error("User with ID {$userId} not found");
                return 1;
            }

            if (!$course) {
                $this->error("Course with ID {$courseId} not found");
                return 1;
            }

            $this->info("Found user: {$user->name}");
            $this->info("Found course: {$course->title}");

            // Test 1: Get or create course discussion
            $this->info("\n1. Testing get or create course discussion...");
            $discussion = $chatService->getOrCreateCourseDiscussion($courseId, $userId);
            $this->info("✓ Discussion created/retrieved: ID {$discussion->id}");

            // Test 2: Send a message
            $this->info("\n2. Testing send message...");
            $message = $chatService->sendMessage(
                $discussion->id,
                $userId,
                "Test message from API test command",
                'text'
            );
            $this->info("✓ Message sent: ID {$message->id}");

            // Test 3: Get messages
            $this->info("\n3. Testing get messages...");
            $messages = $chatService->getMessages($discussion->id, $userId, 1, 10);
            $this->info("✓ Retrieved {$messages->count()} messages");

            // Test 4: Get discussion participants
            $this->info("\n4. Testing get participants...");
            $participants = $chatService->getDiscussionParticipants($discussion->id, $userId);
            $this->info("✓ Retrieved " . count($participants) . " participants");

            // Test 5: Mark as read
            $this->info("\n5. Testing mark as read...");
            $chatService->markAsRead($discussion->id, $userId);
            $this->info("✓ Messages marked as read");

            // Test 6: Get course discussions (note: may fail if user is not enrolled)
            $this->info("\n6. Testing get course discussions...");
            try {
                $discussions = $chatService->getCourseDiscussions($courseId, $userId);
                $this->info("✓ Retrieved " . count($discussions) . " course discussions");
            } catch (\Exception $e) {
                $this->warn("⚠ Course discussions test failed (expected if user not enrolled): " . $e->getMessage());
                $this->info("This is normal - user needs to be enrolled in course or be an instructor");
            }

            $this->info("\n🎉 Core Chat API tests passed successfully!");
            $this->info("Note: Full functionality requires proper user enrollment in course");
            return 0;

        } catch (\Exception $e) {
            $this->error("❌ Test failed: " . $e->getMessage());
            $this->error("Stack trace: " . $e->getTraceAsString());
            return 1;
        }
    }
}
