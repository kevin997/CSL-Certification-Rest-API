<?php

namespace Tests\Feature\Templates;

use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\AssignmentContent;
use App\Models\AssignmentCriterion;
use App\Models\Block;
use App\Models\CertificateContent;
use App\Models\DocumentationAttachment;
use App\Models\DocumentationContent;
use App\Models\DocumentBlob;
use App\Models\Environment;
use App\Models\EventContent;
use App\Models\EventRegistration;
use App\Models\EventSession;
use App\Models\FeedbackContent;
use App\Models\FeedbackQuestion;
use App\Models\FeedbackQuestionOption;
use App\Models\LessonContent;
use App\Models\LessonContentPart;
use App\Models\LessonDiscussion;
use App\Models\LessonQuestion;
use App\Models\LessonQuestionOption;
use App\Models\LiveSession;
use App\Models\QuizContent;
use App\Models\QuizQuestion;
use App\Models\QuizQuestionOption;
use App\Models\Template;
use App\Models\TextContent;
use App\Models\User;
use App\Models\VideoContent;
use App\Models\VideoHandInContent;
use App\Models\WebinarContent;
use App\Services\Templates\TemplateContentCloner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Duplicating a template (or block/activity) and importing blocks/activities
 * from another template must copy the content rows and never learner data.
 */
class TemplateContentDuplicationTest extends TestCase
{
    use RefreshDatabase;

    private Environment $environment;

    private User $owner;

