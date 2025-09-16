<?php

namespace App\Models\Search;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ChatSearchIndex extends Model
{
    use HasUuids;

    protected $table = 'chat_search_index';

    protected $fillable = [
        'message_id',
        'course_id',
        'environment_id',
        'user_id',
        'content',
        'message_date',
        'is_archived',
        'indexed_at'
    ];

    protected $casts = [
        'message_date' => 'datetime',
        'indexed_at' => 'datetime',
        'is_archived' => 'boolean'
    ];

    /**
     * Scope for full-text search
     */
    public function scopeSearch($query, string $searchTerm)
    {
        return $query->whereRaw(
            "MATCH(content) AGAINST(? IN BOOLEAN MODE)",
            [$searchTerm]
        )->orderByRaw(
            "MATCH(content) AGAINST(? IN BOOLEAN MODE) DESC",
            [$searchTerm]
        );
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
     * Scope to filter by user
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope for archived messages only
     */
    public function scopeArchived($query)
    {
        return $query->where('is_archived', true);
    }

    /**
     * Scope for active messages only
     */
    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    /**
     * Scope to filter by date range
     */
    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('message_date', [$startDate, $endDate]);
    }

    /**
     * Scope for recent messages
     */
    public function scopeRecent($query, int $days = 30)
    {
        return $query->where('message_date', '>=', now()->subDays($days));
    }

    /**
     * Get search suggestions based on content
     */
    public static function getSearchSuggestions(string $partialQuery, ?string $courseId = null, int $limit = 10): array
    {
        $query = static::select('content')
            ->whereRaw("MATCH(content) AGAINST(? IN BOOLEAN MODE)", ["{$partialQuery}*"])
            ->when($courseId, fn($q) => $q->where('course_id', $courseId))
            ->limit($limit * 2) // Get more for better filtering
            ->distinct();

        return $query->get()
            ->pluck('content')
            ->map(function($content) use ($partialQuery) {
                // Extract phrases containing the search term
                $words = explode(' ', $content);
                $phrases = [];

                foreach ($words as $index => $word) {
                    if (stripos($word, $partialQuery) !== false) {
                        $start = max(0, $index - 2);
                        $end = min(count($words) - 1, $index + 2);
                        $phrase = implode(' ', array_slice($words, $start, $end - $start + 1));
                        $phrases[] = trim($phrase);
                    }
                }

                return array_unique($phrases);
            })
            ->flatten()
            ->unique()
            ->take($limit)
            ->values()
            ->toArray();
    }

    /**
     * Get index statistics
     */
    public static function getIndexStats(?string $courseId = null): array
    {
        $query = static::query();

        if ($courseId) {
            $query->where('course_id', $courseId);
        }

        $stats = $query->selectRaw('
            COUNT(*) as total_indexed,
            COUNT(DISTINCT course_id) as indexed_courses,
            COUNT(DISTINCT user_id) as indexed_users,
            SUM(CASE WHEN is_archived = 1 THEN 1 ELSE 0 END) as archived_messages,
            SUM(CASE WHEN is_archived = 0 THEN 1 ELSE 0 END) as active_messages,
            MIN(message_date) as earliest_message,
            MAX(message_date) as latest_message,
            MIN(indexed_at) as first_indexed,
            MAX(indexed_at) as last_indexed
        ')->first();

        $totalContentSize = $query->sum(\DB::raw('LENGTH(content)'));

        return [
            'total_indexed' => $stats->total_indexed ?? 0,
            'indexed_courses' => $stats->indexed_courses ?? 0,
            'indexed_users' => $stats->indexed_users ?? 0,
            'archived_messages' => $stats->archived_messages ?? 0,
            'active_messages' => $stats->active_messages ?? 0,
            'earliest_message' => $stats->earliest_message,
            'latest_message' => $stats->latest_message,
            'first_indexed' => $stats->first_indexed,
            'last_indexed' => $stats->last_indexed,
            'content_size_mb' => round($totalContentSize / (1024 * 1024), 2),
            'course_id' => $courseId,
            'generated_at' => now()
        ];
    }

    /**
     * Clean up old index entries
     */
    public static function cleanupOldEntries(int $days = 365): int
    {
        return static::where('indexed_at', '<', now()->subDays($days))
                    ->where('is_archived', false) // Only cleanup non-archived entries
                    ->delete();
    }

    /**
     * Rebuild index for a course
     */
    public static function rebuildCourseIndex(string $courseId): void
    {
        // Delete existing index entries for this course
        static::where('course_id', $courseId)->delete();
    }

    /**
     * Get most searched terms
     */
    public static function getMostSearchedTerms(?string $courseId = null, int $days = 30, int $limit = 10): array
    {
        // This would typically come from search logs, but we can provide related functionality
        return \DB::table('search_logs')
            ->select('query', \DB::raw('COUNT(*) as search_count'))
            ->when($courseId, fn($q) => $q->where('course_id', $courseId))
            ->where('searched_at', '>=', now()->subDays($days))
            ->groupBy('query')
            ->orderByDesc('search_count')
            ->limit($limit)
            ->get()
            ->toArray();
    }
}