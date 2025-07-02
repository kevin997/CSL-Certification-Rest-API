<?php

namespace App\Models;

use App\Traits\HasCreatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
}
