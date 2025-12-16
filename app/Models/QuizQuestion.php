<?php

namespace App\Models;

use App\Traits\HasCreatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuizQuestion extends Model
{
    use HasFactory, SoftDeletes, HasCreatedBy;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'quiz_content_id',
        'question',
        'title',
        'question_text',
        'question_type', // multiple_choice, multiple_response, true_false, text, fill_blanks_text, fill_blanks_drag, matching, hotspot, essay, questionnaire, matrix
        'options',
        'image_url',    // URL to image used for questions like matching or hotspot
        'image_alt',    // Alt text description for the image
        'stimulus_type',
        'stimulus_media_asset_id',
        'blanks',
        'matrix_rows',
        'matrix_columns',
        'matrix_options',
        'explanation',
        'instructions',
        'instruction_format',
        'points',
        'is_scorable',
        'order',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'options' => 'array',
        'blanks' => 'array',
        'matrix_rows' => 'array',
        'matrix_columns' => 'array',
        'matrix_options' => 'array',
        'is_scorable' => 'boolean',
        'instruction_format' => 'string',
    ];

    /**
     * Get the quiz content that owns this question.
     */
    public function quizContent(): BelongsTo
    {
        return $this->belongsTo(QuizContent::class);
    }

    /**
     * Get the options for this question.
     */
    public function options(): HasMany
    {
        return $this->hasMany(QuizQuestionOption::class, 'quiz_question_id');
    }

    /**
     * Get the user who created the question.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the subquestions for questionnaire-type questions.
     * This creates a virtual relationship by grouping QuizQuestionOptions
     * that have subquestion_text populated.
     */
    public function getSubquestionsAttribute()
    {
        if ($this->question_type !== 'questionnaire') {
            return collect();
        }

        // Get all options for this question that have subquestion data
        $options = $this->options()->whereNotNull('subquestion_text')->get();
        
        // Group by subquestion_text to create subquestions
        $subquestions = $options->groupBy('subquestion_text')->map(function ($subquestionOptions, $subquestionText) {
            // Each group represents one subquestion with its assignments
            $assignments = $subquestionOptions->map(function ($option) {
                return (object) [
                    'answer_option_id' => $option->answer_option_id,
                    'points' => $option->points ?? 0,
                ];
            });

            return (object) [
                'text' => $subquestionText,
                'assignments' => $assignments,
            ];
        })->values();

        return $subquestions;
    }

    /**
     * Get the answer options for questionnaire-type questions.
     * This retrieves the unique answer options that can be selected.
     */
    public function getAnswerOptionsAttribute()
    {
        if ($this->question_type !== 'questionnaire') {
            return collect();
        }

        // Get unique answer options from the options table
        $answerOptionIds = $this->options()->whereNotNull('answer_option_id')
            ->distinct('answer_option_id')
            ->pluck('answer_option_id')
            ->unique();

        // For now, we'll create basic answer options
        // In a full implementation, you might have a separate answer_options table
        return $answerOptionIds->map(function ($id) {
            // Get the first option with this answer_option_id to get the text
            $option = $this->options()->where('answer_option_id', $id)->first();
            return (object) [
                'id' => $id,
                'text' => $option->option_text ?? "Option {$id}",
            ];
        });
    }

    /**
     * Get the quiz contents (activities) that use this question (many-to-many).
     *
     * This enables a question to be shared across multiple quiz activities
     * without duplication.
     */
    public function quizContents(): BelongsToMany
    {
        return $this->belongsToMany(
            QuizContent::class,
            'activity_quiz_questions',
            'quiz_question_id',
            'quiz_content_id'
        )
        ->withPivot('order')
        ->withTimestamps();
    }

    /**
     * Check if this question is shared across multiple quizzes.
     *
     * @return bool
     */
    public function isShared(): bool
    {
        return $this->quizContents()->count() > 1;
    }

    /**
     * Get the number of quizzes using this question.
     *
     * @return int
     */
    public function usageCount(): int
    {
        return $this->quizContents()->count();
    }
}
