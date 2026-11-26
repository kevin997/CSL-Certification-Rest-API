<?php

namespace App\Services\Templates;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\AssignmentContent;
use App\Models\AssignmentCriterion;
use App\Models\Block;
use App\Models\CertificateContent;
use App\Models\DocumentationAttachment;
use App\Models\DocumentationContent;
use App\Models\DocumentBlob;
use App\Models\EventContent;
use App\Models\EventSession;
use App\Models\FeedbackContent;
use App\Models\FeedbackQuestion;
use App\Models\LessonContent;
use App\Models\LessonContentPart;
use App\Models\LessonQuestion;
use App\Models\LiveSession;
use App\Models\QuizContent;
use App\Models\QuizQuestion;
use App\Models\Template;
use App\Models\TextContent;
use App\Models\VideoContent;
use App\Models\VideoHandInContent;
use App\Models\WebinarContent;
use App\Services\Documents\DocumentProtectionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Deep-copies template content: template → blocks → activities → every
 * type-specific content row (questions, options, parts, criteria, sessions,
 * attachments...) while never copying learner data (responses, submissions,
 * completions, registrations, issued certificates, access logs).
 *
 * Used by template/block/activity duplicate and by the cross-template import
 * endpoints, so a copied activity opens with exactly the same content as the
 * source one.
 */
class TemplateContentCloner
{
    private const EXCLUDED = ['id', 'created_at', 'updated_at', 'deleted_at'];

    public function __construct(private readonly DocumentProtectionService $documents) {}

    public function duplicateTemplate(Template $source, int $userId, ?string $title = null): Template
    {
        return DB::transaction(function () use ($source, $userId, $title): Template {
            /** @var Template $copy */
            $copy = $source->replicate(self::EXCLUDED);
            $copy->title = $title ?? $this->copyTitle((string) $source->title);
            $copy->template_code = null;
            $copy->created_by = $userId;
            $copy->is_public = false;
            $copy->status = 'draft';
            $copy->save();

            $blocks = Block::query()
                ->where('template_id', $source->id)
                ->orderBy('order')
                ->orderBy('id')
                ->get();

            foreach ($blocks as $block) {
                $this->cloneBlockRow($block, $copy, $userId, true, (int) $block->order);
            }

            return $copy->fresh();
        });
    }

    /**
     * Append a copy of a block (and its activities/content) to a template.
     */
    public function duplicateBlock(Block $source, int $userId, ?string $title = null): Block
    {
        return DB::transaction(function () use ($source, $userId, $title): Block {
            $template = Template::findOrFail($source->template_id);
            $block = $this->cloneBlockRow($source, $template, $userId, true, null);

            if ($title !== null) {
                $block->title = $title;
                $block->save();
            }

            return $block->fresh();
        });
    }

    /**
     * Append a copy of one activity (and its content) to a block.
     */
    public function duplicateActivity(Activity $source, int $userId, ?string $title = null): Activity
    {
        return DB::transaction(function () use ($source, $userId, $title): Activity {
            $targetBlock = Block::findOrFail($source->block_id);
            $environmentId = Template::find($targetBlock->template_id)?->environment_id;

            $activity = $this->cloneActivityRow($source, $targetBlock, $userId, true, $environmentId, null);

            if ($title !== null) {
                $activity->title = $title;
                $activity->save();
            }

            return $activity->fresh();
        });
    }

    /**
     * @param  array<int, int|string>  $blockIds
     * @return Collection<int, Block>
     */
    public function importBlocks(Template $target, Template $source, array $blockIds, bool $includeContent, int $userId): Collection
    {
        return DB::transaction(function () use ($target, $source, $blockIds, $includeContent, $userId): Collection {
            $blocks = Block::query()
                ->where('template_id', $source->id)
                ->whereIn('id', $blockIds)
                ->orderBy('order')
                ->orderBy('id')
                ->get();

            if ($blocks->count() !== count(array_unique($blockIds))) {
                throw new RuntimeException('One or more blocks do not belong to the source template.');
            }

            $imported = collect();

            foreach ($blocks as $block) {
                $imported->push($this->cloneBlockRow($block, $target, $userId, $includeContent, null));
            }

            return $imported;
        });
    }

