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
use Illuminate\Support\Facades\Validator;

class QuestionController extends Controller
{
    /**
     * Get all questions for an activity
     *
     * @param int $activityId
     * @return \Illuminate\Http\Response
     */
    public function index($activityId)
    {
        $activity = Activity::findOrFail($activityId);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        // Check if user has access to this template
        if (!$template->is_public && $template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to view these questions',
            ], Response::HTTP_FORBIDDEN);
        }

        // Get quiz content
        $quizContent = QuizContent::where('activity_id', $activityId)->first();
        
        if (!$quizContent) {
            return response()->json([
                'status' => 'success',
                'data' => [],
            ]);
        }

        // If questions are stored in JSON format
        if (isset($quizContent->questions) && is_array($quizContent->questions)) {
            return response()->json([
                'status' => 'success',
                'data' => $quizContent->questions,
            ]);
        }

        // If questions are stored in related table
        $questions = QuizQuestion::where('quiz_content_id', $quizContent->id)->get();
        
        return response()->json([
            'status' => 'success',
            'data' => $questions,
        ]);
    }

    /**
     * Store a new question
     *
     * @param \Illuminate\Http\Request $request
     * @param int $activityId
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $activityId)
    {
        $activity = Activity::findOrFail($activityId);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        // Check if user has permission
        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to add questions to this activity',
            ], Response::HTTP_FORBIDDEN);
        }

        // Validate activity type
        if ($activity->type->value !== 'quiz') {
            return response()->json([
                'status' => 'error',
                'message' => 'This activity is not of type quiz',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'title' => 'required|string',
            'question_text' => 'required|string',
            'question_type' => 'required|string|in:multiple_choice,multiple_response,true_false,text,fill_blanks_text,fill_blanks_drag,matching,hotspot,essay,questionnaire,matrix',
            'instructions' => 'nullable|string',
            'instruction_format' => 'nullable|string|in:plain,markdown,html,wysiwyg',
            'options' => 'required_if:question_type,multiple_choice,multiple_response,matching,hotspot|array',
            'options.*.text' => 'required_if:question_type,multiple_choice,multiple_response,matching|string',
            'options.*.is_correct' => 'required_if:question_type,multiple_choice,multiple_response|boolean',
            'options.*.match_text' => 'required_if:question_type,matching|string',
            'options.*.position' => 'required_if:question_type,hotspot|array',
            'options.*.position.x' => 'required_if:question_type,hotspot|numeric',
            'options.*.position.y' => 'required_if:question_type,hotspot|numeric',
            'blanks' => 'required_if:question_type,fill_blanks_text,fill_blanks_drag|array',
            'blanks.*.text' => 'required_if:question_type,fill_blanks_text,fill_blanks_drag|string',
            'blanks.*.correct_answer' => 'required_if:question_type,fill_blanks_text|string',
            'blanks.*.position' => 'required_if:question_type,fill_blanks_drag|array',
            'blanks.*.position.x' => 'required_if:question_type,fill_blanks_drag|numeric',
            'blanks.*.position.y' => 'required_if:question_type,fill_blanks_drag|numeric',
            'matrix_rows' => 'required_if:question_type,matrix|array',
            'matrix_columns' => 'required_if:question_type,matrix|array',
            'matrix_options' => 'required_if:question_type,matrix|array',
            'matrix_options.*.row' => 'required_if:question_type,matrix|string',
            'matrix_options.*.column' => 'required_if:question_type,matrix|string',
            'matrix_options.*.is_selected' => 'required_if:question_type,matrix|boolean',
            'explanation' => 'nullable|string',
            'points' => 'required|integer|min:1',
            'is_scorable' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Get or create quiz content
        $quizContent = QuizContent::firstOrCreate(
            ['activity_id' => $activityId],
            [
                'title' => 'Quiz for ' . $activity->title,
                'passing_score' => 70,
                'randomize_questions' => false,
                'show_correct_answers' => true,
                'created_by' => Auth::id(),
            ]
        );

        // If using JSON storage for questions
        if (isset($quizContent->questions) && is_array($quizContent->questions)) {
            $questions = $quizContent->questions;
            $newQuestion = $request->all();
            $newQuestion['id'] = count($questions) + 1; // Simple ID assignment
            $questions[] = $newQuestion;
            
            $quizContent->questions = $questions;
            $quizContent->save();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Question added successfully',
                'data' => $newQuestion,
            ], Response::HTTP_CREATED);
        }

        // If using related table for questions
        $question = new QuizQuestion([
            'quiz_content_id' => $quizContent->id,
            'title' => $request->title,
            'question' => $request->question_text, // Set the question field to match question_text
            'question_text' => $request->question_text,
            'question_type' => $request->question_type,
            'options' => $request->options,
            'blanks' => $request->blanks,
            'matrix_rows' => $request->matrix_rows,
            'matrix_columns' => $request->matrix_columns,
            'matrix_options' => $request->matrix_options,
            'explanation' => $request->explanation,
            'instructions' => $request->instructions,
            'instruction_format' => $request->instruction_format ?? 'markdown',
            'points' => $request->points,
            'is_scorable' => $request->is_scorable ?? true,
            'order' => QuizQuestion::where('quiz_content_id', $quizContent->id)->count() + 1,
            'created_by' => Auth::id(),
        ]);
        
        $question->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Question added successfully',
            'data' => $question,
        ], Response::HTTP_CREATED);
    }

    /**
     * Update a question
     *
     * @param \Illuminate\Http\Request $request
     * @param int $activityId
     * @param int $questionId
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $activityId, $questionId)
    {
        $activity = Activity::findOrFail($activityId);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        // Check if user has permission
        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to update questions for this activity',
            ], Response::HTTP_FORBIDDEN);
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'title' => 'string',
            'question_text' => 'string',
            'question_type' => 'string|in:multiple_choice,multiple_response,true_false,text,fill_blanks_text,fill_blanks_drag,matching,hotspot,essay,questionnaire,matrix',
            'options' => 'required_if:question_type,multiple_choice,multiple_response,matching,hotspot|array',
            'options.*.text' => 'required_if:question_type,multiple_choice,multiple_response,matching|string',
            'options.*.is_correct' => 'required_if:question_type,multiple_choice,multiple_response|boolean',
            'options.*.match_text' => 'required_if:question_type,matching|string',
            'options.*.position' => 'required_if:question_type,hotspot|array',
            'options.*.position.x' => 'required_if:question_type,hotspot|numeric',
            'options.*.position.y' => 'required_if:question_type,hotspot|numeric',
            'blanks' => 'required_if:question_type,fill_blanks_text,fill_blanks_drag|array',
            'blanks.*.text' => 'required_if:question_type,fill_blanks_text,fill_blanks_drag|string',
            'blanks.*.correct_answer' => 'required_if:question_type,fill_blanks_text|string',
            'blanks.*.position' => 'required_if:question_type,fill_blanks_drag|array',
            'blanks.*.position.x' => 'required_if:question_type,fill_blanks_drag|numeric',
            'blanks.*.position.y' => 'required_if:question_type,fill_blanks_drag|numeric',
            'matrix_rows' => 'required_if:question_type,matrix|array',
            'matrix_columns' => 'required_if:question_type,matrix|array',
            'matrix_options' => 'required_if:question_type,matrix|array',
            'matrix_options.*.row' => 'required_if:question_type,matrix|string',
            'matrix_options.*.column' => 'required_if:question_type,matrix|string',
            'matrix_options.*.is_selected' => 'required_if:question_type,matrix|boolean',
            'explanation' => 'nullable|string',
            'points' => 'integer|min:1',
            'is_scorable' => 'boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Get quiz content
        $quizContent = QuizContent::where('activity_id', $activityId)->firstOrFail();

        // If using JSON storage for questions
        if (isset($quizContent->questions) && is_array($quizContent->questions)) {
            $questions = $quizContent->questions;
            $questionIndex = -1;
            
            foreach ($questions as $index => $question) {
                if (isset($question['id']) && $question['id'] == $questionId) {
                    $questionIndex = $index;
                    break;
                }
            }
            
            if ($questionIndex === -1) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Question not found',
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Update the question
            $questions[$questionIndex] = array_merge($questions[$questionIndex], $request->all());
            $quizContent->questions = $questions;
            $quizContent->save();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Question updated successfully',
                'data' => $questions[$questionIndex],
            ]);
        }

        // If using related table for questions
        $question = QuizQuestion::where('quiz_content_id', $quizContent->id)
            ->where('id', $questionId)
            ->firstOrFail();
        
        $question->fill($request->all());
        $question->save();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Question updated successfully',
            'data' => $question,
        ]);
    }

    /**
     * Delete a question
     *
     * @param int $activityId
     * @param int $questionId
     * @return \Illuminate\Http\Response
     */
    public function destroy($activityId, $questionId)
    {
        $activity = Activity::findOrFail($activityId);
        $block = Block::findOrFail($activity->block_id);
        $template = Template::findOrFail($block->template_id);

        // Check if user has permission
        if ($template->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to delete questions for this activity',
            ], Response::HTTP_FORBIDDEN);
        }

        // Get quiz content
        $quizContent = QuizContent::where('activity_id', $activityId)->firstOrFail();

        // If using JSON storage for questions
        if (isset($quizContent->questions) && is_array($quizContent->questions)) {
            $questions = $quizContent->questions;
            $questionIndex = -1;
            
            foreach ($questions as $index => $question) {
                if (isset($question['id']) && $question['id'] == $questionId) {
                    $questionIndex = $index;
                    break;
                }
            }
            
            if ($questionIndex === -1) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Question not found',
                ], Response::HTTP_NOT_FOUND);
            }
            
            // Remove the question
            array_splice($questions, $questionIndex, 1);
            $quizContent->questions = $questions;
            $quizContent->save();
            
            return response()->json([
                'status' => 'success',
                'message' => 'Question deleted successfully',
            ]);
        }

        // If using related table for questions
        $question = QuizQuestion::where('quiz_content_id', $quizContent->id)
            ->where('id', $questionId)
            ->firstOrFail();
        
        $question->delete();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Question deleted successfully',
        ]);
    }
}
