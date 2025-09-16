<?php

namespace App\Models\Archival;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ArchivalJob extends Model
{
    use HasUuids;

    protected $table = 'archival_jobs';

    protected $fillable = [
        'course_id',
        'environment_id',
        'cutoff_date',
        'status',
        'progress',
        'messages_archived',
        'storage_size_mb',
        'error_message',
        'started_at',
        'completed_at'
    ];

    protected $casts = [
        'cutoff_date' => 'datetime',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'progress' => 'float',
        'storage_size_mb' => 'float',
        'messages_archived' => 'integer'
    ];

    /**
     * Scope for active jobs
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'processing');
    }

    /**
     * Scope for completed jobs
     */
    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    /**
     * Scope for failed jobs
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    /**
     * Scope to filter by course
     */
    public function scopeForCourse($query, string $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    /**
     * Scope to filter by environment
     */
    public function scopeForEnvironment($query, string $environmentId)
    {
        return $query->where('environment_id', $environmentId);
    }

    /**
     * Check if job is in progress
     */
    public function isInProgress(): bool
    {
        return $this->status === 'processing';
    }

    /**
     * Check if job is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if job failed
     */
    public function hasFailed(): bool
    {
        return $this->status === 'failed';
    }

    /**
     * Get duration of the job
     */
    public function getDurationAttribute(): ?int
    {
        if (!$this->started_at) {
            return null;
        }

        $endTime = $this->completed_at ?? now();
        return $this->started_at->diffInSeconds($endTime);
    }

    /**
     * Get formatted progress percentage
     */
    public function getFormattedProgressAttribute(): string
    {
        return round($this->progress, 1) . '%';
    }

    /**
     * Get latest job for a course
     */
    public static function getLatestForCourse(string $courseId): ?self
    {
        return static::where('course_id', $courseId)
                    ->orderBy('started_at', 'desc')
                    ->first();
    }

    /**
     * Get running jobs count
     */
    public static function getRunningJobsCount(): int
    {
        return static::where('status', 'processing')->count();
    }

    /**
     * Get job statistics
     */
    public static function getJobStats(int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $stats = static::where('started_at', '>=', $startDate)
            ->selectRaw('
                COUNT(*) as total_jobs,
                SUM(CASE WHEN status = "completed" THEN 1 ELSE 0 END) as completed_jobs,
                SUM(CASE WHEN status = "failed" THEN 1 ELSE 0 END) as failed_jobs,
                SUM(CASE WHEN status = "processing" THEN 1 ELSE 0 END) as active_jobs,
                SUM(messages_archived) as total_messages_archived,
                SUM(storage_size_mb) as total_storage_mb,
                AVG(progress) as avg_progress
            ')
            ->first();

        return [
            'period_days' => $days,
            'total_jobs' => $stats->total_jobs ?? 0,
            'completed_jobs' => $stats->completed_jobs ?? 0,
            'failed_jobs' => $stats->failed_jobs ?? 0,
            'active_jobs' => $stats->active_jobs ?? 0,
            'success_rate' => $stats->total_jobs > 0
                ? round(($stats->completed_jobs / $stats->total_jobs) * 100, 1)
                : 0,
            'total_messages_archived' => $stats->total_messages_archived ?? 0,
            'total_storage_mb' => round($stats->total_storage_mb ?? 0, 2),
            'avg_progress' => round($stats->avg_progress ?? 0, 1)
        ];
    }
}