    /**
     * @param  array<int, int|string>  $activityIds
     * @return Collection<int, Activity>
     */
    public function importActivities(Block $targetBlock, Template $source, array $activityIds, bool $includeContent, int $userId): Collection
    {
        return DB::transaction(function () use ($targetBlock, $source, $activityIds, $includeContent, $userId): Collection {
            $targetTemplate = Template::findOrFail($targetBlock->template_id);
            $activities = Activity::query()
                ->whereIn('id', $activityIds)
                ->orderBy('order')
                ->orderBy('id')
                ->get();

            if ($activities->count() !== count(array_unique($activityIds))) {
                throw new RuntimeException('One or more activities were not found.');
            }

            $sourceBlockIds = Block::query()
                ->where('template_id', $source->id)
                ->pluck('id')
                ->all();

            $imported = collect();

            foreach ($activities as $activity) {
                if (! in_array((int) $activity->block_id, array_map('intval', $sourceBlockIds), true)) {
                    throw new RuntimeException('One or more activities do not belong to the source template.');
                }

                $imported->push(
                    $this->cloneActivityRow($activity, $targetBlock, $userId, $includeContent, $targetTemplate->environment_id, null),
                );
            }

            return $imported;
        });
    }

    private function cloneBlockRow(
        Block $source,
        Template $targetTemplate,
        int $userId,
        bool $includeContent,
        ?int $order,
    ): Block {
        /** @var Block $block */
        $block = $source->replicate(self::EXCLUDED);
        $block->template_id = $targetTemplate->id;
        $block->created_by = $userId;
        $block->order = $order ?? ((int) Block::query()->where('template_id', $targetTemplate->id)->max('order') + 1);
        $block->save();

        $activities = Activity::query()
            ->where('block_id', $source->id)
            ->orderBy('order')
            ->orderBy('id')
            ->get();

        foreach ($activities as $activity) {
            $this->cloneActivityRow(
                $activity,
                $block,
                $userId,
                $includeContent,
                $targetTemplate->environment_id,
                (int) $activity->order,
            );
        }

        return $block->fresh();
    }

    private function cloneActivityRow(
        Activity $source,
        Block $targetBlock,
        int $userId,
        bool $includeContent,
        ?int $environmentId,
        ?int $order,
    ): Activity {
        $type = $source->type instanceof ActivityType ? $source->type->value : (string) $source->type;

        /** @var Activity $activity */
        $activity = $source->replicate(self::EXCLUDED);
        $activity->block_id = $targetBlock->id;
        $activity->created_by = $userId;
        $activity->order = $order ?? ((int) Activity::query()->where('block_id', $targetBlock->id)->max('order') + 1);
        $activity->content_type = null;
        $activity->content_id = null;
        $activity->save();

        if (! $includeContent) {
            return $activity->fresh();
        }

        $pointer = $this->copyContent($type, $source, $activity, $userId, $environmentId);

        if ($pointer !== null) {
            $activity->content_type = $pointer[0];
            $activity->content_id = $pointer[1];
            $activity->save();
        }

        return $activity->fresh();
    }

    /**
     * @return array{0: class-string, 1: int}|null
     */
    private function copyContent(
        string $type,
        Activity $source,
        Activity $target,
        int $userId,
        ?int $environmentId,
    ): ?array {
        $contentType = is_string($source->content_type) && class_exists($source->content_type)
            ? $source->content_type
            : null;

        $copies = $contentType !== null && $source->content_id
            ? $this->copyByConcreteClass($contentType, $source, $target, $userId, $environmentId)
            : [];

        if ($copies === []) {
            $copies = match ($type) {
                'text' => $this->copyText($source, $target, $userId),
                'video' => $this->copyVideos($source, $target, $userId),
                'quiz', 'assessment' => $this->copyQuiz($source, $target, $userId),
                'lesson' => $this->copyLesson($source, $target, $userId),
                'assignment' => $this->copyAssignment($source, $target, $userId),
                'documentation', 'document' => $this->copyDocumentation($source, $target, $userId, $environmentId),
                'feedback' => $this->copyFeedback($source, $target, $userId, $environmentId),
                'event' => $this->copyEvent($source, $userId),
                'certificate' => $this->copyCertificate($source, $target, $userId),
                'webinar' => $this->copyWebinar($source, $target, $userId, $environmentId),
                'video_hand_in' => $this->copyVideoHandIn($source, $target, $userId),
                default => [],
            };
        }

        if ($copies === []) {
            return null;
        }

        // Mirror the source polymorphic pointer when it survives the copy.
        if ($contentType !== null && $source->content_id) {
            foreach ($copies as [$class, $oldId, $newId]) {
                if ($class === $contentType && (int) $oldId === (int) $source->content_id) {
                    return [$class, $newId];
                }
            }
        }

        // An event content row has no activity_id, so the pointer is its only
        // link to the activity and must be rebuilt even when the source row
        // lacked one.
        if ($type === 'event') {
            return [$copies[0][0], $copies[0][2]];
        }

        return null;
    }

