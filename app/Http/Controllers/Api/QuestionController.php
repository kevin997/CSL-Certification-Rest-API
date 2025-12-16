<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Block;
use App\Models\QuizContent;
use App\Models\QuizQuestion;
use App\Models\QuizQuestionOption;
use App\Models\Template;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

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
        $questions = QuizQuestion::where('quiz_content_id', $quizContent->id)
            ->with('options')
            ->get();
            
        // Debug: Check what questions we got
        Log::info('Questions query result', [
            'quiz_content_id' => $quizContent->id,
            'questions_count' => $questions->count(),
            'questions' => $questions->map(function($q) {
                // Handle different types of options: relationship collection vs JSON array
                $optionsCount = 'null';
                if ($q->question_type === 'questionnaire') {
                    // For questionnaire, options refers to the relationship
                    $optionsCount = $q->options ? $q->options->count() : 'null';
                } else {
                    // For other types, options is a JSON array in the options column
                    $optionsArray = $q->getAttributes()['options'] ?? null;
                    if (is_array($optionsArray)) {
                        $optionsCount = count($optionsArray);
                    } elseif (is_string($optionsArray)) {
                        $decoded = json_decode($optionsArray, true);
                        $optionsCount = is_array($decoded) ? count($decoded) : 'invalid_json';
                    }
                }
                
                return [
                    'id' => $q->id,
                    'question_type' => $q->question_type,
                    'options_count' => $optionsCount
                ];
            })
        ]);

        // Transform questionnaire questions to include answer_options and subquestions
        $transformedQuestions = $questions->map(function ($question) {
            $questionArray = $question->toArray();
            
            if ($question->question_type === 'questionnaire') {
                // Extract answer_options and subquestions from QuizQuestionOption records
                // Use manual query instead of relationship since relationship isn't working
                $options = \App\Models\QuizQuestionOption::where('quiz_question_id', $question->id)->get();
                $answerOptions = [];
                $subquestions = [];
                $subquestionMap = [];
                
                Log::info('Questionnaire reconstruction debug', [
                    'question_id' => $question->id,
                    'options_count' => $options ? count($options) : 'null',
                    'options_data' => $options ? $options->toArray() : 'null'
                ]);
                
                if ($options && count($options) > 0) {
                    foreach ($options as $option) {
                        // Handle answer options (subquestion_text is null)
                        if (is_null($option->subquestion_text)) {
                            $answerOptions[$option->answer_option_id] = [
                                'id' => $option->answer_option_id,
                                'text' => $option->option_text
                            ];
                        } else {
                            // Handle assignments (subquestion_text is not null)
                            $subquestionText = $option->subquestion_text;
                            if (!isset($subquestionMap[$subquestionText])) {
                                $subquestionMap[$subquestionText] = [
                                    'text' => $subquestionText,
                                    'assignments' => [],
                                    'image_url' => $option->image_url,
                                    'image_alt' => $option->image_alt
                                ];
                            }
                            // If this assignment has an image, update the subquestion's image fields
                            if ($option->image_url) {
                                $subquestionMap[$subquestionText]['image_url'] = $option->image_url;
                            }
                            if ($option->image_alt) {
                                $subquestionMap[$subquestionText]['image_alt'] = $option->image_alt;
                            }
                            $assignment = [
                                'answer_option_id' => $option->answer_option_id,
                                'points' => $option->points ?? 0,
                                'feedback' => $option->feedback ?? ''
                            ];
                            $subquestionMap[$subquestionText]['assignments'][] = $assignment;
                            Log::info('Adding assignment', [
                                'subquestion' => $subquestionText,
                                'assignment' => $assignment,
                                'current_assignments' => $subquestionMap[$subquestionText]['assignments']
                            ]);
                        }
                    }
                } else {
                    // No options found, provide defaults
                    $answerOptions = [
                        ['id' => 0, 'text' => 'Option A'],
                        ['id' => 1, 'text' => 'Option B']
                    ];
                    $subquestionMap = [
                        ['text' => 'Subquestion 1', 'assignments' => [], 'image_url' => null, 'image_alt' => null]
                    ];
                }
                
                $questionArray['answer_options'] = array_values($answerOptions);
                $questionArray['subquestions'] = array_values($subquestionMap);
            } else {
                // For all other types, use the options column (not the relationship)
                $questionArray['options'] = $question->getAttributes()['options'] ?? [];
                // If options is a JSON string, decode it
                if (is_string($questionArray['options'])) {
                    $decoded = json_decode($questionArray['options'], true);
                    $questionArray['options'] = is_array($decoded) ? $decoded : [];
                }
            }
            
            return $questionArray;
        });
        
        return response()->json([
            'status' => 'success',
            'data' => $transformedQuestions,
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
            'question' => 'required|string',
            'question_type' => 'required|string|in:multiple_choice,multiple_response,true_false,text,fill_blanks_text,fill_blanks_drag,matching,hotspot,essay,questionnaire,matrix,drag_and_drop,ordering,code_snippet,multi_select,short_answer,fill_in_blank',
            'instructions' => 'nullable|string',
            'instruction_format' => 'nullable|string|in:plain,markdown,html,wysiwyg',
            // Common question options
            'options' => 'required_if:question_type,multiple_choice,multiple_response,matching,hotspot,drag_and_drop,ordering,multi_select,questionnaire|array',
            'options.*.text' => 'required_if:question_type,multiple_choice,multiple_response,matching,drag_and_drop,ordering,multi_select,questionnaire|string',
            'options.*.is_correct' => 'required_if:question_type,multiple_choice,multiple_response,multi_select,hotspot,questionnaire|boolean',
            'options.*.points' => 'required_if:question_type,questionnaire|integer|min:0',
            // Matrix-style questionnaire fields
            'answer_options' => 'required_if:question_type,questionnaire|array',
            'answer_options.*.text' => 'required_if:question_type,questionnaire|string',
            'subquestions' => 'required_if:question_type,questionnaire|array',
            'subquestions.*.text' => 'required_if:question_type,questionnaire|string',
            'subquestions.*.assignments' => 'required_if:question_type,questionnaire|array',
            'subquestions.*.assignments.*.answer_option_id' => 'required_if:question_type,questionnaire|integer',
            'subquestions.*.assignments.*.points' => 'required_if:question_type,questionnaire|integer|min:0',
            'options.*.match_text' => 'required_if:question_type,matching|string',
            'options.*.value' => 'nullable|string',
            'options.*.feedback' => 'nullable|string',
            
            // Hotspot question
            'options.*.position' => 'required_if:question_type,hotspot|array',
            'options.*.position.x' => 'required_if:question_type,hotspot|numeric',
            'options.*.position.y' => 'required_if:question_type,hotspot|numeric',
            
            // Image fields for matching and hotspot questions
            'image_url' => 'nullable|string',
            'image_alt' => 'nullable|string',
            
            // Drag and Drop question
            'options.*.source_container' => 'required_if:question_type,drag_and_drop|string',
            'options.*.target_container' => 'required_if:question_type,drag_and_drop|string',
            
            // Ordering question 
            'options.*.initial_order' => 'required_if:question_type,ordering|numeric',
            'options.*.correct_order' => 'required_if:question_type,ordering|numeric',
            
            // Essay question
            'answer_required' => 'nullable|boolean',
            'min_words' => 'nullable|integer|min:0',
            'max_words' => 'nullable|integer|min:0',
            'model_answer' => 'nullable|string',
            'rubric' => 'nullable|string',
            
            // Code snippet question
            'code_language' => 'required_if:question_type,code_snippet|string',
            'initial_code' => 'nullable|string',
            'solution_code' => 'nullable|string',
            
            // Fill in blanks
            'blanks' => 'required_if:question_type,fill_blanks_text,fill_blanks_drag,fill_in_blank|array',
            'blanks.*.text' => 'required_if:question_type,fill_blanks_text,fill_blanks_drag,fill_in_blank|string',
            'blanks.*.correct_answer' => 'required_if:question_type,fill_blanks_text,fill_in_blank|string',
            'blanks.*.position' => 'required_if:question_type,fill_blanks_drag|array',
            'blanks.*.position.x' => 'required_if:question_type,fill_blanks_drag|numeric',
            'blanks.*.position.y' => 'required_if:question_type,fill_blanks_drag|numeric',
            
            // Matrix question
            'matrix_rows' => 'required_if:question_type,matrix|array',
            'matrix_columns' => 'required_if:question_type,matrix|array',
            'matrix_options' => 'required_if:question_type,matrix|array',
            'matrix_options.*.row' => 'required_if:question_type,matrix|string',
            'matrix_options.*.column' => 'required_if:question_type,matrix|string',
            'matrix_options.*.is_selected' => 'required_if:question_type,matrix|boolean',
            
            // Common for all question types
            'explanation' => 'nullable|string',
            'points' => 'required|integer|min:1',
            'is_scorable' => 'boolean',
            'stimulus_type' => 'nullable|string|in:audio,video,image',
            'stimulus_media_asset_id' => 'nullable|integer|exists:media_assets,id',
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
        $question = QuizQuestion::create([
            'quiz_content_id' => $quizContent->id,
            'title' => $request->title,
            'question_text' => $request->question_text,
            'question' => $request->question,
            'question_type' => $request->question_type,
            'options' => $request->question_type === 'questionnaire' ? null : ($request->options ? $request->options : null),
            'image_url' => $request->image_url,
            'image_alt' => $request->image_alt,
            'stimulus_type' => $request->stimulus_type,
            'stimulus_media_asset_id' => $request->stimulus_media_asset_id,
            'blanks' => $request->blanks ? $request->blanks : null,
            'matrix_rows' => $request->matrix_rows ? $request->matrix_rows : null,
            'matrix_columns' => $request->matrix_columns ? $request->matrix_columns : null,
            'matrix_options' => $request->matrix_options ? $request->matrix_options : null,
            'explanation' => $request->explanation,
            'instructions' => $request->instructions,
            'instruction_format' => $request->instruction_format ?? 'plain',
            'points' => $request->points,
            'is_scorable' => $request->is_scorable ?? true,
            'order' => QuizQuestion::where('quiz_content_id', $quizContent->id)->count() + 1,
            'created_by' => Auth::id(),
        ]);
        
        $question->save();
        
        // Handle questionnaire type options in QuizQuestionOption table
        if ($request->question_type === 'questionnaire') {
            if ($request->has('subquestions') && $request->has('answer_options')) {
                // Matrix-style questionnaire with reusable answer options
                
                // First: Store ALL answer options (the complete set of choices)
                foreach ($request->answer_options as $answerOption) {
                    $question->options()->create([
                        'subquestion_text' => null, // This is an answer option, not tied to a specific subquestion
                        'answer_option_id' => $answerOption['id'],
                        'option_text' => $answerOption['text'],
                        'is_correct' => false, // Answer options themselves aren't correct/incorrect
                        'points' => 0, // Base answer options have no points
                        'feedback' => null,
                        'order' => $answerOption['id'] + 1,
                        'image_url' => null,
                        'image_alt' => null,
                    ]);
                }
                
                // Second: Store assignments (which subquestion uses which answer option with what points)
                foreach ($request->subquestions as $subIndex => $subquestion) {
                    foreach ($subquestion['assignments'] as $assignment) {
                        $question->options()->create([
                            'subquestion_text' => $subquestion['text'],
                            'answer_option_id' => $assignment['answer_option_id'],
                            'option_text' => $request->answer_options[$assignment['answer_option_id']]['text'] ?? '',
                            'is_correct' => true, // Assignment means it's correct for this subquestion
                            'points' => $assignment['points'] ?? 0,
                            'feedback' => $assignment['feedback'] ?? null,
                            'order' => $subIndex + 1,
                            'image_url' => $subquestion['image_url'] ?? null,
                            'image_alt' => $subquestion['image_alt'] ?? null,
                        ]);
                    }
                }
            } elseif ($request->options) {
                // Legacy single-question style
                foreach ($request->options as $index => $option) {
                    $question->options()->create([
                        'option_text' => $option['text'],
                        'is_correct' => $option['is_correct'] ?? false,
                        'points' => $option['points'] ?? 0,
                        'feedback' => $option['feedback'] ?? null,
                        'order' => $index + 1,
                        'image_url' => null,
                        'image_alt' => null,
                    ]);
                }
            }
        }

        // Format the response data to include questionnaire-specific fields
        $responseData = $question->toArray();
        if ($request->question_type === 'questionnaire') {
            $responseData['answer_options'] = $request->answer_options ?? [];
            $responseData['subquestions'] = $request->subquestions ?? [];
        }
        
        return response()->json([
            'status' => 'success',
            'message' => 'Question added successfully',
            'data' => $responseData,
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
            'question' => 'string',
            'question_type' => 'string|in:multiple_choice,multiple_response,true_false,text,fill_blanks_text,fill_blanks_drag,matching,hotspot,essay,questionnaire,matrix',
            'options' => 'required_if:question_type,multiple_choice,multiple_response,matching,hotspot,questionnaire|array',
            'options.*.text' => 'required_if:question_type,multiple_choice,multiple_response,matching,questionnaire|string',
            'options.*.is_correct' => 'required_if:question_type,multiple_choice,multiple_response,questionnaire|boolean',
            'options.*.points' => 'required_if:question_type,questionnaire|integer|min:0',
            // Matrix-style questionnaire fields
            'answer_options' => 'required_if:question_type,questionnaire|array',
            'answer_options.*.text' => 'required_if:question_type,questionnaire|string',
            'subquestions' => 'required_if:question_type,questionnaire|array',
            'subquestions.*.text' => 'required_if:question_type,questionnaire|string',
            'subquestions.*.assignments' => 'required_if:question_type,questionnaire|array',
            'subquestions.*.assignments.*.answer_option_id' => 'required_if:question_type,questionnaire|integer',
            'subquestions.*.assignments.*.points' => 'required_if:question_type,questionnaire|integer|min:0',
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
            'stimulus_type' => 'nullable|string|in:audio,video,image',
            'stimulus_media_asset_id' => 'nullable|integer|exists:media_assets,id',
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
            
            // Handle naming convention mapping for compatibility between front-end and back-end
            $requestData = $request->all();
            
            // Update the question
            $questions[$questionIndex] = array_merge($questions[$questionIndex], $requestData);
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
        
        // Handle naming convention mapping for compatibility between front-end and back-end
        $requestData = $request->all();
        
        // For questionnaire type, don't store options in the options column
        if ($request->question_type === 'questionnaire') {
            unset($requestData['options']);
        }
        
        $question->fill($requestData);
        $question->save();
        
        // Handle questionnaire type options in QuizQuestionOption table
        if ($request->question_type === 'questionnaire') {
            // Delete existing options
            $question->options()->delete();
            
            if ($request->has('subquestions') && $request->has('answer_options')) {
                // Matrix-style questionnaire with reusable answer options
                
                // First: Store ALL answer options (the complete set of choices)
                foreach ($request->answer_options as $answerOption) {
                    $question->options()->create([
                        'subquestion_text' => null, // This is an answer option, not tied to a specific subquestion
                        'answer_option_id' => $answerOption['id'],
                        'option_text' => $answerOption['text'],
                        'is_correct' => false, // Answer options themselves aren't correct/incorrect
                        'points' => 0, // Base answer options have no points
                        'feedback' => null,
                        'order' => $answerOption['id'] + 1,
                        'image_url' => null,
                        'image_alt' => null,
                    ]);
                }
                
                // Second: Store assignments (which subquestion uses which answer option with what points)
                foreach ($request->subquestions as $subIndex => $subquestion) {
                    foreach ($subquestion['assignments'] as $assignment) {
                        $question->options()->create([
                            'subquestion_text' => $subquestion['text'],
                            'answer_option_id' => $assignment['answer_option_id'],
                            'option_text' => $request->answer_options[$assignment['answer_option_id']]['text'] ?? '',
                            'is_correct' => true, // Assignment means it's correct for this subquestion
                            'points' => $assignment['points'] ?? 0,
                            'feedback' => $assignment['feedback'] ?? null,
                            'order' => $subIndex + 1,
                            'image_url' => $subquestion['image_url'] ?? null,
                            'image_alt' => $subquestion['image_alt'] ?? null,
                        ]);
                    }
                }
            } elseif ($request->options) {
                // Legacy single-question style
                foreach ($request->options as $index => $option) {
                    $question->options()->create([
                        'option_text' => $option['text'],
                        'is_correct' => $option['is_correct'] ?? false,
                        'points' => $option['points'] ?? 0,
                        'feedback' => $option['feedback'] ?? null,
                        'order' => $index + 1,
                        'image_url' => null,
                        'image_alt' => null,
                    ]);
                }
            }
        }

        // Format the response data to include questionnaire-specific fields
        $responseData = $question->toArray();
        if ($request->question_type === 'questionnaire') {
            $responseData['answer_options'] = $request->answer_options ?? [];
            $responseData['subquestions'] = $request->subquestions ?? [];
        }
        
        return response()->json([
            'status' => 'success',
            'message' => 'Question updated successfully',
            'data' => $responseData,
        ]);
    }

    /**
     * Map question type naming conventions between front-end and back-end
     * This method would ensure compatibility when front-end and back-end use different naming conventions
     * Currently not needed, but kept as placeholder for future use
     *
     * @param array $data The request data to map
     * @return array The mapped request data
     */
    private function mapQuestionTypeNaming(array $data): array
    {
        // No mapping needed at this time, but this method could be used in the future
        // For example, if front-end uses 'multiChoice' but back-end uses 'multiple_choice'
        
        return $data;
    }
    
    /**
     * Get the next order number for a question in a quiz content
     *
     * @param int $quizContentId
     * @return int
     */
    private function getNextQuestionOrder(int $quizContentId): int
    {
        return QuizQuestion::where('quiz_content_id', $quizContentId)->count() + 1;
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
        
        // Delete associated options for questionnaire type
        if ($question->question_type === 'questionnaire') {
            $question->options()->delete();
        }
        
        $question->delete();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Question deleted successfully',
        ]);
    }
}
