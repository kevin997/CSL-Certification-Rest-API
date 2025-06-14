<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonQuestionResponse extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'lesson_question_id',
        'lesson_content_id',
        'selected_option_id',      // For single-selection questions
        'selected_option_ids',     // JSON array for multiple-selection questions
        'text_response',           // For short answer, essay, etc.
        'matrix_responses',        // JSON for matrix question responses
        'hotspot_responses',       // JSON for hotspot question responses
        'matching_responses',      // JSON for matching question responses
        'fill_blanks_responses',   // JSON for fill-in-blanks responses
        'is_correct',              // Whether the response is correct
        'points_earned',           // Points earned for this response
        'attempt_number',          // Which attempt this is
        'feedback',                // Feedback for this response
        'graded_by',               // User ID of the grader (for manually graded questions)
        'graded_at',               // When this response was graded
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_correct' => 'boolean',
        'points_earned' => 'float',
        'attempt_number' => 'integer',
        'selected_option_ids' => 'array',
        'matrix_responses' => 'array',
        'hotspot_responses' => 'array',
        'matching_responses' => 'array',
        'fill_blanks_responses' => 'array',
        'graded_at' => 'datetime',
    ];

    /**
     * Get the user who submitted this response.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the question that this response is for.
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(LessonQuestion::class, 'lesson_question_id');
    }

    /**
     * Get the lesson content that this response belongs to.
     */
    public function lessonContent(): BelongsTo
    {
        return $this->belongsTo(LessonContent::class, 'lesson_content_id');
    }

    /**
     * Get the selected option for multiple choice/true-false questions.
     */
    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(LessonQuestionOption::class, 'selected_option_id');
    }
}
