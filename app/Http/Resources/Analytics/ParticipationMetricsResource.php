<?php

namespace App\Http\Resources\Analytics;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ParticipationMetricsResource extends JsonResource
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
            'user_id' => $this->resource['user_id'],
            'user_name' => $this->resource['user_name'],
            'role' => $this->resource['role'],
            'metrics' => [
                'message_count' => $this->resource['message_count'] ?? 0,
                'active_days' => $this->resource['active_days'] ?? 0,
                'engagement_score' => $this->resource['engagement_score'] ?? 0,
                'participation_level' => $this->getParticipationLevel($this->resource['engagement_score'] ?? 0),
            ],
            'activity_timeline' => [
                'first_message_date' => $this->resource['first_message_date'],
                'last_message_date' => $this->resource['last_message_date'],
                'days_since_first_message' => $this->calculateDaysSince($this->resource['first_message_date']),
                'days_since_last_message' => $this->calculateDaysSince($this->resource['last_message_date']),
                'activity_consistency' => $this->calculateActivityConsistency(),
            ],
            'certificate_status' => [
                'eligible' => $this->resource['certificate_eligible'] ?? false,
                'generated' => $this->resource['certificate_generated'] ?? false,
                'certificate_id' => $this->resource['certificate_id'] ?? null,
                'eligibility_reasons' => $this->getEligibilityReasons(),
            ],
            'performance_indicators' => [
                'messages_per_active_day' => $this->calculateMessagesPerActiveDay(),
                'engagement_trend' => $this->determineEngagementTrend(),
                'relative_activity' => $this->calculateRelativeActivity(),
            ]
        ];
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
     * Calculate days since a given date
     */
    private function calculateDaysSince(?string $date): ?int
    {
        if (!$date) return null;

        try {
            return now()->diffInDays(\Carbon\Carbon::parse($date));
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Calculate activity consistency ratio
     */
    private function calculateActivityConsistency(): float
    {
        $activeDays = $this->resource['active_days'] ?? 0;
        $firstMessage = $this->resource['first_message_date'];
        $lastMessage = $this->resource['last_message_date'];

        if (!$firstMessage || !$lastMessage || $activeDays === 0) {
            return 0;
        }

        try {
            $totalDaysSpan = \Carbon\Carbon::parse($firstMessage)->diffInDays(\Carbon\Carbon::parse($lastMessage)) + 1;
            return $totalDaysSpan > 0 ? round(($activeDays / $totalDaysSpan) * 100, 2) : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    /**
     * Calculate messages per active day
     */
    private function calculateMessagesPerActiveDay(): float
    {
        $messageCount = $this->resource['message_count'] ?? 0;
        $activeDays = $this->resource['active_days'] ?? 0;

        return $activeDays > 0 ? round($messageCount / $activeDays, 2) : 0;
    }

    /**
     * Determine engagement trend (simplified)
     */
    private function determineEngagementTrend(): string
    {
        // This is a simplified implementation
        // In a real scenario, you'd compare recent vs. historical engagement
        $engagementScore = $this->resource['engagement_score'] ?? 0;
        $daysSinceLastMessage = $this->calculateDaysSince($this->resource['last_message_date']);

        if ($daysSinceLastMessage === null) {
            return 'unknown';
        }

        if ($daysSinceLastMessage > 7) {
            return 'declining';
        }

        if ($engagementScore >= 80) {
            return 'increasing';
        }

        if ($engagementScore >= 60) {
            return 'stable';
        }

        return 'declining';
    }

    /**
     * Calculate relative activity compared to course average
     */
    private function calculateRelativeActivity(): string
    {
        // This would typically compare against course averages
        // For now, using general thresholds
        $messageCount = $this->resource['message_count'] ?? 0;

        if ($messageCount >= 50) return 'above_average';
        if ($messageCount >= 20) return 'average';
        if ($messageCount >= 10) return 'below_average';

        return 'low';
    }

    /**
     * Get reasons for certificate eligibility or ineligibility
     */
    private function getEligibilityReasons(): array
    {
        $messageCount = $this->resource['message_count'] ?? 0;
        $activeDays = $this->resource['active_days'] ?? 0;
        $engagementScore = $this->resource['engagement_score'] ?? 0;
        $eligible = $this->resource['certificate_eligible'] ?? false;

        $requirements = [
            'min_messages' => config('chat.certificate.min_messages', 10),
            'min_active_days' => config('chat.certificate.min_active_days', 3),
            'min_engagement_score' => config('chat.certificate.min_engagement_score', 70),
        ];

        $reasons = [];

        if ($eligible) {
            $reasons[] = 'All requirements met';
        } else {
            if ($messageCount < $requirements['min_messages']) {
                $reasons[] = sprintf(
                    'Need %d more messages (current: %d, required: %d)',
                    $requirements['min_messages'] - $messageCount,
                    $messageCount,
                    $requirements['min_messages']
                );
            }

            if ($activeDays < $requirements['min_active_days']) {
                $reasons[] = sprintf(
                    'Need %d more active days (current: %d, required: %d)',
                    $requirements['min_active_days'] - $activeDays,
                    $activeDays,
                    $requirements['min_active_days']
                );
            }

            if ($engagementScore < $requirements['min_engagement_score']) {
                $reasons[] = sprintf(
                    'Need %d more engagement points (current: %d, required: %d)',
                    $requirements['min_engagement_score'] - $engagementScore,
                    $engagementScore,
                    $requirements['min_engagement_score']
                );
            }
        }

        return $reasons;
    }
}