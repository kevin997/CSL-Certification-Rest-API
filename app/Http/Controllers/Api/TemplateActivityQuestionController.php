<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Block;
use App\Models\QuizContent;
use App\Models\QuizQuestion;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TemplateActivityQuestionController extends Controller
{
    /**
     * Get questions from all quiz activities in a template except the current activity
     *
     * @param Request $request
     * @param int $templateId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTemplateQuestions(Request $request, $templateId)
    {
        // Validate template exists and user has access
        $template = Template::findOrFail($templateId);

        // Check if user has access to this template
        if (!$template->is_public && $template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to view this template',
            ], Response::HTTP_FORBIDDEN);
        }

        // Get the current activity ID if provided (to exclude it from results)
        $currentActivityId = $request->query('current_activity_id');

        // Find all quiz activities in this template
        $quizActivities = Activity::whereHas('block', function ($query) use ($templateId) {
            $query->where('template_id', $templateId);
        })
        ->where('type', 'quiz')
        ->when($currentActivityId, function ($query, $currentActivityId) {
            // Exclude the current activity if specified
            return $query->where('id', '!=', $currentActivityId);
        })
        ->get();

        // Collect all unique questions from these activities (avoiding duplicates)
        $questionMap = []; // Track questions we've already seen

        foreach ($quizActivities as $activity) {
            $quizContent = QuizContent::where('activity_id', $activity->id)->first();

            if ($quizContent) {
                // Try to get questions via pivot table first (new approach)
                $activityQuestions = $quizContent->allQuestions();

                // If no questions via pivot, fall back to legacy direct relationship
                if ($activityQuestions->isEmpty()) {
                    $activityQuestions = QuizQuestion::where('quiz_content_id', $quizContent->id)
                        ->with('options')
                        ->get();
                } else {
                    // Load options relationship for pivot questions
                    $activityQuestions->load('options');
                }

                // Add activity info to each question and track unique questions
                foreach ($activityQuestions as $question) {
                    $questionId = $question->id;

                    // If we haven't seen this question yet, add it
                    if (!isset($questionMap[$questionId])) {
                        $questionData = $question->toArray();
                        $questionData['activity_title'] = $activity->title;
                        $questionData['activity_id'] = $activity->id;
                        $questionData['quiz_content_id'] = $quizContent->id;

                        $questionMap[$questionId] = $questionData;
                    }
                }
            }
        }

        // Convert map to array of questions
        $questions = array_values($questionMap);

        return response()->json([
            'status' => 'success',
            'data' => [
                'questions' => $questions,
                'total' => count($questions),
                'unique_count' => count($questions), // All questions are now unique
                'template' => [
                    'id' => $template->id,
                    'title' => $template->title
                ]
            ]
        ]);
    }
    
    /**
     * Import questions to a quiz activity (NEW: Using pivot table approach)
     *
     * Instead of duplicating questions, this method links existing questions
     * to the target quiz activity through the activity_quiz_questions pivot table.
     * This eliminates database bloat and enables true question sharing.
     *
     * @param Request $request
     * @param int $activityId
     * @return \Illuminate\Http\JsonResponse
     */
    public function importQuestions(Request $request, $activityId)
    {
        // Validate request
        $request->validate([
            'question_ids' => 'required|array',
            'question_ids.*' => 'integer|exists:quiz_questions,id'
        ]);

        // Check if activity exists and is a quiz type
        $activity = Activity::findOrFail($activityId);

        if ($activity->type->value !== 'quiz') {
            return response()->json([
                'status' => 'error',
                'message' => 'Activity is not a quiz type',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Find or create quiz content for this activity
        $quizContent = QuizContent::firstOrCreate(
            ['activity_id' => $activityId],
            [
                'title' => $activity->title ?? 'Quiz',
                'description' => '',
                'instructions' => 'Answer all questions to the best of your ability.',
                'instruction_format' => 'markdown',
                'passing_score' => 70,
                'randomize_questions' => false,
                'show_correct_answers' => true,
                'created_by' => Auth::id(),
            ]
        );

        // Get the questions to import
        $questionIds = $request->input('question_ids');

        // Verify all questions exist
        $sourceQuestions = QuizQuestion::whereIn('id', $questionIds)->get();

        if ($sourceQuestions->count() !== count($questionIds)) {
            return response()->json([
                'status' => 'error',
                'message' => 'One or more questions not found',
            ], Response::HTTP_NOT_FOUND);
        }

        DB::beginTransaction();

        try {
            // Get the current max order for questions in this quiz
            $maxOrder = DB::table('activity_quiz_questions')
                ->where('quiz_content_id', $quizContent->id)
                ->max('order') ?? 0;

            // Attach questions to the quiz via pivot table
            $attachData = [];
            $order = $maxOrder + 1;

            foreach ($questionIds as $questionId) {
                // Check if question is already attached to avoid duplicates
                $exists = DB::table('activity_quiz_questions')
                    ->where('quiz_content_id', $quizContent->id)
                    ->where('quiz_question_id', $questionId)
                    ->exists();

                if (!$exists) {
                    $attachData[$questionId] = [
                        'order' => $order++,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            // Attach all questions at once (no duplication!)
            if (!empty($attachData)) {
                $quizContent->questionsViaPivot()->attach($attachData);
            }

            // Get the newly attached questions with their options for the response
            $importedQuestions = QuizQuestion::whereIn('id', $questionIds)
                ->with('options')
                ->get();

            DB::commit();

            $message = count($attachData) . ' questions imported successfully';
            if (count($attachData) < count($questionIds)) {
                $skipped = count($questionIds) - count($attachData);
                $message .= " ({$skipped} already existed and were skipped)";
            }

            return response()->json([
                'status' => 'success',
                'message' => $message,
                'data' => [
                    'imported_questions' => $importedQuestions,
                    'quiz_content' => $quizContent,
                    'newly_added_count' => count($attachData),
                    'skipped_count' => count($questionIds) - count($attachData),
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to import questions: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Import questions to a quiz activity (LEGACY: Creates duplicates)
     *
     * DEPRECATED: This method is kept for backward compatibility and emergency rollback.
     * Use importQuestions() which uses the pivot table approach instead.
     *
     * @param Request $request
     * @param int $activityId
     * @return \Illuminate\Http\JsonResponse
     */
    public function importQuestionsLegacy(Request $request, $activityId)
    {
        // Validate request
        $request->validate([
            'question_ids' => 'required|array',
            'question_ids.*' => 'integer|exists:quiz_questions,id'
        ]);

        // Check if activity exists and is a quiz type
        $activity = Activity::findOrFail($activityId);

        if ($activity->type->value !== 'quiz') {
            return response()->json([
                'status' => 'error',
                'message' => 'Activity is not a quiz type',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Find or create quiz content for this activity
        $quizContent = QuizContent::firstOrCreate(
            ['activity_id' => $activityId],
            [
                'title' => $activity->title ?? 'Quiz',
                'description' => '',
                'instructions' => 'Answer all questions to the best of your ability.',
                'instruction_format' => 'markdown',
                'passing_score' => 70,
                'randomize_questions' => false,
                'show_correct_answers' => true,
                'created_by' => Auth::id(),
            ]
        );

        // Get the questions to import
        $questionIds = $request->input('question_ids');
        $sourceQuestions = QuizQuestion::whereIn('id', $questionIds)->with('options')->get();

        // Import each question
        $importedQuestions = [];

        DB::beginTransaction();

        try {
            foreach ($sourceQuestions as $sourceQuestion) {
                // Create a new question based on the source
                $newQuestion = new QuizQuestion([
                    'quiz_content_id' => $quizContent->id,
                    'title' => $sourceQuestion->title,
                    'question' => $sourceQuestion->question ?? $sourceQuestion->question_text,
                    'question_text' => $sourceQuestion->question_text,
                    'question_type' => $sourceQuestion->question_type,
                    'explanation' => $sourceQuestion->explanation,
                    'points' => $sourceQuestion->points,
                    'is_scorable' => $sourceQuestion->is_scorable,
                    'image_url' => $sourceQuestion->image_url,
                    'image_alt' => $sourceQuestion->image_alt,
                    'created_by' => Auth::id(),
                ]);

                // Copy any array fields that might be present
                if ($sourceQuestion->options) $newQuestion->options = $sourceQuestion->options;
                if ($sourceQuestion->blanks) $newQuestion->blanks = $sourceQuestion->blanks;
                if ($sourceQuestion->matrix_rows) $newQuestion->matrix_rows = $sourceQuestion->matrix_rows;
                if ($sourceQuestion->matrix_columns) $newQuestion->matrix_columns = $sourceQuestion->matrix_columns;
                if ($sourceQuestion->matrix_options) $newQuestion->matrix_options = $sourceQuestion->matrix_options;

                $newQuestion->save();

                // Special handling for questionnaire-type questions
                if ($sourceQuestion->question_type === 'questionnaire') {
                    $this->importQuestionnaireOptions($sourceQuestion, $newQuestion);
                } else if ($sourceQuestion->options()->count() > 0) {
                    foreach ($sourceQuestion->options as $option) {
                        $newQuestion->options()->create([
                            'option_text' => $option->option_text,
                            'is_correct' => $option->is_correct,
                            'feedback' => $option->feedback,
                            'order' => $option->order,
                            'created_by' => Auth::id(),
                        ]);
                    }
                }

                $importedQuestions[] = $newQuestion;
            }

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => count($importedQuestions) . ' questions imported successfully (legacy duplication method)',
                'data' => [
                    'imported_questions' => $importedQuestions,
                    'quiz_content' => $quizContent
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollback();

            return response()->json([
                'status' => 'error',
                'message' => 'Failed to import questions: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Import all options for a questionnaire-type question, including subquestion assignments and images
     *
     * @param QuizQuestion $sourceQuestion
     * @param QuizQuestion $newQuestion
     * @return void
     */
    private function importQuestionnaireOptions($sourceQuestion, $newQuestion)
    {
        // Copy all options, including answer options and subquestion assignments
        foreach ($sourceQuestion->options as $option) {
            $newQuestion->options()->create([
                'subquestion_text' => $option->subquestion_text,
                'answer_option_id' => $option->answer_option_id,
                'option_text' => $option->option_text,
                'is_correct' => $option->is_correct,
                'points' => $option->points,
                'feedback' => $option->feedback,
                'order' => $option->order,
                'image_url' => $option->image_url,
                'image_alt' => $option->image_alt,
                'created_by' => Auth::id(),
            ]);
        }
    }
}
