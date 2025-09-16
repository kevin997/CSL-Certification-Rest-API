<?php

namespace App\Services\ChatSearch;

use App\Models\Search\ChatSearchIndex;
use App\Models\Archival\ArchivedChatMessage;
use App\Services\ChatArchival\MessageArchivalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;

class ChatSearchService
{
    public function __construct(
        private MessageArchivalService $archivalService
    ) {}

    public function searchMessages(
        string $query,
        ?string $courseId = null,
        ?string $userId = null,
        array $filters = []
    ): array {
        // Validate user permissions first
        if ($courseId && $userId && !$this->validateUserAccess($userId, $courseId)) {
            throw new \Exception("User does not have access to this course");
        }

        $cacheKey = "chat_search:" . md5($query . $courseId . json_encode($filters));
        $cacheTime = config('chat.search.cache_duration', 300); // 5 minutes

        return Cache::remember($cacheKey, $cacheTime, function() use ($query, $courseId, $filters) {
            $searchResults = [
                'active_messages' => $this->searchActiveMessages($query, $courseId, $filters),
                'archived_messages' => $this->searchArchivedMessages($query, $courseId, $filters),
                'total_results' => 0,
                'search_metadata' => [
                    'query' => $query,
                    'course_id' => $courseId,
                    'search_time' => now()->toISOString(),
                    'filters_applied' => $filters,
                    'cache_hit' => false
                ]
            ];

            $searchResults['total_results'] =
                count($searchResults['active_messages']) +
                count($searchResults['archived_messages']);

            // Combine and sort results by relevance and date
            $searchResults['combined_results'] = $this->combineAndRankResults(
                $searchResults['active_messages'],
                $searchResults['archived_messages'],
                $query
            );

            return $searchResults;
        });
    }

    public function indexMessages(array $messages, bool $isArchived = false): int
    {
        $indexed = 0;

        DB::transaction(function() use ($messages, $isArchived, &$indexed) {
            foreach ($messages as $message) {
                if ($this->indexMessage($message, $isArchived)) {
                    $indexed++;
                }
            }
        });

        Log::info("Indexed messages for search", [
            'total_messages' => count($messages),
            'successfully_indexed' => $indexed,
            'is_archived' => $isArchived
        ]);

        return $indexed;
    }

