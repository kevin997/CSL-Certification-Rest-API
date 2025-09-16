<?php

namespace App\Models\Analytics;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasEnvironmentSlug;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Carbon\Carbon;

class CourseEngagement extends Model
{
    use HasUuids, HasEnvironmentSlug;

    protected $table = 'course_engagement_analytics';

    protected $fillable = [
        'course_id',
        'date',
        'total_messages',
        'unique_participants',
        'average_response_time',
        'instructor_participation_rate',
        'engagement_score',
        'peak_activity_hour',
        'environment_id'
    ];

    protected $casts = [
        'date' => 'date',
        'total_messages' => 'integer',
        'unique_participants' => 'integer',
        'average_response_time' => 'decimal:2',
        'instructor_participation_rate' => 'decimal:2',
        'engagement_score' => 'decimal:2',
        'peak_activity_hour' => 'integer'
    ];

    protected $dates = [
        'date',
        'created_at',
        'updated_at'
    ];

    /**
     * Boot the model and add global scopes
     */
    protected static function boot()
    {
        parent::boot();

        // Add environment scope if trait is available
        if (method_exists(static::class, 'addGlobalScope')) {
            static::addGlobalScope(new \App\Scopes\EnvironmentScope);
        }
    }

    /**
     * Scope to get engagement data for a specific course
     */
    public function scopeByCourse($query, string $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    /**
     * Scope to get engagement data within date range
     */
    public function scopeInDateRange($query, Carbon $startDate, Carbon $endDate)
    {
        return $query->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')]);
    }

    /**
     * Scope to get high engagement days
     */
    public function scopeHighEngagement($query, float $threshold = 70.0)
    {
        return $query->where('engagement_score', '>=', $threshold);
    }

    /**
     * Scope to get recent engagement data
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('date', '>=', now()->subDays($days)->format('Y-m-d'));
    }

    /**
     * Get formatted date for display
     */
    public function getFormattedDateAttribute(): string
    {
        return $this->date->format('M d, Y');
    }

    /**
     * Get engagement level based on score
     */
    public function getEngagementLevelAttribute(): string
    {
        $score = $this->engagement_score;

        if ($score >= 90) return 'Very High';
        if ($score >= 70) return 'High';
        if ($score >= 50) return 'Moderate';
        if ($score >= 30) return 'Low';

        return 'Very Low';
    }

    /**
     * Check if this is a weekend
     */
    public function getIsWeekendAttribute(): bool
    {
        return $this->date->isWeekend();
    }

    /**
     * Get the day of week name
     */
    public function getDayOfWeekNameAttribute(): string
    {
        return $this->date->format('l'); // Full day name like 'Monday'
    }

    /**
     * Calculate participation rate
     */
    public function getParticipationRateAttribute(): float
    {
        if ($this->total_messages == 0) {
            return 0;
        }

        // This would need course enrollment data to be accurate
        // For now, use a simplified calculation
        return min(100, ($this->unique_participants / max(1, $this->total_messages)) * 100);
    }

    /**
     * Static method to calculate daily engagement score
     */
    public static function calculateEngagementScore(
        int $totalMessages,
        int $uniqueParticipants,
        float $avgResponseTime,
        float $instructorRate
    ): float {
        // Engagement scoring algorithm
        $messageScore = min(30, $totalMessages * 2); // Max 30 points for messages
        $participantScore = min(30, $uniqueParticipants * 5); // Max 30 points for participants
        $responseTimeScore = max(0, 25 - ($avgResponseTime / 60)); // Max 25 points, penalty for slow response
        $instructorScore = min(15, $instructorRate); // Max 15 points for instructor participation

        return min(100, $messageScore + $participantScore + $responseTimeScore + $instructorScore);
    }

    /**
     * Get aggregated statistics for a course over time
     */
    public static function getCourseStatistics(string $courseId, Carbon $startDate, Carbon $endDate): array
    {
        $data = static::byCourse($courseId)
            ->inDateRange($startDate, $endDate)
            ->orderBy('date')
            ->get();

        if ($data->isEmpty()) {
            return [
                'total_days' => 0,
                'avg_daily_messages' => 0,
                'avg_participants' => 0,
                'avg_engagement_score' => 0,
                'peak_day' => null,
                'trend' => 'stable'
            ];
        }

        $totalMessages = $data->sum('total_messages');
        $avgParticipants = $data->avg('unique_participants');
        $avgEngagement = $data->avg('engagement_score');
        $peakDay = $data->sortByDesc('total_messages')->first();

        // Calculate trend (simplified)
        $firstHalf = $data->take($data->count() / 2)->avg('engagement_score');
        $secondHalf = $data->reverse()->take($data->count() / 2)->avg('engagement_score');
        $trend = $secondHalf > $firstHalf ? 'increasing' : ($secondHalf < $firstHalf ? 'decreasing' : 'stable');

        return [
            'total_days' => $data->count(),
            'total_messages' => $totalMessages,
            'avg_daily_messages' => round($totalMessages / $data->count(), 2),
            'avg_participants' => round($avgParticipants, 2),
            'avg_engagement_score' => round($avgEngagement, 2),
            'peak_day' => [
                'date' => $peakDay->date->format('Y-m-d'),
                'messages' => $peakDay->total_messages,
                'participants' => $peakDay->unique_participants
            ],
            'trend' => $trend
        ];
    }
}