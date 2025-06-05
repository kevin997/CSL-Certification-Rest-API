<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class EnrollmentAnalytics extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'enrollment_id',
        'activity_id',
        'activity_type',
        
        // Time-based metrics
        'time_spent',
        'active_time',
        'idle_time',
        'session_duration',
        'total_sessions',
        'average_session_length',
        'first_accessed_at',
        'last_accessed_at',
        'completed_at',
        'time_to_completion',
        
        // Interaction metrics
        'click_count',
        'scroll_count',
        'scroll_depth',
        'focus_events',
        'pause_resume_events',
        'retry_attempts',
        'navigation_events',
        
        // Engagement metrics
        'engagement_score',
        'completion_percentage',
        'interaction_frequency',
        
        // Additional metadata
        'performance_data',
        'device_info',
        'event_log',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'first_accessed_at' => 'datetime',
            'last_accessed_at' => 'datetime',
            'completed_at' => 'datetime',
            'engagement_score' => 'float',
            'completion_percentage' => 'float',
            'interaction_frequency' => 'float',
            'performance_data' => 'json',
            'device_info' => 'json',
            'event_log' => 'json',
        ];
    }

    /**
     * Get the enrollment that these analytics belong to.
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * Calculate the engagement score based on various metrics.
     * 
     * @return float Score from 0-100
     */
    public function calculateEngagementScore(): float
    {
        // Base weightings for different metric types
        $weights = [
            'timeEngagement' => 0.4,    // Weight for time-based metrics
            'interactionRate' => 0.35,  // Weight for interaction metrics
            'completionRate' => 0.25    // Weight for completion percentage
        ];
        
        // Score time engagement (0-100)
        $timeScore = 0;
        if ($this->time_spent > 0) {
            // Calculate active time ratio (0-1)
            $activeRatio = $this->active_time / max($this->time_spent, 1);
            
            // Calculate session consistency
            $sessionConsistency = $this->total_sessions > 1 
                ? $this->average_session_length / max($this->time_spent / $this->total_sessions, 1)
                : 1;
                
            // Calculate overall time score
            $timeScore = min(100, 
                (log10(max($this->time_spent, 10)) * 25) * // Base score from total time
                (0.7 + (0.3 * $activeRatio)) *             // Adjustment for active time ratio
                (0.8 + (0.2 * $sessionConsistency))        // Adjustment for session consistency
            );
        }
        
        // Score interaction engagement (0-100)
        $interactionScore = 0;
        if ($this->time_spent > 0) {
            // Get normalized scores for different interaction types
            $normalizedClicks = min(100, ($this->click_count / max($this->time_spent / 60, 1)) * 10);
            $normalizedScrolls = min(100, ($this->scroll_count / max($this->time_spent / 60, 1)) * 5);
            $normalizedNavigation = min(100, ($this->navigation_events / max($this->time_spent / 60, 1)) * 15);
            
            // Different weights for different interaction types
            $interactionScore = 
                ($normalizedClicks * 0.5) + 
                ($normalizedScrolls * 0.3) + 
                ($normalizedNavigation * 0.2);
            
            // Adjust for scroll depth (0-100)
            $interactionScore = $interactionScore * (0.5 + (0.5 * ($this->scroll_depth / 100)));
            
            // Cap at 100
            $interactionScore = min(100, $interactionScore);
        }
        
        // Combine all factors into final score
        $finalScore = round(
            ($timeScore * $weights['timeEngagement']) +
            ($interactionScore * $weights['interactionRate']) +
            ($this->completion_percentage * $weights['completionRate'])
        );
        
        return min(100, max(0, $finalScore));
    }

    /**
     * Update the analytics record with new session data
     * 
     * @param array $metrics New metrics to update
     * @return $this
     */
    public function updateSessionMetrics(array $metrics): self
    {
        // Update time-based metrics
        if (isset($metrics['time_spent'])) {
            $this->time_spent += $metrics['time_spent'];
        }
        
        if (isset($metrics['active_time'])) {
            $this->active_time += $metrics['active_time'];
        }
        
        if (isset($metrics['idle_time'])) {
            $this->idle_time += $metrics['idle_time'];
        }
        
        // Track session information
        if (isset($metrics['session_duration'])) {
            // Update total sessions count
            $this->total_sessions = ($this->total_sessions ?? 0) + 1;
            
            // Update average session length
            $totalDuration = ($this->average_session_length * ($this->total_sessions - 1)) + $metrics['session_duration'];
            $this->average_session_length = $totalDuration / $this->total_sessions;
            
            // Update current session duration
            $this->session_duration = $metrics['session_duration'];
        }
        
        // Update interaction metrics
        foreach (['click_count', 'scroll_count', 'focus_events', 'pause_resume_events', 'retry_attempts', 'navigation_events'] as $metric) {
            if (isset($metrics[$metric])) {
                $this->$metric = ($this->$metric ?? 0) + $metrics[$metric];
            }
        }
        
        // Update max values for metrics that should take the highest value
        if (isset($metrics['scroll_depth']) && $metrics['scroll_depth'] > $this->scroll_depth) {
            $this->scroll_depth = $metrics['scroll_depth'];
        }
        
        // Update completion percentage if provided
        if (isset($metrics['completion_percentage'])) {
            $this->completion_percentage = $metrics['completion_percentage'];
        }
        
        // Update timestamps
        $this->last_accessed_at = now();
        if (!$this->first_accessed_at) {
            $this->first_accessed_at = now();
        }
        
        // If completed for the first time, record completion timestamp and calculate time to completion
        if (isset($metrics['completed']) && $metrics['completed'] && !$this->completed_at) {
            $this->completed_at = now();
            $this->time_to_completion = $this->first_accessed_at->diffInSeconds($this->completed_at);
        }
        
        // Recalculate engagement score
        $this->engagement_score = $this->calculateEngagementScore();
        
        // Recalculate interaction frequency
        if ($this->time_spent > 0) {
            $totalInteractions = $this->click_count + $this->scroll_count + $this->focus_events + $this->navigation_events;
            $this->interaction_frequency = $totalInteractions / ($this->time_spent / 60); // per minute
        }
        
        // Update device info if provided
        if (isset($metrics['device_info']) && is_array($metrics['device_info'])) {
            $this->device_info = $metrics['device_info'];
        }
        
        // Update performance data if provided
        if (isset($metrics['performance_data']) && is_array($metrics['performance_data'])) {
            $this->performance_data = $metrics['performance_data'];
        }
        
        // Add events to event log if provided
        if (isset($metrics['events']) && is_array($metrics['events'])) {
            $eventLog = $this->event_log ?? [];
            foreach ($metrics['events'] as $event) {
                $event['timestamp'] = $event['timestamp'] ?? now()->toIso8601String();
                $eventLog[] = $event;
            }
            $this->event_log = $eventLog;
        }
        
        $this->save();
        
        return $this;
    }
}