    private string $domain = 'academy-templates.test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create([
            'role' => UserRole::INDIVIDUAL_TEACHER->value,
        ]);

        $this->environment = Environment::factory()->create([
            'owner_id' => $this->owner->id,
            'primary_domain' => $this->domain,
            'domain_verified_at' => now(),
        ]);
    }

    public function test_duplicating_a_template_copies_every_content_type_without_learner_data(): void
    {
        $source = $this->makeTemplate('Media Buying');
        $block = $this->makeBlock($source, 'Week 1');

        // Text
        $textActivity = $this->makeActivity($block, 'text', 'Intro text');
        TextContent::create([
            'activity_id' => $textActivity->id,
            'title' => 'Intro text',
            'content' => 'Hello',
            'format' => 'markdown',
            'created_by' => $this->owner->id,
        ]);

        // Video (a video activity can hold several rows)
        $videoActivity = $this->makeActivity($block, 'video', 'Videos');
        foreach ([1, 2] as $sortOrder) {
            VideoContent::create([
                'activity_id' => $videoActivity->id,
                'title' => 'Video '.$sortOrder,
                'video_url' => 'https://example.com/video-'.$sortOrder.'.mp4',
                'video_type' => 'mp4',
                'sort_order' => $sortOrder,
                'created_by' => $this->owner->id,
            ]);
        }

        // Quiz with a question, option and pivot link
        $quizActivity = $this->makeActivity($block, 'quiz', 'Quiz');
        $quizContent = QuizContent::create([
            'activity_id' => $quizActivity->id,
            'title' => 'Quiz',
            'created_by' => $this->owner->id,
        ]);
        $question = QuizQuestion::create([
            'quiz_content_id' => $quizContent->id,
            'question' => 'Pick one?',
            'question_type' => 'multiple_choice',
            'order' => 1,
            'created_by' => $this->owner->id,
        ]);
        QuizQuestionOption::create([
            'quiz_question_id' => $question->id,
            'option_text' => 'Yes',
            'order' => 1,
        ]);
        DB::table('activity_quiz_questions')->insert([
            'quiz_content_id' => $quizContent->id,
            'quiz_question_id' => $question->id,
            'order' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Lesson with a part, a question and learner/discussion data
        $lessonActivity = $this->makeActivity($block, 'lesson', 'Lesson');
        $lessonContent = LessonContent::create([
            'activity_id' => $lessonActivity->id,
            'title' => 'Lesson',
            'format' => 'markdown',
            'created_by' => $this->owner->id,
        ]);
        $part = LessonContentPart::create([
            'lesson_content_id' => $lessonContent->id,
            'content_type' => 'wysiwyg',
            'content' => 'Part body',
            'order' => 1,
            'created_by' => $this->owner->id,
        ]);
        $lessonQuestion = LessonQuestion::create([
            'lesson_content_id' => $lessonContent->id,
            'content_part_id' => $part->id,
            'question' => 'Understand?',
            'question_type' => 'multiple_choice',
            'order' => 1,
            'created_by' => $this->owner->id,
        ]);
        LessonQuestionOption::create([
            'lesson_question_id' => $lessonQuestion->id,
            'option_text' => 'Yes',
            'order' => 1,
        ]);
        LessonDiscussion::create([
            'lesson_content_id' => $lessonContent->id,
            'user_id' => $this->owner->id,
            'message' => 'A learner comment',
        ]);

        // Assignment with a rubric criterion
        $assignmentActivity = $this->makeActivity($block, 'assignment', 'Assignment');
        $assignmentContent = AssignmentContent::create([
            'activity_id' => $assignmentActivity->id,
            'instructions' => 'Submit your work',
            'created_by' => $this->owner->id,
        ]);
        AssignmentCriterion::create([
            'assignment_content_id' => $assignmentContent->id,
            'title' => 'Clarity',
            'points' => 10,
            'order' => 1,
        ]);
        $assignmentActivity->update([
            'content_type' => AssignmentContent::class,
            'content_id' => $assignmentContent->id,
        ]);

        // Documentation with a database-stored attachment
        $documentationActivity = $this->makeActivity($block, 'documentation', 'Docs');
        $documentationContent = DocumentationContent::create([
            'activity_id' => $documentationActivity->id,
            'title' => 'Docs',
            'content' => 'Read me',
            'format' => 'markdown',
            'created_by' => $this->owner->id,
        ]);
        $attachment = DocumentationAttachment::create([
            'documentation_content_id' => $documentationContent->id,
            'environment_id' => $this->environment->id,
            'storage' => 'database',
            'file_path' => 'stored',
            'file_name' => 'handbook.pdf',
            'file_type' => 'application/pdf',
            'file_size' => 9,
            'created_by' => $this->owner->id,
        ]);
        DocumentBlob::create([
            'documentation_attachment_id' => $attachment->id,
            'payload' => base64_encode('%PDF-test'),
            'mime_type' => 'application/pdf',
        ]);

        // Feedback with a question and an option row
        $feedbackActivity = $this->makeActivity($block, 'feedback', 'Feedback');
        $feedbackContent = FeedbackContent::create([
            'activity_id' => $feedbackActivity->id,
            'environment_id' => $this->environment->id,
            'title' => 'Feedback',
            'feedback_type' => 'survey',
            'created_by' => $this->owner->id,
        ]);
        $feedbackQuestion = FeedbackQuestion::create([
            'feedback_content_id' => $feedbackContent->id,
            'environment_id' => $this->environment->id,
            'question_text' => 'How was it?',
            'question_type' => 'rating',
            'order' => 1,
            'created_by' => $this->owner->id,
        ]);
        FeedbackQuestionOption::create([
            'feedback_question_id' => $feedbackQuestion->id,
            'option_text' => 'Good',
            'order' => 1,
            'created_by' => $this->owner->id,
        ]);

        // Event linked through the polymorphic pointer, with a session and a
        // learner registration that must not be copied.
        $eventActivity = $this->makeActivity($block, 'event', 'Event');
        $eventContent = EventContent::create([
            'title' => 'Live event',
            'event_type' => 'online',
            'start_date' => now(),
            'end_date' => now()->addHour(),
            'timezone' => 'UTC',
            'created_by' => $this->owner->id,
        ]);
        $eventActivity->update([
            'content_type' => EventContent::class,
            'content_id' => $eventContent->id,
        ]);
        EventSession::create([
            'event_content_id' => $eventContent->id,
            'title' => 'Session 1',
            'start_time' => now(),
            'created_by' => $this->owner->id,
        ]);
        EventRegistration::create([
            'event_content_id' => $eventContent->id,
            'user_id' => $this->owner->id,
            'status' => 'registered',
        ]);

        // Certificate
        $certificateActivity = $this->makeActivity($block, 'certificate', 'Certificate');
        CertificateContent::create([
            'activity_id' => $certificateActivity->id,
            'title' => 'Certificate',
            'created_by' => $this->owner->id,
        ]);

        // Webinar with its own live session
        $webinarActivity = $this->makeActivity($block, 'webinar', 'Webinar');
        $liveSession = LiveSession::create([
            'environment_id' => $this->environment->id,
            'created_by' => $this->owner->id,
            'title' => 'Webinar room',
            'scheduled_at' => now()->addDay(),
            'duration_minutes' => 60,
            'max_participants' => 100,
        ]);
        WebinarContent::create([
            'activity_id' => $webinarActivity->id,
            'live_session_id' => $liveSession->id,
            'title' => 'Webinar',
            'duration_minutes' => 60,
            'max_participants' => 100,
            'allow_recording' => true,
            'enable_chat' => true,
            'enable_qa' => true,
            'enable_reactions' => true,
            'mute_participants_on_join' => false,
            'disable_participant_video' => false,
            'access_type' => 'enrolled',
            'status' => 'scheduled',
            'created_by' => $this->owner->id,
        ]);

        // Video hand-in
        $handInActivity = $this->makeActivity($block, 'video_hand_in', 'Hand in');
        VideoHandInContent::create([
            'activity_id' => $handInActivity->id,
            'title' => 'Hand in',
            'instructions' => 'Record a video',
            'instructions_format' => 'markdown',
            'allow_late_submissions' => false,
        ]);

        $copy = app(TemplateContentCloner::class)->duplicateTemplate($source, $this->owner->id);

        $this->assertSame('Media Buying (Copy)', $copy->title);
        $this->assertSame($this->owner->id, $copy->created_by);
        $this->assertSame($this->environment->id, $copy->environment_id);
        $this->assertFalse((bool) $copy->is_public);
        $this->assertSame('draft', $copy->status);

        $newBlock = Block::withoutGlobalScopes()->where('template_id', $copy->id)->firstOrFail();
        $this->assertSame('Week 1', $newBlock->title);

        $activities = Activity::withoutGlobalScopes()
            ->where('block_id', $newBlock->id)
            ->orderBy('order')
            ->get()
            ->keyBy('title');

        $this->assertCount(11, $activities);

        // Text
        $this->assertSame(1, TextContent::where('activity_id', $activities['Intro text']->id)->count());

        // Video: both rows copied with their order
        $newVideos = VideoContent::where('activity_id', $activities['Videos']->id)->orderBy('sort_order')->get();
        $this->assertCount(2, $newVideos);
        $this->assertSame([1, 2], $newVideos->pluck('sort_order')->all());

        // Quiz: content, question, option and pivot
        $newQuiz = QuizContent::where('activity_id', $activities['Quiz']->id)->firstOrFail();
        $newQuizQuestion = QuizQuestion::where('quiz_content_id', $newQuiz->id)->firstOrFail();
        $this->assertSame('Pick one?', $newQuizQuestion->question);
        $this->assertSame(1, QuizQuestionOption::where('quiz_question_id', $newQuizQuestion->id)->count());
        $this->assertSame(1, DB::table('activity_quiz_questions')->where('quiz_content_id', $newQuiz->id)->count());

        // Lesson: parts, questions, options copied; discussions not
        $newLesson = LessonContent::where('activity_id', $activities['Lesson']->id)->firstOrFail();
        $newPart = LessonContentPart::where('lesson_content_id', $newLesson->id)->firstOrFail();
        $newLessonQuestion = LessonQuestion::where('lesson_content_id', $newLesson->id)->firstOrFail();
        $this->assertSame($newPart->id, $newLessonQuestion->content_part_id);
        $this->assertSame(1, LessonQuestionOption::where('lesson_question_id', $newLessonQuestion->id)->count());
        $this->assertSame(0, LessonDiscussion::where('lesson_content_id', $newLesson->id)->count());

        // Assignment: content pointer mirrored, criterion copied
        $newAssignment = AssignmentContent::where('activity_id', $activities['Assignment']->id)->firstOrFail();
        $this->assertSame(AssignmentContent::class, $activities['Assignment']->content_type);
        $this->assertSame($newAssignment->id, (int) $activities['Assignment']->content_id);
        $this->assertSame(1, AssignmentCriterion::where('assignment_content_id', $newAssignment->id)->count());

        // Documentation: attachment re-pointed, new uuid, blob copied
        $newDocumentation = DocumentationContent::where('activity_id', $activities['Docs']->id)->firstOrFail();
        $newAttachment = DocumentationAttachment::where('documentation_content_id', $newDocumentation->id)->firstOrFail();
        $this->assertNotSame($attachment->uuid, $newAttachment->uuid);
        $this->assertSame('handbook.pdf', $newAttachment->file_name);
        $this->assertEquals(
            DocumentBlob::where('documentation_attachment_id', $attachment->id)->value('payload'),
            DocumentBlob::where('documentation_attachment_id', $newAttachment->id)->value('payload'),
        );
        $presented = collect($newDocumentation->attachments);
        $this->assertTrue($presented->contains(fn ($item) => ($item['uuid'] ?? null) === $newAttachment->uuid));

        // Feedback: content, question, option
        $newFeedback = FeedbackContent::where('activity_id', $activities['Feedback']->id)->firstOrFail();
        $newFeedbackQuestion = FeedbackQuestion::where('feedback_content_id', $newFeedback->id)->firstOrFail();
        $this->assertSame(1, FeedbackQuestionOption::where('feedback_question_id', $newFeedbackQuestion->id)->count());

        // Event: content pointer rebuilt, session copied, registration not
        $newEvent = EventContent::find((int) $activities['Event']->content_id);
        $this->assertNotNull($newEvent);
        $this->assertSame(EventContent::class, $activities['Event']->content_type);
        $this->assertSame(1, EventSession::where('event_content_id', $newEvent->id)->count());
        $this->assertSame(0, EventRegistration::where('event_content_id', $newEvent->id)->count());

        // Certificate
        $this->assertSame(1, CertificateContent::where('activity_id', $activities['Certificate']->id)->count());

        // Webinar: content copied with a fresh live session (same template can
        // be used in two courses, so they must not share a room).
        $newWebinar = WebinarContent::where('activity_id', $activities['Webinar']->id)->firstOrFail();
        $newLiveSession = LiveSession::find($newWebinar->live_session_id);
        $this->assertNotNull($newLiveSession);
        $this->assertNotSame($liveSession->id, $newLiveSession->id);
        $this->assertNotSame($liveSession->room_name, $newLiveSession->room_name);

        // Video hand-in
        $this->assertSame(1, VideoHandInContent::where('activity_id', $activities['Hand in']->id)->count());

        // Source rows are untouched
        $this->assertSame($this->environment->id, $source->fresh()->environment_id);
        $this->assertCount(11, Activity::withoutGlobalScopes()->where('block_id', $block->id)->get());
    }

    public function test_duplicating_a_template_through_the_api_copies_content(): void
    {
        $source = $this->makeTemplate('API Course');
        $block = $this->makeBlock($source);
        $activity = $this->makeActivity($block, 'text', 'Reading');
        TextContent::create([
            'activity_id' => $activity->id,
            'title' => 'Reading',
            'content' => 'Text body',
            'format' => 'markdown',
            'created_by' => $this->owner->id,
        ]);

        $response = $this->asOwner()->postJson("/api/templates/{$source->id}/duplicate");

        $response->assertCreated();
        $newTemplateId = $response->json('data.id');
        $this->assertNotSame($source->id, $newTemplateId);
        $this->assertSame('API Course (Copy)', $response->json('data.title'));

        $newActivity = Activity::withoutGlobalScopes()
            ->whereIn('block_id', Block::withoutGlobalScopes()->where('template_id', $newTemplateId)->pluck('id'))
            ->firstOrFail();

        $this->assertSame(1, TextContent::where('activity_id', $newActivity->id)->count());
        $this->assertSame('Text body', TextContent::where('activity_id', $newActivity->id)->value('content'));
    }

    public function test_duplicating_a_block_copies_its_activities_and_content(): void
    {
        $template = $this->makeTemplate();
        $block = $this->makeBlock($template);
        $activity = $this->makeActivity($block, 'text', 'Reading');
        TextContent::create([
            'activity_id' => $activity->id,
            'title' => 'Reading',
            'content' => 'Body',
            'format' => 'markdown',
            'created_by' => $this->owner->id,
        ]);

        $response = $this->asOwner()->postJson("/api/blocks/{$block->id}/duplicate");

        $response->assertCreated();
        $newBlockId = $response->json('data.id');
        $this->assertNotSame($block->id, $newBlockId);
        $this->assertSame('Week 1 (Copy)', $response->json('data.title'));

        $newActivity = Activity::withoutGlobalScopes()->where('block_id', $newBlockId)->firstOrFail();
        $this->assertSame(1, TextContent::where('activity_id', $newActivity->id)->count());
    }

    public function test_duplicating_an_activity_copies_its_content(): void
    {
        $template = $this->makeTemplate();
        $block = $this->makeBlock($template);
        $activity = $this->makeActivity($block, 'text', 'Reading');
        TextContent::create([
            'activity_id' => $activity->id,
            'title' => 'Reading',
            'content' => 'Body',
            'format' => 'markdown',
            'created_by' => $this->owner->id,
        ]);

        $response = $this->asOwner()->postJson("/api/blocks/activities/{$activity->id}/duplicate");

        $response->assertCreated();
        $newActivityId = $response->json('data.id');
        $this->assertNotSame($activity->id, $newActivityId);
        $this->assertSame('Reading (Copy)', $response->json('data.title'));
        $this->assertSame('Body', TextContent::where('activity_id', $newActivityId)->value('content'));
    }

    public function test_importing_blocks_with_content_copies_every_activity_and_its_content(): void
    {
        [$source, $target] = $this->sourceAndTarget();
        $sourceBlock = $this->makeBlock($source, 'Imported week', 1);
        $text = $this->makeActivity($sourceBlock, 'text', 'Imported reading');
        TextContent::create([
            'activity_id' => $text->id,
            'title' => 'Imported reading',
            'content' => 'Imported body',
            'format' => 'markdown',
            'created_by' => $this->owner->id,
        ]);
        $participants = $this->makeActivity($sourceBlock, 'video', 'Imported video');
        VideoContent::create([
            'activity_id' => $participants->id,
            'title' => 'Video',
            'video_url' => 'https://example.com/v.mp4',
            'video_type' => 'mp4',
            'created_by' => $this->owner->id,
        ]);

        $response = $this->asOwner()->postJson("/api/templates/{$target->id}/import-blocks", [
            'source_template_id' => $source->id,
            'block_ids' => [$sourceBlock->id],
            'include_content' => true,
        ]);

        $response->assertCreated();
        $newBlockId = $response->json('data.0.id');
        $newActivities = Activity::withoutGlobalScopes()->where('block_id', $newBlockId)->get();

        $this->assertCount(2, $newActivities);
        $this->assertSame('Imported body', TextContent::where('activity_id', $newActivities->firstWhere('title', 'Imported reading')->id)->value('content'));
        $this->assertSame(1, VideoContent::where('activity_id', $newActivities->firstWhere('title', 'Imported video')->id)->count());
    }

    public function test_importing_blocks_without_content_creates_empty_activities(): void
    {
        [$source, $target] = $this->sourceAndTarget();
        $sourceBlock = $this->makeBlock($source, 'Shell week', 1);
        $activity = $this->makeActivity($sourceBlock, 'text', 'Shell reading');
        TextContent::create([
            'activity_id' => $activity->id,
            'title' => 'Shell reading',
            'content' => 'Should not travel',
            'format' => 'markdown',
            'created_by' => $this->owner->id,
        ]);

        $response = $this->asOwner()->postJson("/api/templates/{$target->id}/import-blocks", [
            'source_template_id' => $source->id,
            'block_ids' => [$sourceBlock->id],
            'include_content' => false,
        ]);

        $response->assertCreated();
        $newBlockId = $response->json('data.0.id');
        $newActivity = Activity::withoutGlobalScopes()->where('block_id', $newBlockId)->firstOrFail();

        $this->assertSame('Shell reading', $newActivity->title);
        $this->assertSame(0, TextContent::where('activity_id', $newActivity->id)->count());
        $this->assertNull($newActivity->content_id);
    }

    public function test_importing_activities_into_a_block_with_content(): void
    {
        [$source, $target] = $this->sourceAndTarget();
        $sourceBlock = $this->makeBlock($source, 'Source week');
        $sourceActivity = $this->makeActivity($sourceBlock, 'text', 'Activity to import');
        TextContent::create([
            'activity_id' => $sourceActivity->id,
            'title' => 'Activity to import',
            'content' => 'Copied body',
            'format' => 'markdown',
            'created_by' => $this->owner->id,
        ]);

        $targetBlock = $this->makeBlock($target, 'Destination week');

        $response = $this->asOwner()->postJson("/api/blocks/{$targetBlock->id}/import-activities", [
            'source_template_id' => $source->id,
            'activity_ids' => [$sourceActivity->id],
            'include_content' => true,
        ]);

        $response->assertCreated();
        $newActivityId = $response->json('data.0.id');
        $this->assertSame('Copied body', TextContent::where('activity_id', $newActivityId)->value('content'));
        $this->assertSame($targetBlock->id, Activity::withoutGlobalScopes()->find($newActivityId)->block_id);
    }

    public function test_importing_activities_without_content(): void
    {
        [$source, $target] = $this->sourceAndTarget();
        $sourceBlock = $this->makeBlock($source, 'Source week');
        $sourceActivity = $this->makeActivity($sourceBlock, 'text', 'Shell activity');
        TextContent::create([
            'activity_id' => $sourceActivity->id,
            'title' => 'Shell activity',
            'content' => 'Not copied',
            'format' => 'markdown',
            'created_by' => $this->owner->id,
        ]);
        $targetBlock = $this->makeBlock($target, 'Destination week');

        $response = $this->asOwner()->postJson("/api/blocks/{$targetBlock->id}/import-activities", [
            'source_template_id' => $source->id,
            'activity_ids' => [$sourceActivity->id],
            'include_content' => false,
        ]);

        $response->assertCreated();
        $newActivityId = $response->json('data.0.id');
        $this->assertSame(0, TextContent::where('activity_id', $newActivityId)->count());
        $this->assertSame($targetBlock->id, Activity::withoutGlobalScopes()->find($newActivityId)->block_id);
    }

    public function test_import_rejects_a_source_template_from_another_academy(): void
    {
        [$target] = $this->sourceAndTarget();

        $otherEnvironment = Environment::factory()->create([
            'owner_id' => $this->owner->id,
            'primary_domain' => 'other-academy.test',
            'domain_verified_at' => now(),
        ]);
        $foreignTemplate = $this->makeTemplate('Foreign', $otherEnvironment);
        $foreignBlock = $this->makeBlock($foreignTemplate);

        $this->asOwner()->postJson("/api/templates/{$target->id}/import-blocks", [
            'source_template_id' => $foreignTemplate->id,
            'block_ids' => [$foreignBlock->id],
        ])->assertStatus(422);
    }

    public function test_import_rejects_a_target_template_the_user_does_not_own(): void
    {
        [$source] = $this->sourceAndTarget();
        $sourceBlock = $this->makeBlock($source, 'Week');

        $stranger = User::factory()->create([
            'role' => UserRole::INDIVIDUAL_TEACHER->value,
        ]);
        $strangerTemplate = $this->makeTemplate('Stranger', null, $stranger);

        $this->actingAs($this->owner, 'sanctum')
            ->withHeaders($this->headers())
            ->postJson("/api/templates/{$strangerTemplate->id}/import-blocks", [
                'source_template_id' => $source->id,
                'block_ids' => [$sourceBlock->id],
            ])
            ->assertForbidden();
    }

    public function test_import_rejects_the_same_template_as_source_and_target(): void
    {
        $template = $this->makeTemplate();
        $block = $this->makeBlock($template);

        $this->asOwner()->postJson("/api/templates/{$template->id}/import-blocks", [
            'source_template_id' => $template->id,
            'block_ids' => [$block->id],
        ])->assertStatus(422);
    }

    public function test_import_validates_the_payload(): void
    {
        [$source, $target] = $this->sourceAndTarget();
        $block = $this->makeBlock($source);

        $this->asOwner()->postJson("/api/templates/{$target->id}/import-blocks", [])
            ->assertStatus(422);

        $this->asOwner()->postJson("/api/templates/{$target->id}/import-blocks", [
            'source_template_id' => $source->id,
            'block_ids' => [],
        ])->assertStatus(422);

        $this->asOwner()->postJson("/api/templates/{$target->id}/import-blocks", [
            'source_template_id' => $source->id,
            'block_ids' => [$block->id + 9999],
        ])->assertStatus(422);
    }

    private function sourceAndTarget(): array
    {
        $source = $this->makeTemplate('Source course');
        $target = $this->makeTemplate('Target course');

        return [$source, $target];
    }

    private function makeTemplate(string $title = 'Course', ?Environment $environment = null, ?User $owner = null): Template
    {
        $environment ??= $this->environment;
        $owner ??= $this->owner;

        return Template::withoutGlobalScopes()->create([
            'title' => $title,
            'description' => 'Description',
            'status' => 'draft',
            'is_public' => false,
            'created_by' => $owner->id,
            'environment_id' => $environment->id,
        ]);
    }

    private function makeBlock(Template $template, string $title = 'Week 1', int $order = 1): Block
    {
        return Block::withoutGlobalScopes()->create([
            'title' => $title,
            'template_id' => $template->id,
            'order' => $order,
            'status' => 'active',
            'created_by' => $template->created_by,
        ]);
    }

    private function makeActivity(Block $block, string $type, string $title, int $order = 1): Activity
    {
        return Activity::withoutGlobalScopes()->create([
            'title' => $title,
            'type' => $type,
            'block_id' => $block->id,
            'order' => $order,
            'status' => 'active',
            'created_by' => $block->created_by,
        ]);
    }

    private function asOwner(): static
    {
        return $this->actingAs($this->owner, 'sanctum')->withHeaders($this->headers());
    }

    private function headers(): array
    {
        return [
            'X-Frontend-Domain' => $this->domain,
            'X-Device-Id' => Str::uuid(),
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) Chrome/120.0',
        ];
    }
}
