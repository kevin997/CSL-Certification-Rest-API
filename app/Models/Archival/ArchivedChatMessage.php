<?php

namespace App\Models\Archival;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ArchivedChatMessage extends Model
{
    use HasUuids;

    protected $table = 'archived_chat_messages';

    protected $fillable = [
        'course_id',
        'environment_id',
        'archive_path',
        'message_count',
        'storage_size_mb',
        'archived_date',
        'start_date',
        'end_date',
        'checksum',
        'batch_index'
    ];

    protected $casts = [
        'archived_date' => 'datetime',
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'storage_size_mb' => 'float',
        'batch_index' => 'integer'
    ];

    /**
     * Scope to filter by course
     */
    public function scopeForCourse($query, string $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->where(function($q) use ($startDate, $endDate) {
            $q->whereBetween('start_date', [$startDate, $endDate])
              ->orWhereBetween('end_date', [$startDate, $endDate])
              ->orWhere(function($subQ) use ($startDate, $endDate) {
                  $subQ->where('start_date', '<=', $startDate)
                       ->where('end_date', '>=', $endDate);
              });
        });
    }

    /**
     * Scope to filter by environment
     */
    public function scopeForEnvironment($query, string $environmentId)
    {
        return $query->where('environment_id', $environmentId);
    }

    /**
     * Get total storage size for a course
     */
    public static function getTotalStorageForCourse(string $courseId): float
    {
        return static::where('course_id', $courseId)
                    ->sum('storage_size_mb');
    }

    /**
     * Get archive statistics for a course
     */
    public static function getArchiveStats(string $courseId): array
    {
        $stats = static::where('course_id', $courseId)
            ->selectRaw('
                COUNT(*) as total_archives,
                SUM(message_count) as total_messages,
                SUM(storage_size_mb) as total_storage_mb,
                MIN(start_date) as earliest_message,
                MAX(end_date) as latest_message
            ')
            ->first();

        return [
            'total_archives' => $stats->total_archives ?? 0,
            'total_messages' => $stats->total_messages ?? 0,
            'total_storage_mb' => round($stats->total_storage_mb ?? 0, 2),
            'earliest_message' => $stats->earliest_message,
            'latest_message' => $stats->latest_message,
            'date_range_days' => $stats->earliest_message && $stats->latest_message
                ? $stats->earliest_message->diffInDays($stats->latest_message)
                : 0
        ];
    }
}