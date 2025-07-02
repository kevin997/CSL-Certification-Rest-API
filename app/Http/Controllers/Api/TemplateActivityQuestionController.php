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
        
        // Collect all questions from these activities
        $questions = [];
        
        foreach ($quizActivities as $activity) {
            $quizContent = QuizContent::where('activity_id', $activity->id)->first();
            
            if ($quizContent) {
                // Get questions with options
                $activityQuestions = QuizQuestion::where('quiz_content_id', $quizContent->id)
                    ->with('options')
                    ->get()
                    ->map(function ($question) use ($activity) {
                        // Add activity information to each question
                        $question->activity_title = $activity->title;
                        $question->activity_id = $activity->id;
                        return $question;
                    });
                
                $questions = array_merge($questions, $activityQuestions->toArray());
            }
        }
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'questions' => $questions,
                'total' => count($questions),
                'template' => [
                    'id' => $template->id,
                    'title' => $template->title
                ]
            ]
        ]);
    }
    
    /**
     * Import questions to a quiz activity
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
                    // Include both question and question_text fields
                    // If source has question, use it, otherwise use question_text
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
                'message' => count($importedQuestions) . ' questions imported successfully',
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
