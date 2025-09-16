<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\Course;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Broadcast;

class TestChannelAuthCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'chat:test-auth {--user-id=1} {--course-id=1}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test channel authorization for chat system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $userId = $this->option('user-id');
        $courseId = $this->option('course-id');

        $this->info("Testing channel authorization for User ID: {$userId}, Course ID: {$courseId}");

        // Find user
        $user = User::find($userId);
        if (!$user) {
            $this->error("User with ID {$userId} not found");
            return 1;
        }

        $this->info("Found user: {$user->name} (Role: " . ($user->role ? $user->role->value : 'N/A') . ")");

        // Find course
        $course = Course::find($courseId);
        if (!$course) {
            $this->error("Course with ID {$courseId} not found");
            return 1;
        }

        $this->info("Found course: {$course->title}");

        // Test course enrollment
        $isEnrolled = $course->enrolledUsers()->where('users.id', $user->id)->exists();
        $this->info("User enrolled in course: " . ($isEnrolled ? 'YES' : 'NO'));

        // Test instructor status
        $isInstructor = $user->isTeacher();
        $this->info("User is instructor: " . ($isInstructor ? 'YES' : 'NO'));

        // Simulate channel authorization
        $channelAuth = function($user, $courseId) {
            $course = Course::find($courseId);
            if (!$course) {
                return false;
            }

            $isEnrolled = $course->enrolledUsers()->where('users.id', $user->id)->exists();
            $isInstructor = $user->isTeacher();

            if ($isEnrolled || $isInstructor) {
                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'avatar' => $user->avatar_url ?? null,
                    'role' => $isInstructor ? 'instructor' : 'student'
                ];
            }

            return false;
        };

        // Test authorization
        $authResult = $channelAuth($user, $courseId);

        if ($authResult === false) {
            $this->error("Channel authorization FAILED - User cannot access course discussion");
            return 1;
        }

        $this->info("Channel authorization SUCCESS");
        $this->info("Authorization result:");
        $this->line("  ID: " . $authResult['id']);
        $this->line("  Name: " . $authResult['name']);
        $this->line("  Role: " . $authResult['role']);
        $this->line("  Avatar: " . ($authResult['avatar'] ?? 'None'));

        $this->info('Channel authorization test completed successfully!');
        return 0;
    }
}