    public function buildSearchIndex(string $courseId): array
    {
        Log::info("Building search index for course", ['course_id' => $courseId]);

        $stats = [
            'active_messages_indexed' => 0,
            'archived_messages_indexed' => 0,
            'total_indexed' => 0,
            'index_size_mb' => 0,
            'start_time' => now()
        ];

        try {
            // Clear existing index for this course
            ChatSearchIndex::where('course_id', $courseId)->delete();

            // Index active messages
            $activeMessages = $this->fetchActiveMessages($courseId);
            $stats['active_messages_indexed'] = $this->indexMessages($activeMessages, false);

            // Index archived messages
            $archivedMessages = $this->fetchArchivedMessages($courseId);
            $stats['archived_messages_indexed'] = $this->indexMessages($archivedMessages, true);

            $stats['total_indexed'] = $stats['active_messages_indexed'] + $stats['archived_messages_indexed'];

            // Calculate index size
            $indexSize = DB::table('chat_search_index')
                ->where('course_id', $courseId)
                ->sum(DB::raw('LENGTH(content)'));
            $stats['index_size_mb'] = round($indexSize / (1024 * 1024), 2);

        } catch (\Exception $e) {
            Log::error("Failed to build search index", [
                'course_id' => $courseId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }

        $stats['end_time'] = now();
        $stats['duration_seconds'] = $stats['start_time']->diffInSeconds($stats['end_time']);

        Log::info("Search index build completed", $stats);

        return $stats;
    }

    public function getSearchSuggestions(string $partialQuery, ?string $courseId = null, int $limit = 10): array
    {
        $cacheKey = "search_suggestions:" . md5($partialQuery . ($courseId ?? ''));
        $cacheTime = config('chat.search.suggestion_cache_duration', 1800); // 30 minutes

        return Cache::remember($cacheKey, $cacheTime, function() use ($partialQuery, $courseId, $limit) {
            // Get suggestions based on indexed content
            $contentSuggestions = ChatSearchIndex::select('content')
                ->when($courseId, fn($q) => $q->where('course_id', $courseId))
                ->whereRaw("MATCH(content) AGAINST(? IN BOOLEAN MODE)", ["{$partialQuery}*"])
                ->limit($limit * 2) // Get more to filter better suggestions
                ->get()
                ->pluck('content')
                ->unique()
                ->take($limit);

            // Extract relevant phrases containing the partial query
            $suggestions = $contentSuggestions->map(function($content) use ($partialQuery) {
                return $this->extractRelevantPhrases($content, $partialQuery);
            })->flatten()->unique()->values()->take($limit)->toArray();

            // Also get popular search terms
            $popularTerms = $this->getPopularSearchTerms($partialQuery, $courseId, 3);

            return [
                'query_suggestions' => $suggestions,
                'popular_terms' => $popularTerms,
                'generated_at' => now()->toISOString()
            ];
        });
    }

    public function getSearchAnalytics(?string $courseId = null, int $days = 30): array
    {
        $startDate = now()->subDays($days);

        $analytics = DB::table('search_logs')
            ->when($courseId, fn($q) => $q->where('course_id', $courseId))
            ->where('searched_at', '>=', $startDate)
            ->selectRaw('
                COUNT(*) as total_searches,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT query) as unique_queries,
                AVG(result_count) as avg_results_per_search,
                MAX(result_count) as max_results,
                query
            ')
            ->groupBy('query')
            ->orderByDesc('total_searches')
            ->limit(20)
            ->get();

        $topQueries = $analytics->map(fn($item) => [
            'query' => $item->query,
            'search_count' => $item->total_searches,
            'avg_results' => round($item->avg_results_per_search, 1)
        ])->toArray();

        $totalStats = DB::table('search_logs')
            ->when($courseId, fn($q) => $q->where('course_id', $courseId))
            ->where('searched_at', '>=', $startDate)
            ->selectRaw('
                COUNT(*) as total_searches,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT DATE(searched_at)) as active_days,
                AVG(result_count) as avg_results
            ')
            ->first();

        return [
            'period' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => now()->toDateString(),
                'days' => $days
            ],
            'summary' => [
                'total_searches' => $totalStats->total_searches ?? 0,
                'unique_users' => $totalStats->unique_users ?? 0,
                'active_days' => $totalStats->active_days ?? 0,
                'avg_results_per_search' => round($totalStats->avg_results ?? 0, 1)
            ],
            'top_queries' => $topQueries,
            'course_id' => $courseId,
            'generated_at' => now()->toISOString()
        ];
    }

    private function searchActiveMessages(string $query, ?string $courseId, array $filters): array
    {
        try {
            $response = Http::withToken(config('chat.main_api.token'))
                ->timeout(30)
                ->get(config('chat.main_api.url') . "/api/v1/chat/search", [
                    'query' => $query,
                    'course_id' => $courseId,
                    'filters' => $filters,
                    'include_metadata' => true,
                    'limit' => config('chat.search.max_active_results', 50)
                ]);

            if ($response->failed()) {
                Log::warning("Failed to search active messages", [
                    'query' => $query,
                    'course_id' => $courseId,
                    'response_status' => $response->status(),
                    'response_body' => $response->body()
                ]);
                return [];
            }

            return $response->json('messages', []);

        } catch (\Exception $e) {
            Log::error("Exception during active message search", [
                'query' => $query,
                'course_id' => $courseId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    private function searchArchivedMessages(string $query, ?string $courseId, array $filters): array
    {
        try {
            $searchQuery = ChatSearchIndex::whereRaw("MATCH(content) AGAINST(? IN BOOLEAN MODE)", [$query])
                ->when($courseId, fn($q) => $q->where('course_id', $courseId))
                ->when(!empty($filters['start_date']), fn($q) => $q->where('message_date', '>=', $filters['start_date']))
                ->when(!empty($filters['end_date']), fn($q) => $q->where('message_date', '<=', $filters['end_date']))
                ->when(!empty($filters['user_id']), fn($q) => $q->where('user_id', $filters['user_id']))
                ->orderByRaw("MATCH(content) AGAINST(? IN BOOLEAN MODE) DESC", [$query])
                ->limit(config('chat.search.max_archived_results', 100));

            $indexResults = $searchQuery->get();

            $messages = [];
            foreach ($indexResults as $result) {
                // For archived messages, we need to fetch the full message data
                if ($result->is_archived) {
                    $archivedMessage = $this->fetchMessageFromArchive(
                        $result->message_id,
                        $result->course_id,
                        $result->message_date
                    );
                    if ($archivedMessage) {
                        $archivedMessage['search_relevance'] = $this->calculateRelevance($query, $result->content);
                        $messages[] = $archivedMessage;
                    }
                } else {
                    // For recently indexed active messages, use index data
                    $messages[] = [
                        'id' => $result->message_id,
                        'course_id' => $result->course_id,
                        'user_id' => $result->user_id,
                        'content' => $result->content,
                        'created_at' => $result->message_date->toISOString(),
                        'is_archived' => false,
                        'search_relevance' => $this->calculateRelevance($query, $result->content)
                    ];
                }
            }

            return $messages;

        } catch (\Exception $e) {
            Log::error("Exception during archived message search", [
                'query' => $query,
                'course_id' => $courseId,
                'error' => $e->getMessage()
            ]);
            return [];
        }
    }

    private function indexMessage(array $message, bool $isArchived = false): bool
    {
        try {
            // Skip messages with no content or invalid data
            if (empty($message['content']) || empty($message['id'])) {
                return false;
            }

            ChatSearchIndex::updateOrCreate([
                'message_id' => $message['id'],
            ], [
                'course_id' => $message['course_id'] ?? null,
                'environment_id' => $message['environment_id'] ?? null,
                'user_id' => $message['user_id'] ?? null,
                'content' => strip_tags($message['content']), // Remove HTML tags for better search
                'message_date' => Carbon::parse($message['created_at']),
                'is_archived' => $isArchived,
                'indexed_at' => now()
            ]);

            return true;

        } catch (\Exception $e) {
            Log::warning("Failed to index message", [
                'message_id' => $message['id'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function fetchActiveMessages(string $courseId): array
    {
        $response = Http::withToken(config('chat.main_api.token'))
            ->timeout(120)
            ->get(config('chat.main_api.url') . "/api/v1/chat/messages", [
                'course_id' => $courseId,
                'for_indexing' => true,
                'include_content' => true,
                'limit' => config('chat.search.max_indexing_messages', 10000)
            ]);

        if ($response->failed()) {
            throw new \Exception("Failed to fetch active messages for indexing: " . $response->body());
        }

        return $response->json('messages', []);
    }

    private function fetchArchivedMessages(string $courseId): array
    {
        $archivedFiles = ArchivedChatMessage::where('course_id', $courseId)
            ->orderBy('archived_date', 'desc')
            ->get();

        $messages = [];

        foreach ($archivedFiles as $file) {
            try {
                if (!Storage::disk('s3')->exists($file->archive_path)) {
                    Log::warning("Archive file not found in S3", [
                        'file_path' => $file->archive_path,
                        'archive_id' => $file->id
                    ]);
                    continue;
                }

                $content = Storage::disk('s3')->get($file->archive_path);
                $archiveData = json_decode($content, true);

                if (!$archiveData || !isset($archiveData['messages'])) {
                    Log::warning("Invalid archive file format", [
                        'file_path' => $file->archive_path,
                        'archive_id' => $file->id
                    ]);
                    continue;
                }

                // Verify checksum if available
                if (isset($archiveData['checksum']) && $file->checksum) {
                    $computedChecksum = md5(json_encode($archiveData['messages']));
                    if ($computedChecksum !== $file->checksum) {
                        Log::error("Archive file checksum mismatch", [
                            'file_path' => $file->archive_path,
                            'expected' => $file->checksum,
                            'computed' => $computedChecksum
                        ]);
                        continue;
                    }
                }

                foreach ($archiveData['messages'] as $message) {
                    $message['is_archived'] = true;
                    $message['archive_path'] = $file->archive_path;
                    $messages[] = $message;
                }

            } catch (\Exception $e) {
                Log::warning("Failed to read archived file for indexing", [
                    'file_path' => $file->archive_path,
                    'archive_id' => $file->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $messages;
    }

    private function fetchMessageFromArchive(string $messageId, string $courseId, Carbon $messageDate): ?array
    {
        // Find the archive file containing this message
        $archiveFile = ArchivedChatMessage::where('course_id', $courseId)
            ->where('start_date', '<=', $messageDate)
            ->where('end_date', '>=', $messageDate)
            ->first();

        if (!$archiveFile) {
            return null;
        }

        try {
            $content = Storage::disk('s3')->get($archiveFile->archive_path);
            $archiveData = json_decode($content, true);

            if (!$archiveData || !isset($archiveData['messages'])) {
                return null;
            }

            foreach ($archiveData['messages'] as $message) {
                if ($message['id'] === $messageId) {
                    $message['is_archived'] = true;
                    $message['archive_path'] = $archiveFile->archive_path;
                    return $message;
                }
            }

        } catch (\Exception $e) {
            Log::warning("Failed to fetch message from archive", [
                'message_id' => $messageId,
                'archive_path' => $archiveFile->archive_path,
                'error' => $e->getMessage()
            ]);
        }

        return null;
    }

    private function validateUserAccess(string $userId, string $courseId): bool
    {
        try {
            $response = Http::withToken(config('chat.main_api.token'))
                ->timeout(10)
                ->get(config('chat.main_api.url') . "/api/v1/courses/{$courseId}/user-access/{$userId}");

            return $response->successful() && $response->json('has_access', false);

        } catch (\Exception $e) {
            Log::warning("Failed to validate user access", [
                'user_id' => $userId,
                'course_id' => $courseId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function logSearch(string $query, ?string $courseId, ?string $userId, int $resultCount): void
    {
        try {
            DB::table('search_logs')->insert([
                'user_id' => $userId,
                'course_id' => $courseId,
                'query' => substr($query, 0, 255), // Limit query length
                'result_count' => $resultCount,
                'searched_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]);
        } catch (\Exception $e) {
            Log::warning("Failed to log search", [
                'query' => $query,
                'error' => $e->getMessage()
            ]);
        }
    }

    private function extractRelevantPhrases(string $content, string $partialQuery): array
    {
        $words = explode(' ', $content);
        $phrases = [];
        $queryLength = strlen($partialQuery);

        foreach ($words as $index => $word) {
            if (stripos($word, $partialQuery) !== false) {
                $start = max(0, $index - 2);
                $end = min(count($words) - 1, $index + 2);
                $phrase = implode(' ', array_slice($words, $start, $end - $start + 1));
                $phrases[] = trim($phrase);
            }
        }

        return array_unique($phrases);
    }

    private function getPopularSearchTerms(string $partialQuery, ?string $courseId, int $limit): array
    {
        return DB::table('search_logs')
            ->select('query', DB::raw('COUNT(*) as search_count'))
            ->when($courseId, fn($q) => $q->where('course_id', $courseId))
            ->where('query', 'LIKE', "%{$partialQuery}%")
            ->where('searched_at', '>=', now()->subDays(30))
            ->groupBy('query')
            ->orderByDesc('search_count')
            ->limit($limit)
            ->pluck('query')
            ->toArray();
    }

    private function combineAndRankResults(array $activeMessages, array $archivedMessages, string $query): array
    {
        $combined = array_merge($activeMessages, $archivedMessages);

        // Sort by relevance and recency
        usort($combined, function($a, $b) use ($query) {
            $aRelevance = $a['search_relevance'] ?? $this->calculateRelevance($query, $a['content'] ?? '');
            $bRelevance = $b['search_relevance'] ?? $this->calculateRelevance($query, $b['content'] ?? '');

            // Primary sort: relevance (higher first)
            if ($aRelevance !== $bRelevance) {
                return $bRelevance <=> $aRelevance;
            }

            // Secondary sort: recency (newer first)
            $aDate = Carbon::parse($a['created_at'])->timestamp;
            $bDate = Carbon::parse($b['created_at'])->timestamp;
            return $bDate <=> $aDate;
        });

        return array_slice($combined, 0, config('chat.search.max_combined_results', 100));
    }

    private function calculateRelevance(string $query, string $content): float
    {
        $query = strtolower($query);
        $content = strtolower($content);

        // Exact match gets highest score
        if (strpos($content, $query) !== false) {
            return 1.0;
        }

        // Word-based matching
        $queryWords = explode(' ', $query);
        $contentWords = explode(' ', $content);
        $matches = 0;

        foreach ($queryWords as $queryWord) {
            foreach ($contentWords as $contentWord) {
                if (strpos($contentWord, $queryWord) !== false) {
                    $matches++;
                    break;
                }
            }
        }

        return count($queryWords) > 0 ? $matches / count($queryWords) : 0;
    }
}