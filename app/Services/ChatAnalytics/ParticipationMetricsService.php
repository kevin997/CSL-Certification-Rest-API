<?php

namespace App\Services\ChatAnalytics;

use App\Models\Analytics\ChatParticipation;
use App\Models\Analytics\CourseEngagement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class ParticipationMetricsService
{
    public function generateCourseEngagementReport(string $courseId, Carbon $startDate, Carbon $endDate): array
    {
        Log::info("Generating chat engagement report", [
            'course_id' => $courseId,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString()
        ]);

        $cacheKey = "engagement_report_{$courseId}_{$startDate->format('Y-m-d')}_{$endDate->format('Y-m-d')}";

        return Cache::remember($cacheKey, config('chat.analytics.cache_duration', 3600), function () use ($courseId, $startDate, $endDate) {
            $metrics = [
                'overview' => $this->getOverviewMetrics($courseId, $startDate, $endDate),
                'participation' => $this->getParticipationMetrics($courseId, $startDate, $endDate),
                'engagement_trends' => $this->getEngagementTrends($courseId, $startDate, $endDate),
                'top_contributors' => $this->getTopContributors($courseId, $startDate, $endDate),
                'activity_patterns' => $this->getActivityPatterns($courseId, $startDate, $endDate),
                'certificate_eligibility' => $this->getCertificateEligibilityStatus($courseId)
            ];

            return $metrics;
        });
    }

    public function processParticipationData(array $chatData): void
    {
        DB::transaction(function () use ($chatData) {
            foreach ($chatData as $messageData) {
                $this->updateParticipationRecord($messageData);
                $this->checkCertificateEligibility($messageData['user_id'], $messageData['course_id']);
            }
        });
    }

    public function generateParticipationCertificate(string $userId, string $courseId): ?array
    {
        $participationMetrics = $this->getUserParticipationMetrics($userId, $courseId);

        if (!$this->isEligibleForParticipationCertificate($participationMetrics)) {
            return null;
        }

        $certificateData = [
            'template_id' => 'discussion_participation',
            'recipient_name' => $participationMetrics['user_name'],
            'course_name' => $participationMetrics['course_name'],
            'completion_date' => now()->toDateString(),
            'certificate_id' => "CHAT-PART-{$courseId}-{$userId}-" . now()->format('Ymd'),
            'additional_fields' => [
                'total_messages' => $participationMetrics['message_count'],
                'discussion_days' => $participationMetrics['active_days'],
                'engagement_score' => $participationMetrics['engagement_score'],
                'participation_level' => $this->getParticipationLevel($participationMetrics['engagement_score'])
            ]
        ];

        // This would integrate with the existing certificate generation service
        return $this->callCertificateGenerationService($certificateData);
    }

    private function getOverviewMetrics(string $courseId, Carbon $startDate, Carbon $endDate): array
    {
        $chatData = $this->fetchChatDataFromMainAPI($courseId, $startDate, $endDate);

        return [
            'total_messages' => count($chatData['messages']),
            'unique_participants' => count(array_unique(array_column($chatData['messages'], 'user_id'))),
            'average_messages_per_day' => $this->calculateAverageMessagesPerDay($chatData['messages'], $startDate, $endDate),
            'most_active_day' => $this->getMostActiveDay($chatData['messages']),
            'response_time_avg' => $this->calculateAverageResponseTime($chatData['messages']),
            'instructor_participation_rate' => $this->calculateInstructorParticipationRate($chatData['messages'])
        ];
    }

    private function getParticipationMetrics(string $courseId, Carbon $startDate, Carbon $endDate): array
    {
        $chatData = $this->fetchChatDataFromMainAPI($courseId, $startDate, $endDate);
        $enrollmentData = $this->fetchEnrollmentDataFromMainAPI($courseId);

        $participantMetrics = [];

        foreach ($enrollmentData['users'] as $user) {
            $userMessages = array_filter($chatData['messages'], fn($msg) => $msg['user_id'] === $user['id']);

            $participantMetrics[] = [
                'user_id' => $user['id'],
                'user_name' => $user['name'],
                'role' => $user['role'],
                'message_count' => count($userMessages),
                'active_days' => $this->countActiveDays($userMessages),
                'first_message_date' => $this->getFirstMessageDate($userMessages),
                'last_message_date' => $this->getLastMessageDate($userMessages),
                'engagement_score' => $this->calculateEngagementScore($userMessages, $startDate, $endDate),
                'certificate_eligible' => $this->isEligibleForParticipationCertificate([
                    'message_count' => count($userMessages),
                    'active_days' => $this->countActiveDays($userMessages),
                    'engagement_score' => $this->calculateEngagementScore($userMessages, $startDate, $endDate)
                ])
            ];
        }

        return $participantMetrics;
    }

    private function getEngagementTrends(string $courseId, Carbon $startDate, Carbon $endDate): array
    {
        $chatData = $this->fetchChatDataFromMainAPI($courseId, $startDate, $endDate);
        $trends = [];

        $current = $startDate->copy();
        while ($current <= $endDate) {
            $dayMessages = array_filter($chatData['messages'], function($msg) use ($current) {
                return Carbon::parse($msg['created_at'])->isSameDay($current);
            });

            $trends[] = [
                'date' => $current->toDateString(),
                'message_count' => count($dayMessages),
                'unique_participants' => count(array_unique(array_column($dayMessages, 'user_id'))),
                'avg_response_time' => $this->calculateAverageResponseTime($dayMessages)
            ];

            $current->addDay();
        }

        return $trends;
    }

    private function getTopContributors(string $courseId, Carbon $startDate, Carbon $endDate): array
    {
        $participationMetrics = $this->getParticipationMetrics($courseId, $startDate, $endDate);

        $studentMetrics = array_filter($participationMetrics, fn($p) => $p['role'] === 'student');
        usort($studentMetrics, fn($a, $b) => $b['engagement_score'] <=> $a['engagement_score']);

        return array_slice($studentMetrics, 0, 10);
    }

    private function getActivityPatterns(string $courseId, Carbon $startDate, Carbon $endDate): array
    {
        $chatData = $this->fetchChatDataFromMainAPI($courseId, $startDate, $endDate);

        return [
            'hourly_distribution' => $this->getHourlyDistribution($chatData['messages']),
            'daily_distribution' => $this->getDailyDistribution($chatData['messages']),
            'peak_activity_hours' => $this->getPeakActivityHours($chatData['messages']),
            'discussion_threads' => $this->analyzeDiscussionThreads($chatData['messages'])
        ];
    }

    private function getCertificateEligibilityStatus(string $courseId): array
    {
        $eligibleUsers = ChatParticipation::where('course_id', $courseId)
            ->where('message_count', '>=', config('chat.certificate.min_messages', 10))
            ->where('active_days', '>=', config('chat.certificate.min_active_days', 3))
            ->where('engagement_score', '>=', config('chat.certificate.min_engagement_score', 70))
            ->get();

        return [
            'total_eligible' => $eligibleUsers->count(),
            'eligible_users' => $eligibleUsers->map(function($participation) {
                return [
                    'user_id' => $participation->user_id,
                    'user_name' => $this->getUserName($participation->user_id),
                    'message_count' => $participation->message_count,
                    'active_days' => $participation->active_days,
                    'engagement_score' => $participation->engagement_score,
                    'certificate_generated' => $participation->certificate_generated
                ];
            })->toArray()
        ];
    }

    private function fetchChatDataFromMainAPI(string $courseId, Carbon $startDate, Carbon $endDate): array
    {
        try {
            $response = Http::withToken(config('chat.main_api.token'))
                ->timeout(30)
                ->get(config('chat.main_api.url') . "/api/v1/chat/analytics/course/{$courseId}", [
                    'start_date' => $startDate->toISOString(),
                    'end_date' => $endDate->toISOString()
                ]);

            if ($response->failed()) {
                Log::error("Failed to fetch chat data", [
                    'course_id' => $courseId,
                    'status' => $response->status(),
                    'response' => $response->body()
                ]);
                throw new \Exception("Failed to fetch chat data from main API");
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error("Exception fetching chat data", ['error' => $e->getMessage()]);
            // Return empty data structure for graceful degradation
            return ['messages' => []];
        }
    }

    private function fetchEnrollmentDataFromMainAPI(string $courseId): array
    {
        try {
            $response = Http::withToken(config('chat.main_api.token'))
                ->timeout(30)
                ->get(config('chat.main_api.url') . "/api/v1/courses/{$courseId}/enrollments");

            if ($response->failed()) {
                throw new \Exception("Failed to fetch enrollment data from main API");
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error("Exception fetching enrollment data", ['error' => $e->getMessage()]);
            return ['users' => []];
        }
    }

    private function calculateEngagementScore(array $messages, Carbon $startDate, Carbon $endDate): int
    {
        if (empty($messages)) return 0;

        $totalDays = $startDate->diffInDays($endDate) + 1;
        $activeDays = $this->countActiveDays($messages);
        $messageCount = count($messages);

        // Engagement score factors:
        $consistencyScore = ($activeDays / max($totalDays, 1)) * 40;
        $volumeScore = min(($messageCount / 10) * 30, 30);
        $qualityScore = $this->calculateQualityScore($messages) * 30;

        return min(100, round($consistencyScore + $volumeScore + $qualityScore));
    }

    private function calculateQualityScore(array $messages): float
    {
        if (empty($messages)) return 0;

        $totalScore = 0;
        foreach ($messages as $message) {
            $length = strlen($message['content']);

            // Quality factors: length, response timing, etc.
            if ($length < 10) $score = 0.3;
            elseif ($length < 50) $score = 0.6;
            elseif ($length < 100) $score = 0.8;
            else $score = 1.0;

            $totalScore += $score;
        }

        return $totalScore / count($messages);
    }

    private function isEligibleForParticipationCertificate(array $metrics): bool
    {
        return $metrics['message_count'] >= config('chat.certificate.min_messages', 10) &&
               $metrics['active_days'] >= config('chat.certificate.min_active_days', 3) &&
               $metrics['engagement_score'] >= config('chat.certificate.min_engagement_score', 70);
    }

    private function updateParticipationRecord(array $messageData): void
    {
        ChatParticipation::updateOrCreate([
            'user_id' => $messageData['user_id'],
            'course_id' => $messageData['course_id']
        ], [
            'message_count' => DB::raw('message_count + 1'),
            'last_activity_date' => now(),
            'updated_at' => now()
        ]);

        // Recalculate active days
        $this->recalculateActiveDays($messageData['user_id'], $messageData['course_id']);
    }

    private function recalculateActiveDays(string $userId, string $courseId): void
    {
        $chatData = $this->fetchChatDataFromMainAPI($courseId, now()->subYear(), now());
        $userMessages = array_filter($chatData['messages'], fn($msg) => $msg['user_id'] === $userId);
        $activeDays = $this->countActiveDays($userMessages);

        ChatParticipation::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->update([
                'active_days' => $activeDays,
                'engagement_score' => $this->calculateEngagementScore($userMessages, now()->subYear(), now())
            ]);
    }

    private function checkCertificateEligibility(string $userId, string $courseId): void
    {
        $participation = ChatParticipation::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->first();

        if (!$participation || $participation->certificate_generated) {
            return;
        }

        $metrics = $this->getUserParticipationMetrics($userId, $courseId);

        if ($this->isEligibleForParticipationCertificate($metrics)) {
            $certificate = $this->generateParticipationCertificate($userId, $courseId);

            if ($certificate) {
                $participation->update([
                    'certificate_generated' => true,
                    'certificate_id' => $certificate['certificate_id']
                ]);

                Log::info("Participation certificate generated", [
                    'user_id' => $userId,
                    'course_id' => $courseId,
                    'certificate_id' => $certificate['certificate_id']
                ]);
            }
        }
    }

    private function getUserParticipationMetrics(string $userId, string $courseId): array
    {
        $participation = ChatParticipation::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->first();

        if (!$participation) {
            return [
                'message_count' => 0,
                'active_days' => 0,
                'engagement_score' => 0,
                'user_name' => $this->getUserName($userId),
                'course_name' => $this->getCourseName($courseId)
            ];
        }

        return [
            'message_count' => $participation->message_count,
            'active_days' => $participation->active_days,
            'engagement_score' => $participation->engagement_score,
            'user_name' => $this->getUserName($userId),
            'course_name' => $this->getCourseName($courseId)
        ];
    }

    private function countActiveDays(array $messages): int
    {
        if (empty($messages)) return 0;

        $uniqueDays = [];
        foreach ($messages as $message) {
            $date = Carbon::parse($message['created_at'])->format('Y-m-d');
            $uniqueDays[$date] = true;
        }

        return count($uniqueDays);
    }

    private function getFirstMessageDate(array $messages): ?string
    {
        if (empty($messages)) return null;

        $dates = array_map(fn($msg) => Carbon::parse($msg['created_at']), $messages);
        return min($dates)->toDateString();
    }

    private function getLastMessageDate(array $messages): ?string
    {
        if (empty($messages)) return null;

        $dates = array_map(fn($msg) => Carbon::parse($msg['created_at']), $messages);
        return max($dates)->toDateString();
    }

    private function calculateAverageMessagesPerDay(array $messages, Carbon $startDate, Carbon $endDate): float
    {
        $totalDays = $startDate->diffInDays($endDate) + 1;
        return $totalDays > 0 ? count($messages) / $totalDays : 0;
    }

    private function getMostActiveDay(array $messages): array
    {
        if (empty($messages)) return ['date' => null, 'count' => 0];

        $dayCount = [];
        foreach ($messages as $message) {
            $date = Carbon::parse($message['created_at'])->format('Y-m-d');
            $dayCount[$date] = ($dayCount[$date] ?? 0) + 1;
        }

        $maxDate = array_keys($dayCount, max($dayCount))[0];
        return ['date' => $maxDate, 'count' => $dayCount[$maxDate]];
    }

    private function calculateAverageResponseTime(array $messages): float
    {
        if (count($messages) < 2) return 0;

        usort($messages, fn($a, $b) => Carbon::parse($a['created_at'])->timestamp <=> Carbon::parse($b['created_at'])->timestamp);

        $totalTime = 0;
        $responseCount = 0;

        for ($i = 1; $i < count($messages); $i++) {
            $prevTime = Carbon::parse($messages[$i - 1]['created_at']);
            $currentTime = Carbon::parse($messages[$i]['created_at']);

            $diffMinutes = $prevTime->diffInMinutes($currentTime);

            // Only count responses within reasonable time frame (24 hours)
            if ($diffMinutes <= 1440) {
                $totalTime += $diffMinutes;
                $responseCount++;
            }
        }

        return $responseCount > 0 ? round($totalTime / $responseCount, 2) : 0;
    }

    private function calculateInstructorParticipationRate(array $messages): float
    {
        if (empty($messages)) return 0;

        $instructorMessages = array_filter($messages, function($msg) {
            return $this->isInstructor($msg['user_id']);
        });

        return (count($instructorMessages) / count($messages)) * 100;
    }

    private function getHourlyDistribution(array $messages): array
    {
        $hourlyCount = array_fill(0, 24, 0);

        foreach ($messages as $message) {
            $hour = Carbon::parse($message['created_at'])->hour;
            $hourlyCount[$hour]++;
        }

        return $hourlyCount;
    }

    private function getDailyDistribution(array $messages): array
    {
        $dailyCount = array_fill(0, 7, 0); // Sunday = 0, Monday = 1, etc.

        foreach ($messages as $message) {
            $dayOfWeek = Carbon::parse($message['created_at'])->dayOfWeek;
            $dailyCount[$dayOfWeek]++;
        }

        return $dailyCount;
    }

    private function getPeakActivityHours(array $messages): array
    {
        $hourlyCount = $this->getHourlyDistribution($messages);
        $maxCount = max($hourlyCount);

        $peakHours = [];
        foreach ($hourlyCount as $hour => $count) {
            if ($count === $maxCount) {
                $peakHours[] = $hour;
            }
        }

        return $peakHours;
    }

    private function analyzeDiscussionThreads(array $messages): array
    {
        // Simple thread analysis - can be enhanced based on parent_message_id
        return [
            'total_threads' => count(array_filter($messages, fn($msg) => !isset($msg['parent_message_id']))),
            'average_thread_length' => count($messages) / max(1, count(array_filter($messages, fn($msg) => !isset($msg['parent_message_id'])))),
            'longest_thread' => $this->findLongestThread($messages)
        ];
    }

    private function findLongestThread(array $messages): int
    {
        // Simplified implementation
        return 1;
    }

    private function getParticipationLevel(int $score): string
    {
        if ($score >= 90) return 'Outstanding';
        if ($score >= 80) return 'Excellent';
        if ($score >= 70) return 'Good';
        if ($score >= 60) return 'Average';
        return 'Needs Improvement';
    }

    private function callCertificateGenerationService(array $certificateData): ?array
    {
        try {
            // This would call the existing certificate generation service
            $response = Http::withToken(config('services.certificate_service.token'))
                ->post(config('services.certificate_service.url') . '/generate', $certificateData);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error("Certificate generation failed", [
                'status' => $response->status(),
                'response' => $response->body()
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error("Certificate generation exception", ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function getUserName(string $userId): string
    {
        // This would typically fetch from a user service or cached data
        return "User {$userId}";
    }

    private function getCourseName(string $courseId): string
    {
        // This would typically fetch from a course service or cached data
        return "Course {$courseId}";
    }

    private function isInstructor(string $userId): bool
    {
        // This would check user role from the main system
        return false; // Placeholder implementation
    }
}