<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuizQuestion extends Model
{
    use HasFactory, SoftDeletes;

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
        'blanks',
        'matrix_rows',
        'matrix_columns',
        'matrix_options',
        'explanation',
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
        return $this->hasMany(QuizQuestionOption::class);
    }

    /**
     * Get the user who created the question.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
