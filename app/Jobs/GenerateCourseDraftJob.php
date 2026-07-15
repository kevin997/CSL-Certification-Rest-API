<?php

namespace App\Jobs;

use App\Ai\Agents\CourseBuilderAgent;
use App\Ai\Agents\TemplateEnhancerAgent;
use App\Models\Template;
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
 * contract (`course_builder:{jobId}` => {user_id, status, kind, draft|
 * additions|message}).
 *
 * When constructed with a `templateId`, the job instead ACTS ON an existing
 * template: it loads its current structure via TemplateEnhancerAgent and
 * proposes additions only (new blocks / new activities in existing blocks).
 * Existing content is never modified or deleted — the frontend applies the
 * additions via the existing creation endpoints.
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

    private const MAX_NEW_BLOCKS = 3;

    private const MAX_ACTIVITIES_PER_NEW_BLOCK = 5;

    private const MAX_ACTIVITIES_PER_BLOCK_ADDITION = 4;

    /**
     * Roughly how much of the existing template structure to include in the
     * enhance prompt, to keep the job within its model context/time budget.
     */
    private const MAX_STRUCTURE_CHARS = 4000;

    public function __construct(
        public string $jobId,
        public int $userId,
        public string $description,
        public string $language,
        public ?int $templateId = null,
    ) {}

    public function handle(): void
    {
        if ($this->templateId !== null) {
            $this->handleEnhance();

            return;
        }

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
                    'fallback_model' => 'llama3.2:1b',
                    'error' => $e->getMessage(),
                ]);

                $response = (new CourseBuilderAgent)->prompt($prompt, model: 'llama3.2:1b');
            }

            $draft = $this->normalize($response->toArray());

            Cache::put("course_builder:{$this->jobId}", [
                'user_id' => $this->userId,
                'status' => 'done',
                'kind' => 'new',
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
     * Generate addition-only proposals for an existing template via
     * TemplateEnhancerAgent. Mirrors the 'new' path above (same failover
     * pattern, same cache contract), but the payload/normalization shape is
     * additions against the template's current structure rather than a full
     * draft.
     */
    private function handleEnhance(): void
    {
        try {
            // The Template model uses BelongsToEnvironment, whose global
            // scope resolves the current environment from the HTTP session —
            // there is no session in a queue worker, so the scope silently
            // no-ops here. That's safe: ownership/access was already
            // authorized in CourseBuilderController::generate() before this
            // job was dispatched, and template ids are unique regardless of
            // environment. Blocks/activities carry no environment scope.
            $template = Template::withoutGlobalScopes()
                ->with('blocks.activities')
                ->find($this->templateId);

            if (! $template) {
                throw new \RuntimeException("Template {$this->templateId} not found.");
            }

            $languageLine = $this->language === 'en'
                ? 'Respond in English.'
                : 'Réponds en français.';

            $structure = $this->serializeTemplateStructure($template);

            $prompt = $languageLine."\n\nInstructor request: {$this->description}".
                "\n\nCurrent template structure:\n{$structure}";

            // Same primary/fallback model failover as the 'new' path — see
            // the comment there for why this isn't the shared concern.
            try {
                $response = (new TemplateEnhancerAgent)->prompt($prompt);
            } catch (Throwable $e) {
                Log::warning('GenerateCourseDraftJob: primary model failed, retrying with fallback model', [
                    'job_id' => $this->jobId,
                    'fallback_model' => 'llama3.2:1b',
                    'error' => $e->getMessage(),
                ]);

                $response = (new TemplateEnhancerAgent)->prompt($prompt, model: 'llama3.2:1b');
            }

            $additions = $this->normalizeAdditions($response->toArray(), $template);

            Cache::put("course_builder:{$this->jobId}", [
                'user_id' => $this->userId,
                'status' => 'done',
                'kind' => 'enhance',
                'template_id' => $this->templateId,
                'additions' => $additions,
            ], now()->addMinutes(45));
        } catch (Throwable $e) {
            Log::error('GenerateCourseDraftJob: failed to generate template additions', [
                'job_id' => $this->jobId,
                'template_id' => $this->templateId,
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
     * Serialize a template's current title/description and block/activity
     * structure into a compact, numbered-list prompt fragment, so the model
     * knows what already exists (and each block's real id) before proposing
     * additions. Truncated to keep the prompt within budget.
     */
    private function serializeTemplateStructure(Template $template): string
    {
        $lines = [];
        $lines[] = "Title: {$template->title}";
        $lines[] = 'Description: '.($template->description ?? '');
        $lines[] = '';

        foreach ($template->blocks as $block) {
            $activities = $block->activities
                ->map(fn ($activity) => '['.$activity->type->value.'] '.$activity->title)
                ->implode('; ');

            $lines[] = "Block #{$block->id}: {$block->title} — activities: {$activities}";
        }

        $structure = implode("\n", $lines);

        return mb_substr($structure, 0, self::MAX_STRUCTURE_CHARS);
    }

    /**
     * Validate and normalize the raw structured additions output: whitelist
     * activity types (unknown -> lesson), cap new blocks/activities, drop
     * block_additions targeting a block id that isn't really on this
     * template, drop activities that duplicate an existing activity title in
     * that block (case-insensitive), trim strings, and drop empties.
     *
     * @param  array<string, mixed>  $raw
     * @return array{new_blocks: array<int, array{title: string, description: string, activities: array<int, array{title: string, type: string, description: string}>}>, block_additions: array<int, array{block_id: int, block_title: string, activities: array<int, array{title: string, type: string, description: string}>}>}
     */
    private function normalizeAdditions(array $raw, Template $template): array
    {
        $newBlocks = [];

        foreach ((array) ($raw['new_blocks'] ?? []) as $block) {
            if (count($newBlocks) >= self::MAX_NEW_BLOCKS) {
                break;
            }

            if (! is_array($block)) {
                continue;
            }

            $blockTitle = trim((string) ($block['title'] ?? ''));
            $blockDescription = trim((string) ($block['description'] ?? ''));

            $activities = $this->normalizeActivities(
                (array) ($block['activities'] ?? []),
                self::MAX_ACTIVITIES_PER_NEW_BLOCK,
            );

            if ($blockTitle === '' || $blockDescription === '' || empty($activities)) {
                continue;
            }

            $newBlocks[] = [
                'title' => $blockTitle,
                'description' => $blockDescription,
                'activities' => $activities,
            ];
        }

        $realBlocks = $template->blocks->keyBy('id');

        $blockAdditions = [];

        foreach ((array) ($raw['block_additions'] ?? []) as $addition) {
            if (! is_array($addition)) {
                continue;
            }

            $blockId = (int) ($addition['block_id'] ?? 0);
            $block = $realBlocks->get($blockId);

            // Drop additions targeting a block id that isn't really on this
            // template.
            if (! $block) {
                continue;
            }

            $existingTitles = $block->activities
                ->map(fn ($activity) => mb_strtolower(trim($activity->title)))
                ->all();

            $activities = $this->normalizeActivities(
                (array) ($addition['activities'] ?? []),
                self::MAX_ACTIVITIES_PER_BLOCK_ADDITION,
                $existingTitles,
            );

            if (empty($activities)) {
                continue;
            }

            $blockAdditions[] = [
                'block_id' => $block->id,
                'block_title' => $block->title,
                'activities' => $activities,
            ];
        }

        return [
            'new_blocks' => $newBlocks,
            'block_additions' => $blockAdditions,
        ];
    }

    /**
     * Whitelist types, cap count, trim strings, drop empties, and (when
     * `$excludeTitles` is given) drop activities whose lowercase title
     * already exists.
     *
     * @param  array<int, mixed>  $rawActivities
     * @param  array<int, string>  $excludeTitles  lowercase, trimmed existing titles to avoid duplicating
     * @return array<int, array{title: string, type: string, description: string}>
     */
    private function normalizeActivities(array $rawActivities, int $max, array $excludeTitles = []): array
    {
        $activities = [];

        foreach ($rawActivities as $activity) {
            if (count($activities) >= $max) {
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

            if (in_array(mb_strtolower($activityTitle), $excludeTitles, true)) {
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

        return $activities;
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
