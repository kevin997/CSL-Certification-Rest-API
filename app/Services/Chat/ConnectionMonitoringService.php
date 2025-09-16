<?php

namespace App\Services\Chat;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class ConnectionMonitoringService
{
    /**
     * Track a user connection to a course discussion.
     */
    public function trackConnection(string $userId, string $courseId): void
    {
        $key = "chat_connection:{$courseId}:{$userId}";
        Cache::put($key, now(), 300); // 5 minute expiry

        // Also maintain fallback list
        $this->addToParticipantsList($userId, $courseId);

        $this->updateCourseParticipantCount($courseId);

        Log::info('User connected to course discussion', [
            'user_id' => $userId,
            'course_id' => $courseId,
            'timestamp' => now(),
        ]);
    }

    /**
     * Remove a user connection from a course discussion.
     */
    public function removeConnection(string $userId, string $courseId): void
    {
        $key = "chat_connection:{$courseId}:{$userId}";
        Cache::forget($key);

        // Also remove from fallback list
        $this->removeFromParticipantsList($userId, $courseId);

        $this->updateCourseParticipantCount($courseId);

        Log::info('User disconnected from course discussion', [
            'user_id' => $userId,
            'course_id' => $courseId,
            'timestamp' => now(),
        ]);
    }

    /**
     * Get list of online participants in a course discussion.
     */
    public function getOnlineParticipants(string $courseId): array
    {
        $pattern = "chat_connection:{$courseId}:*";

        try {
            // Check if Redis is available
            if (!class_exists('Redis') && !extension_loaded('redis')) {
                // Fallback to scanning cache manually (less efficient but works)
                return $this->getOnlineParticipantsFallback($courseId);
            }

            // Use Redis directly for pattern matching
            $redis = Redis::connection();
            $keys = $redis->keys($pattern);

            $participants = [];
            foreach ($keys as $key) {
                if (Cache::has($key)) {
                    $userId = explode(':', $key)[2] ?? null;
                    if ($userId) {
                        $participants[] = $userId;
                    }
                }
            }

            return array_unique($participants);
        } catch (\Exception $e) {
            Log::warning('Redis not available for chat participants, using fallback', [
                'course_id' => $courseId,
                'error' => $e->getMessage(),
            ]);

            return $this->getOnlineParticipantsFallback($courseId);
        }
    }

    /**
     * Fallback method when Redis is not available.
     */
    private function getOnlineParticipantsFallback(string $courseId): array
    {
        // For development/testing without Redis, we'll maintain a simple cache list
        $cacheKey = "course_participants:{$courseId}";
        return Cache::get($cacheKey, []);
    }

    /**
     * Add user to fallback participants list.
     */
    private function addToParticipantsList(string $userId, string $courseId): void
    {
        $cacheKey = "course_participants:{$courseId}";
        $participants = Cache::get($cacheKey, []);

        if (!in_array($userId, $participants)) {
            $participants[] = $userId;
            Cache::put($cacheKey, $participants, 300);
        }
    }

    /**
     * Remove user from fallback participants list.
     */
    private function removeFromParticipantsList(string $userId, string $courseId): void
    {
        $cacheKey = "course_participants:{$courseId}";
        $participants = Cache::get($cacheKey, []);

        $participants = array_diff($participants, [$userId]);
        Cache::put($cacheKey, array_values($participants), 300);
    }

    /**
     * Get the count of online participants in a course.
     */
    public function getOnlineParticipantCount(string $courseId): int
    {
        $cacheKey = "course_participant_count:{$courseId}";
        return Cache::get($cacheKey, 0);
    }

    /**
     * Update the participant count for a course.
     */
    private function updateCourseParticipantCount(string $courseId): void
    {
        $count = count($this->getOnlineParticipants($courseId));
        $cacheKey = "course_participant_count:{$courseId}";
        Cache::put($cacheKey, $count, 300); // 5 minute expiry
    }

    /**
     * Check if a user is currently connected to a course discussion.
     */
    public function isUserConnected(string $userId, string $courseId): bool
    {
        $key = "chat_connection:{$courseId}:{$userId}";
        return Cache::has($key);
    }

    /**
     * Cleanup stale connections (called by scheduled job).
     */
    public function cleanupStaleConnections(): int
    {
        $pattern = "chat_connection:*";
        $cleaned = 0;

        try {
            $redis = Redis::connection();
            $keys = $redis->keys($pattern);

            foreach ($keys as $key) {
                if (!Cache::has($key)) {
                    // Extract course ID and update participant count
                    $parts = explode(':', $key);
                    if (count($parts) >= 3) {
                        $courseId = $parts[1];
                        $this->updateCourseParticipantCount($courseId);
                    }
                    $cleaned++;
                }
            }

            Log::info('Cleaned up stale chat connections', [
                'connections_cleaned' => $cleaned,
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to cleanup stale connections', [
                'error' => $e->getMessage(),
            ]);
        }

        return $cleaned;
    }

    /**
     * Get connection statistics for monitoring.
     */
    public function getConnectionStats(): array
    {
        try {
            // Check if Redis is available
            if (!class_exists('Redis') && !extension_loaded('redis')) {
                return $this->getConnectionStatsFallback();
            }

            $pattern = "chat_connection:*";
            $redis = Redis::connection();
            $keys = $redis->keys($pattern);

            $totalConnections = 0;
            $courseStats = [];

            foreach ($keys as $key) {
                if (Cache::has($key)) {
                    $totalConnections++;
                    $parts = explode(':', $key);
                    if (count($parts) >= 3) {
                        $courseId = $parts[1];
                        $courseStats[$courseId] = ($courseStats[$courseId] ?? 0) + 1;
                    }
                }
            }

            return [
                'total_connections' => $totalConnections,
                'active_courses' => count($courseStats),
                'course_breakdown' => $courseStats,
                'timestamp' => now(),
            ];

        } catch (\Exception $e) {
            Log::warning('Redis not available for connection stats, using fallback', [
                'error' => $e->getMessage(),
            ]);

            return $this->getConnectionStatsFallback();
        }
    }

    /**
     * Fallback method for connection stats when Redis is not available.
     */
    private function getConnectionStatsFallback(): array
    {
        // Get stats from our fallback cache approach
        $courseStats = [];
        $totalConnections = 0;

        // This is a simplified version - in production with Redis this would be more accurate
        $pattern = 'course_participants:*';

        // For now, return basic stats
        return [
            'total_connections' => $totalConnections,
            'active_courses' => count($courseStats),
            'course_breakdown' => $courseStats,
            'timestamp' => now(),
            'note' => 'Using fallback method (Redis not available)',
        ];
    }
}