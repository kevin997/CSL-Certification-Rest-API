<?php

namespace App\Http\Resources\Analytics;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EngagementReportResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'overview' => [
                'total_messages' => $this->resource['overview']['total_messages'] ?? 0,
                'unique_participants' => $this->resource['overview']['unique_participants'] ?? 0,
                'average_messages_per_day' => round($this->resource['overview']['average_messages_per_day'] ?? 0, 2),
                'most_active_day' => $this->resource['overview']['most_active_day'] ?? null,
                'response_time_avg' => round($this->resource['overview']['response_time_avg'] ?? 0, 2),
                'instructor_participation_rate' => round($this->resource['overview']['instructor_participation_rate'] ?? 0, 2),
            ],

            'participation' => ParticipationMetricsResource::collection(
                $this->resource['participation'] ?? []
            ),

            'engagement_trends' => collect($this->resource['engagement_trends'] ?? [])->map(function ($trend) {
                return [
                    'date' => $trend['date'],
                    'message_count' => $trend['message_count'],
                    'unique_participants' => $trend['unique_participants'],
                    'avg_response_time' => round($trend['avg_response_time'] ?? 0, 2),
                    'engagement_level' => $this->getEngagementLevel($trend['message_count'], $trend['unique_participants']),
                ];
            }),

            'top_contributors' => collect($this->resource['top_contributors'] ?? [])->map(function ($contributor) {
                return [
                    'user_id' => $contributor['user_id'],
                    'user_name' => $contributor['user_name'],
                    'role' => $contributor['role'],
                    'message_count' => $contributor['message_count'],
                    'active_days' => $contributor['active_days'],
                    'engagement_score' => $contributor['engagement_score'],
                    'participation_level' => $this->getParticipationLevel($contributor['engagement_score']),
                    'certificate_eligible' => $contributor['certificate_eligible'] ?? false,
                ];
            }),

            'activity_patterns' => [
                'hourly_distribution' => $this->formatHourlyDistribution(
                    $this->resource['activity_patterns']['hourly_distribution'] ?? []
                ),
                'daily_distribution' => $this->formatDailyDistribution(
                    $this->resource['activity_patterns']['daily_distribution'] ?? []
                ),
                'peak_activity_hours' => $this->resource['activity_patterns']['peak_activity_hours'] ?? [],
                'discussion_threads' => [
                    'total_threads' => $this->resource['activity_patterns']['discussion_threads']['total_threads'] ?? 0,
                    'average_thread_length' => round($this->resource['activity_patterns']['discussion_threads']['average_thread_length'] ?? 0, 2),
                    'longest_thread' => $this->resource['activity_patterns']['discussion_threads']['longest_thread'] ?? 0,
                ],
            ],

            'certificate_eligibility' => [
                'total_eligible' => $this->resource['certificate_eligibility']['total_eligible'] ?? 0,
                'eligible_users' => collect($this->resource['certificate_eligibility']['eligible_users'] ?? [])->map(function ($user) {
                    return [
                        'user_id' => $user['user_id'],
                        'user_name' => $user['user_name'],
                        'message_count' => $user['message_count'],
                        'active_days' => $user['active_days'],
                        'engagement_score' => $user['engagement_score'],
                        'participation_level' => $this->getParticipationLevel($user['engagement_score']),
                        'certificate_generated' => $user['certificate_generated'] ?? false,
                    ];
                }),
            ],

            'metadata' => [
                'generated_at' => now()->toISOString(),
                'total_participants' => $this->resource['overview']['unique_participants'] ?? 0,
                'report_period' => [
                    'days' => $this->calculateReportPeriod(),
                    'has_sufficient_data' => ($this->resource['overview']['total_messages'] ?? 0) > 0,
                ],
                'data_quality' => [
                    'completeness_score' => $this->calculateCompletenessScore(),
                    'reliability_score' => $this->calculateReliabilityScore(),
                ],
            ],
        ];
    }

    /**
     * Format hourly distribution for better readability
     */
    private function formatHourlyDistribution(array $hourlyData): array
    {
        $formatted = [];
        foreach ($hourlyData as $hour => $count) {
            $formatted[] = [
                'hour' => $hour,
                'time' => sprintf('%02d:00', $hour),
                'message_count' => $count,
                'percentage' => $this->calculatePercentage($count, array_sum($hourlyData)),
            ];
        }
        return $formatted;
    }

    /**
     * Format daily distribution for better readability
     */
    private function formatDailyDistribution(array $dailyData): array
    {
        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        $formatted = [];

        foreach ($dailyData as $day => $count) {
            $formatted[] = [
                'day_of_week' => $day,
                'day_name' => $dayNames[$day] ?? 'Unknown',
                'message_count' => $count,
                'percentage' => $this->calculatePercentage($count, array_sum($dailyData)),
                'is_weekend' => in_array($day, [0, 6]), // Sunday and Saturday
            ];
        }

        return $formatted;
    }

    /**
     * Get engagement level based on message count and participants
     */
    private function getEngagementLevel(int $messageCount, int $participants): string
    {
        if ($participants === 0) return 'No Activity';

        $ratio = $messageCount / $participants;

        if ($ratio >= 10) return 'Very High';
        if ($ratio >= 5) return 'High';
        if ($ratio >= 2) return 'Moderate';
        if ($ratio >= 1) return 'Low';

        return 'Very Low';
    }

    /**
     * Get participation level based on engagement score
     */
    private function getParticipationLevel(int $score): string
    {
        if ($score >= 90) return 'Outstanding';
        if ($score >= 80) return 'Excellent';
        if ($score >= 70) return 'Good';
        if ($score >= 60) return 'Average';

        return 'Needs Improvement';
    }

    /**
     * Calculate percentage with precision
     */
    private function calculatePercentage(int $value, int $total): float
    {
        return $total > 0 ? round(($value / $total) * 100, 2) : 0;
    }

    /**
     * Calculate report period in days
     */
    private function calculateReportPeriod(): int
    {
        $trends = $this->resource['engagement_trends'] ?? [];
        return count($trends);
    }

    /**
     * Calculate data completeness score
     */
    private function calculateCompletenessScore(): float
    {
        $score = 0;
        $maxScore = 6;

        // Check if we have basic data
        if (($this->resource['overview']['total_messages'] ?? 0) > 0) $score++;
        if (($this->resource['overview']['unique_participants'] ?? 0) > 0) $score++;
        if (!empty($this->resource['engagement_trends'])) $score++;
        if (!empty($this->resource['participation'])) $score++;
        if (!empty($this->resource['activity_patterns']['hourly_distribution'])) $score++;
        if (!empty($this->resource['top_contributors'])) $score++;

        return round(($score / $maxScore) * 100, 2);
    }

    /**
     * Calculate data reliability score
     */
    private function calculateReliabilityScore(): float
    {
        $totalMessages = $this->resource['overview']['total_messages'] ?? 0;
        $trends = $this->resource['engagement_trends'] ?? [];

        // Higher reliability for more data points and consistent trends
        if ($totalMessages === 0) return 0;
        if ($totalMessages < 10) return 40;
        if ($totalMessages < 50) return 60;
        if ($totalMessages < 100) return 80;

        return 95; // High reliability for large datasets
    }
}