<?php

namespace App\Console\Commands;

use App\Events\Chat\MessageSent;
use App\Events\Chat\UserJoinedDiscussion;
use App\Models\User;
use App\Models\DiscussionMessage;
use App\Models\Discussion;
use App\Models\Course;
use App\Services\Chat\ConnectionMonitoringService;
use Illuminate\Console\Command;

class TestWebSocketCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:test-websocket {--course-id=1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test WebSocket functionality for chat system';

    /**
     * Execute the console command.
     */
    public function handle(ConnectionMonitoringService $connectionService)
    {
        $courseId = $this->option('course-id');

        $this->info("Testing WebSocket functionality for course ID: {$courseId}");

        // Check if Reverb is configured
        $broadcastDriver = config('broadcasting.default');
        $this->info("Broadcast driver: {$broadcastDriver}");

        if ($broadcastDriver !== 'reverb') {
            $this->error('Reverb is not configured as the default broadcaster');
            return 1;
        }

        // Test connection monitoring
        $this->info('Testing connection monitoring...');

        $testUserId = '1';
        $connectionService->trackConnection($testUserId, $courseId);

        $isConnected = $connectionService->isUserConnected($testUserId, $courseId);
        $this->info("User {$testUserId} connected: " . ($isConnected ? 'YES' : 'NO'));

        $onlineCount = $connectionService->getOnlineParticipantCount($courseId);
        $this->info("Online participants in course {$courseId}: {$onlineCount}");

        // Test broadcasting (dry run)
        $this->info('Testing event broadcasting...');

        try {
            // Find a test user
            $user = User::first();

            if (!$user) {
                $this->error('No users found for testing');
                return 1;
            }

            // Create test UserJoinedDiscussion event
            $joinEvent = new UserJoinedDiscussion($user, $courseId);
            $this->info("Created UserJoinedDiscussion event for user: {$user->name}");

            // Broadcast the event
            broadcast($joinEvent);
            $this->info("Successfully broadcasted UserJoinedDiscussion event");

        } catch (\Exception $e) {
            $this->error("Error testing broadcast: " . $e->getMessage());
            return 1;
        }

        // Clean up test connection
        $connectionService->removeConnection($testUserId, $courseId);
        $this->info("Cleaned up test connection");

        // Show connection stats
        $stats = $connectionService->getConnectionStats();
        $this->info("Connection stats:");
        $this->line("Total connections: " . $stats['total_connections']);
        $this->line("Active courses: " . $stats['active_courses']);

        $this->info('WebSocket test completed successfully!');
        return 0;
    }
}
