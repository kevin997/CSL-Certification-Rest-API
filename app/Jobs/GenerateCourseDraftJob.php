<?php

namespace App\Jobs;

use App\Ai\Agents\CourseBuilderAgent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Generates a full KURSA course draft (template -> blocks -> activities) from
 * an instructor's free-text description via CourseBuilderAgent, then writes
 * the normalized result to the cache key polled by
 * CourseBuilderController::result(). See that controller for the cache
 * contract (`course_builder:{jobId}` => {user_id, status, draft|message}).
 */
class GenerateCourseDraftJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    /**
     * The activity types the model is allowed to use — unknown values are
     * normalized to `lesson`.
     */
    private const ACTIVITY_TYPES = [
        'text', 'video', 'quiz', 'lesson', 'assignment', 'documentation', 'feedback', 'certificate',
    ];

    private const MAX_BLOCKS = 6;

    private const MAX_ACTIVITIES_PER_BLOCK = 5;

    public function __construct(
        public string $jobId,
        public int $userId,
        public string $description,
        public string $language,
    ) {}

    public function handle(): void
    {
        try {
            $languageLine = $this->language === 'en'
                ? 'Respond in English.'
                : 'Réponds en français.';

            $prompt = $languageLine."\n\n".$this->description;

            // Try the primary (larger) model first, then fail over to a
            // smaller model hosted on the same Ollama box. This is done here
            // rather than via App\Ai\Agents\Concerns\FailsOverToFallbackModel
            // because that concern hardcodes its fallback to `llama3.2:1b`.
            try {
                $response = (new CourseBuilderAgent)->prompt($prompt);
            } catch (Throwable $e) {
                Log::warning('GenerateCourseDraftJob: primary model failed, retrying with fallback model', [
                    'job_id' => $this->jobId,
                    'fallback_model' => 'llama3.1:8b',
                    'error' => $e->getMessage(),
                ]);

                $response = (new CourseBuilderAgent)->prompt($prompt, model: 'llama3.1:8b');
            }

            $draft = $this->normalize($response->toArray());

            Cache::put("course_builder:{$this->jobId}", [
                'user_id' => $this->userId,
                'status' => 'done',
                'draft' => $draft,
            ], now()->addMinutes(45));
        } catch (Throwable $e) {
            Log::error('GenerateCourseDraftJob: failed to generate course draft', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
            ]);

            Cache::put("course_builder:{$this->jobId}", [
                'user_id' => $this->userId,
                'status' => 'failed',
                'message' => 'The course draft could not be generated. Please try again.',
            ], now()->addMinutes(45));
        }
    }

    /**
     * Validate and normalize the raw structured output: whitelist activity
     * types (unknown -> lesson), cap blocks/activities, trim strings, drop
     * empty blocks, and ensure at most one certificate activity (the last
     * one found is kept).
     *
     * @param  array<string, mixed>  $raw
     * @return array{title: string, description: string, blocks: array<int, array{title: string, description: string, activities: array<int, array{title: string, type: string, description: string}>}>}
     */
    private function normalize(array $raw): array
    {
        $title = trim((string) ($raw['title'] ?? ''));
        $description = trim((string) ($raw['description'] ?? ''));

        $blocks = [];

        foreach ((array) ($raw['blocks'] ?? []) as $block) {
            if (count($blocks) >= self::MAX_BLOCKS) {
                break;
            }

            if (! is_array($block)) {
                continue;
            }

            $blockTitle = trim((string) ($block['title'] ?? ''));
            $blockDescription = trim((string) ($block['description'] ?? ''));

            $activities = [];

            foreach ((array) ($block['activities'] ?? []) as $activity) {
                if (count($activities) >= self::MAX_ACTIVITIES_PER_BLOCK) {
                    break;
                }

                if (! is_array($activity)) {
                    continue;
                }

                $activityTitle = trim((string) ($activity['title'] ?? ''));
                $activityDescription = trim((string) ($activity['description'] ?? ''));

                if ($activityTitle === '' || $activityDescription === '') {
                    continue;
                }

                $type = (string) ($activity['type'] ?? '');
                if (! in_array($type, self::ACTIVITY_TYPES, true)) {
                    $type = 'lesson';
                }

                $activities[] = [
                    'title' => $activityTitle,
                    'type' => $type,
                    'description' => $activityDescription,
                ];
            }

            // Drop empty blocks (no title/description, or no valid activities).
            if ($blockTitle === '' || $blockDescription === '' || empty($activities)) {
                continue;
            }

            $blocks[] = [
                'title' => $blockTitle,
                'description' => $blockDescription,
                'activities' => $activities,
            ];
        }

        return [
            'title' => $title,
            'description' => $description,
            'blocks' => $this->ensureAtMostOneCertificate($blocks),
        ];
    }

    /**
     * Ensure at most one `certificate` activity exists across the whole
     * draft, keeping the last one found and converting any earlier ones to
     * `lesson`.
     *
     * @param  array<int, array{title: string, description: string, activities: array<int, array{title: string, type: string, description: string}>}>  $blocks
     * @return array<int, array{title: string, description: string, activities: array<int, array{title: string, type: string, description: string}>}>
     */
    private function ensureAtMostOneCertificate(array $blocks): array
    {
        $lastBlockIndex = null;
        $lastActivityIndex = null;

        foreach ($blocks as $blockIndex => $block) {
            foreach ($block['activities'] as $activityIndex => $activity) {
                if ($activity['type'] === 'certificate') {
                    $lastBlockIndex = $blockIndex;
                    $lastActivityIndex = $activityIndex;
                }
            }
        }

        if ($lastBlockIndex === null) {
            return $blocks;
        }

        foreach ($blocks as $blockIndex => $block) {
            foreach ($block['activities'] as $activityIndex => $activity) {
                if ($activity['type'] !== 'certificate') {
                    continue;
                }

                if ($blockIndex !== $lastBlockIndex || $activityIndex !== $lastActivityIndex) {
                    $blocks[$blockIndex]['activities'][$activityIndex]['type'] = 'lesson';
                }
            }
        }

        return $blocks;
    }
}