    /**
     * @return array<int, array{0: class-string, 1: int, 2: int}>
     */
    private function copyByConcreteClass(
        string $class,
        Activity $source,
        Activity $target,
        int $userId,
        ?int $environmentId,
    ): array {
        return match ($class) {
            TextContent::class => $this->copyText($source, $target, $userId),
            VideoContent::class => $this->copyVideos($source, $target, $userId),
            QuizContent::class => $this->copyQuiz($source, $target, $userId),
            LessonContent::class => $this->copyLesson($source, $target, $userId),
            AssignmentContent::class => $this->copyAssignment($source, $target, $userId),
            DocumentationContent::class => $this->copyDocumentation($source, $target, $userId, $environmentId),
            FeedbackContent::class => $this->copyFeedback($source, $target, $userId, $environmentId),
            EventContent::class => $this->copyEvent($source, $userId),
            CertificateContent::class => $this->copyCertificate($source, $target, $userId),
            WebinarContent::class => $this->copyWebinar($source, $target, $userId, $environmentId),
            VideoHandInContent::class => $this->copyVideoHandIn($source, $target, $userId),
            default => [],
        };
    }

    /**
     * @return array<int, array{0: class-string, 1: int, 2: int}>
     */
    private function copyText(Activity $source, Activity $target, int $userId): array
    {
        $copies = [];

        foreach (TextContent::query()->where('activity_id', $source->id)->get() as $content) {
            /** @var TextContent $copy */
            $copy = $this->replicateRow($content, [
                'activity_id' => $target->id,
                'created_by' => $userId,
            ]);

            $copies[] = [TextContent::class, (int) $content->id, (int) $copy->id];
        }

        return $copies;
    }

