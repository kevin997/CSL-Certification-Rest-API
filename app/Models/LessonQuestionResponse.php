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
        'selected_option_id',
        'text_response',
        'is_correct',
        'points_earned',
        'attempt_number',
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
