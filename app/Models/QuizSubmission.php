<?php

namespace App\Models;

use App\Traits\HasCreatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuizSubmission extends Model
{
    use HasFactory, SoftDeletes, HasCreatedBy;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'quiz_content_id',
        'user_id',
        'enrollment_id',
        'score',
        'max_score',
        'percentage_score',
        'is_passed',
        'completed_at',
        'time_spent',
        'attempt_number',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'score' => 'float',
        'max_score' => 'float',
        'percentage_score' => 'float',
        'is_passed' => 'boolean',
        'completed_at' => 'datetime',
        'time_spent' => 'integer',
        'attempt_number' => 'integer',
    ];

    /**
     * Get the quiz content this submission is for
     */
    public function quizContent(): BelongsTo
    {
        return $this->belongsTo(QuizContent::class);
    }

    /**
     * Get the user who submitted this quiz
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the enrollment associated with this submission
     */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    /**
     * Get the individual question responses for this submission
     */
    public function responses(): HasMany
    {
        return $this->hasMany(QuizQuestionResponse::class);
    }
}
