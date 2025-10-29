<?php

namespace App\Models;

use App\Traits\HasCreatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToEnvironment;


class FeedbackQuestion extends Model
{
    use HasFactory, SoftDeletes, HasCreatedBy, BelongsToEnvironment;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'feedback_content_id',
        'question_text',
        'question_type', // text, rating, multiple_choice, checkbox, dropdown, questionnaire
        'options', // JSON array of options for multiple_choice, checkbox, dropdown
        'answer_options', // JSON array for questionnaire type (global answer options)
        'required',
        'order',
        'created_by',
        'environment_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'options' => 'json',
            'answer_options' => 'json',
            'required' => 'boolean',
            'order' => 'integer',
        ];
    }

    /**
     * Get the feedback content that this question belongs to.
     */
    public function feedbackContent(): BelongsTo
    {
        return $this->belongsTo(FeedbackContent::class);
    }

    /**
     * Get the answers for this question.
     */
    public function answers(): HasMany
    {
        return $this->hasMany(FeedbackAnswer::class);
    }

    /**
     * Get the user who created this question.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the options for this question (for questionnaire type).
     * This relationship is used for questionnaire-type questions that need
     * to store subquestions and answer option assignments.
     */
    public function questionOptions(): HasMany
    {
        return $this->hasMany(FeedbackQuestionOption::class, 'feedback_question_id');
    }

    /**
     * Get the subquestions for questionnaire-type questions.
     * This creates a virtual relationship by grouping FeedbackQuestionOptions
     * that have subquestion_text populated.
     */
    public function getSubquestionsAttribute()
    {
        if ($this->question_type !== 'questionnaire') {
            return collect();
        }

        // Get all options for this question that have subquestion data
        $options = $this->questionOptions()->whereNotNull('subquestion_text')->get();

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
    public function getAnswerOptionsListAttribute()
    {
        if ($this->question_type !== 'questionnaire') {
            return collect();
        }

        // Get unique answer options from the options table
        $answerOptionIds = $this->questionOptions()
            ->whereNotNull('answer_option_id')
            ->distinct('answer_option_id')
            ->pluck('answer_option_id')
            ->unique();

        // Match with answer_options JSON field
        if ($this->answer_options && is_array($this->answer_options)) {
            return collect($this->answer_options)->whereIn('id', $answerOptionIds);
        }

        return collect();
    }
}
