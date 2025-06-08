<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuizQuestionResponse extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'quiz_submission_id',
        'quiz_question_id',
        'user_response',
        'is_correct',
        'points_earned',
        'max_points',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'user_response' => 'array',
        'is_correct' => 'boolean',
        'points_earned' => 'float',
        'max_points' => 'float',
    ];

    /**
     * Get the submission this response belongs to
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(QuizSubmission::class, 'quiz_submission_id');
    }

    /**
     * Get the question this response is for
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'quiz_question_id');
    }
}