    /**
     * A video activity can hold several video rows; copy all of them.
     *
     * @return array<int, array{0: class-string, 1: int, 2: int}>
     */
    private function copyVideos(Activity $source, Activity $target, int $userId): array
    {
        $copies = [];

        $videos = VideoContent::query()
            ->where('activity_id', $source->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        foreach ($videos as $video) {
            /** @var VideoContent $copy */
            $copy = $this->replicateRow($video, [
                'activity_id' => $target->id,
                'created_by' => $userId,
            ]);

            $copies[] = [VideoContent::class, (int) $video->id, (int) $copy->id];
        }

        return $copies;
    }

    /**
     * @return array<int, array{0: class-string, 1: int, 2: int}>
     */
    private function copyQuiz(Activity $source, Activity $target, int $userId): array
    {
        $quiz = QuizContent::query()->where('activity_id', $source->id)->first();

        if (! $quiz) {
            return [];
        }

        /** @var QuizContent $newQuiz */
        $newQuiz = $this->replicateRow($quiz, [
            'activity_id' => $target->id,
            'created_by' => $userId,
        ]);

        $pivotRows = DB::table('activity_quiz_questions')
            ->where('quiz_content_id', $quiz->id)
            ->orderBy('order')
            ->get();

        $directQuestions = QuizQuestion::query()
            ->where('quiz_content_id', $quiz->id)
            ->get();

        $pivotQuestions = QuizQuestion::query()
            ->whereIn('id', $pivotRows->pluck('quiz_question_id')->all())
            ->get();

        $questions = $directQuestions->concat($pivotQuestions)->unique('id')->sortBy('order');
        $questionMap = [];

        foreach ($questions as $question) {
            /** @var QuizQuestion $newQuestion */
            $newQuestion = $this->replicateRow($question, [
                'quiz_content_id' => $newQuiz->id,
                'created_by' => $userId,
            ]);

            $questionMap[(int) $question->id] = (int) $newQuestion->id;

            foreach ($question->options()->get() as $option) {
                $this->replicateRow($option, ['quiz_question_id' => $newQuestion->id]);
            }
        }

        foreach ($pivotRows as $row) {
            $newQuestionId = $questionMap[(int) $row->quiz_question_id] ?? null;

            if ($newQuestionId === null) {
                continue;
            }

            DB::table('activity_quiz_questions')->insert([
                'quiz_content_id' => $newQuiz->id,
                'quiz_question_id' => $newQuestionId,
                'order' => (int) $row->order,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [[QuizContent::class, (int) $quiz->id, (int) $newQuiz->id]];
    }

    /**
     * @return array<int, array{0: class-string, 1: int, 2: int}>
     */
    private function copyLesson(Activity $source, Activity $target, int $userId): array
    {
        $lesson = LessonContent::query()->where('activity_id', $source->id)->first();

        if (! $lesson) {
            return [];
        }

        /** @var LessonContent $newLesson */
        $newLesson = $this->replicateRow($lesson, [
            'activity_id' => $target->id,
            'created_by' => $userId,
        ]);

        $partMap = [];

        foreach (LessonContentPart::query()->where('lesson_content_id', $lesson->id)->orderBy('order')->get() as $part) {
            /** @var LessonContentPart $newPart */
            $newPart = $this->replicateRow($part, [
                'lesson_content_id' => $newLesson->id,
                'created_by' => $userId,
            ]);

            $partMap[(int) $part->id] = (int) $newPart->id;
        }

        $questions = LessonQuestion::query()
            ->where('lesson_content_id', $lesson->id)
            ->orderBy('order')
            ->get();

        foreach ($questions as $question) {
            /** @var LessonQuestion $newQuestion */
            $newQuestion = $this->replicateRow($question, [
                'lesson_content_id' => $newLesson->id,
                'content_part_id' => $question->content_part_id
                    ? ($partMap[(int) $question->content_part_id] ?? null)
                    : null,
                'created_by' => $userId,
            ]);

            foreach ($question->options()->get() as $option) {
                $this->replicateRow($option, ['lesson_question_id' => $newQuestion->id]);
            }
        }

        return [[LessonContent::class, (int) $lesson->id, (int) $newLesson->id]];
    }

    /**
     * @return array<int, array{0: class-string, 1: int, 2: int}>
     */
    private function copyAssignment(Activity $source, Activity $target, int $userId): array
    {
        $assignment = AssignmentContent::query()->where('activity_id', $source->id)->first();

        if (! $assignment) {
            return [];
        }

        /** @var AssignmentContent $newAssignment */
        $newAssignment = $this->replicateRow($assignment, [
            'activity_id' => $target->id,
            'created_by' => $userId,
        ]);

        foreach (AssignmentCriterion::query()->where('assignment_content_id', $assignment->id)->orderBy('order')->get() as $criterion) {
            $this->replicateRow($criterion, ['assignment_content_id' => $newAssignment->id]);
        }

        return [[AssignmentContent::class, (int) $assignment->id, (int) $newAssignment->id]];
    }

    /**
     * Documentation content carries attachment rows whose bytes may live in
     * the database. The copy re-points them at the new content and rewrites
     * the JSON snapshot the API exposes.
     *
     * @return array<int, array{0: class-string, 1: int, 2: int}>
     */
    private function copyDocumentation(
        Activity $source,
        Activity $target,
        int $userId,
        ?int $environmentId,
    ): array {
        $documentation = DocumentationContent::query()->where('activity_id', $source->id)->first();

        if (! $documentation) {
            return [];
        }

        /** @var DocumentationContent $newDocumentation */
        $newDocumentation = $this->replicateRow($documentation, [
            'activity_id' => $target->id,
            'created_by' => $userId,
        ]);

        $presented = [];

        foreach ($documentation->attachments()->get() as $attachment) {
            /** @var DocumentationAttachment $newAttachment */
            $newAttachment = $this->replicateRow($attachment, [
                'documentation_content_id' => $newDocumentation->id,
                'uuid' => (string) Str::uuid(),
                'environment_id' => $environmentId ?? $attachment->environment_id,
                'created_by' => $userId,
                'last_accessed_at' => null,
            ]);

            $this->copyDocumentBlob($attachment, $newAttachment);

            $presented[] = $this->documents->present($newAttachment);
        }

        if ($presented !== []) {
            $newDocumentation->attachments = $presented;
            $newDocumentation->save();
        }

        return [[DocumentationContent::class, (int) $documentation->id, (int) $newDocumentation->id]];
    }

    private function copyDocumentBlob(DocumentationAttachment $source, DocumentationAttachment $target): void
    {
        if ((string) $source->storage !== 'database') {
            return;
        }

        $blob = DocumentBlob::query()
            ->where('documentation_attachment_id', $source->id)
            ->first();

        if (! $blob) {
            return;
        }

        DocumentBlob::create([
            'documentation_attachment_id' => $target->id,
            'payload' => $blob->payload,
            'mime_type' => $blob->mime_type,
        ]);
    }

    /**
     * @return array<int, array{0: class-string, 1: int, 2: int}>
     */
    private function copyFeedback(
        Activity $source,
        Activity $target,
        int $userId,
        ?int $environmentId,
    ): array {
        $feedback = FeedbackContent::query()->where('activity_id', $source->id)->first();

        if (! $feedback) {
            return [];
        }

        /** @var FeedbackContent $newFeedback */
        $newFeedback = $this->replicateRow($feedback, [
            'activity_id' => $target->id,
            'environment_id' => $environmentId ?? $feedback->environment_id,
            'created_by' => $userId,
        ]);

        $questions = FeedbackQuestion::query()
            ->where('feedback_content_id', $feedback->id)
            ->orderBy('order')
            ->get();

        foreach ($questions as $question) {
            /** @var FeedbackQuestion $newQuestion */
            $newQuestion = $this->replicateRow($question, [
                'feedback_content_id' => $newFeedback->id,
                'environment_id' => $environmentId ?? $question->environment_id,
                'created_by' => $userId,
            ]);

            foreach ($question->questionOptions()->get() as $option) {
                $this->replicateRow($option, [
                    'feedback_question_id' => $newQuestion->id,
                    'created_by' => $userId,
                ]);
            }
        }

        return [[FeedbackContent::class, (int) $feedback->id, (int) $newFeedback->id]];
    }

    /**
     * Event content has no activity_id; it is linked through the activity's
     * polymorphic pointer. Sessions are copied, registrations never are.
     *
     * @return array<int, array{0: class-string, 1: int, 2: int}>
     */
    private function copyEvent(Activity $source, int $userId): array
    {
        $event = $source->content_id ? EventContent::find((int) $source->content_id) : null;

        if (! $event) {
            return [];
        }

        /** @var EventContent $newEvent */
        $newEvent = $this->replicateRow($event, ['created_by' => $userId]);

        foreach (EventSession::query()->where('event_content_id', $event->id)->orderBy('start_time')->get() as $session) {
            $this->replicateRow($session, [
                'event_content_id' => $newEvent->id,
                'created_by' => $userId,
            ]);
        }

        return [[EventContent::class, (int) $event->id, (int) $newEvent->id]];
    }

    /**
     * @return array<int, array{0: class-string, 1: int, 2: int}>
     */
    private function copyCertificate(Activity $source, Activity $target, int $userId): array
    {
        $certificate = CertificateContent::query()->where('activity_id', $source->id)->first();

        if (! $certificate) {
            return [];
        }

        /** @var CertificateContent $newCertificate */
        $newCertificate = $this->replicateRow($certificate, [
            'activity_id' => $target->id,
            'created_by' => $userId,
        ]);

        return [[CertificateContent::class, (int) $certificate->id, (int) $newCertificate->id]];
    }

    /**
     * @return array<int, array{0: class-string, 1: int, 2: int}>
     */
    private function copyWebinar(
        Activity $source,
        Activity $target,
        int $userId,
        ?int $environmentId,
    ): array {
        $webinar = WebinarContent::query()->where('activity_id', $source->id)->first();

        if (! $webinar) {
            return [];
        }

        $overrides = [
            'activity_id' => $target->id,
            'created_by' => $userId,
        ];

        if ($webinar->live_session_id) {
            $session = LiveSession::find($webinar->live_session_id);

            if ($session) {
                /** @var LiveSession $newSession */
                $newSession = $this->replicateRow($session, [
                    'environment_id' => $environmentId ?? $session->environment_id,
                    'created_by' => $userId,
                    'course_id' => null,
                    'room_name' => null,
                    'status' => 'scheduled',
                    'started_at' => null,
                    'ended_at' => null,
                ]);

                $overrides['live_session_id'] = $newSession->id;
            }
        }

        /** @var WebinarContent $newWebinar */
        $newWebinar = $this->replicateRow($webinar, $overrides);

        return [[WebinarContent::class, (int) $webinar->id, (int) $newWebinar->id]];
    }

    /**
     * @return array<int, array{0: class-string, 1: int, 2: int}>
     */
    private function copyVideoHandIn(Activity $source, Activity $target, int $userId): array
    {
        $handIn = VideoHandInContent::query()->where('activity_id', $source->id)->first();

        if (! $handIn) {
            return [];
        }

        /** @var VideoHandInContent $newHandIn */
        $newHandIn = $this->replicateRow($handIn, ['activity_id' => $target->id]);

        return [[VideoHandInContent::class, (int) $handIn->id, (int) $newHandIn->id]];
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function replicateRow(Model $model, array $overrides): Model
    {
        /** @var Model $copy */
        $copy = $model->replicate(self::EXCLUDED);

        foreach ($overrides as $key => $value) {
            $copy->{$key} = $value;
        }

        $copy->save();

        return $copy;
    }

    private function copyTitle(string $title): string
    {
        $trimmed = trim($title);

        return ($trimmed === '' ? 'Template' : $trimmed).' (Copy)';
    }
}
