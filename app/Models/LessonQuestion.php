<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LessonQuestion extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'lesson_content_id',
        'content_part_id', // nullable, if the question is related to a specific content part
        'question',
        'question_type', // multiple_choice, multiple_response, true_false, etc.
        'is_scorable',
        'image_url',    // URL to image used for questions like matching or hotspot
        'image_alt',    // Alt text description for the image
        'points',
        'order',
        'created_by',
        'explanation',  // Explanation of the correct answer
        'question_text', // Additional text for the question (e.g., for fill in blanks)
        'title',        // Optional title for the question
        'blanks',       // JSON array for fill in blanks questions
        'matrix_rows',  // JSON array for matrix questions
        'matrix_columns', // JSON array for matrix questions
        'matrix_options', // JSON array for matrix question options
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_scorable' => 'boolean',
            'blanks' => 'array',
            'matrix_rows' => 'array',
            'matrix_columns' => 'array',
            'matrix_options' => 'array',
            'points' => 'integer',
        ];
    }

    /**
     * Get the lesson content that owns this question.
     */
    public function lessonContent(): BelongsTo
    {
        return $this->belongsTo(LessonContent::class);
    }

    /**
     * Get the content part that this question is related to (if any).
     */
    public function contentPart(): BelongsTo
    {
        return $this->belongsTo(LessonContentPart::class, 'content_part_id');
    }

    /**
     * Get the options for this question.
     */
    public function options(): HasMany
    {
        return $this->hasMany(LessonQuestionOption::class);
    }

    /**
     * Get the discussions for this question.
     */
    public function discussions(): HasMany
    {
        return $this->hasMany(LessonDiscussion::class, 'question_id');
    }

    /**
     * Get the user who created this question.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
