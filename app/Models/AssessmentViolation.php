<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentViolation extends Model
{
    protected $fillable = [
        'quiz_submission_id',
        'user_id',
        'violation_type',
        'violated_at',
        'question_index',
        'metadata',
    ];

    protected $casts = [
        'violated_at' => 'datetime',
        'metadata' => 'array',
        'question_index' => 'integer',
    ];

    /**
     * Get the quiz submission that this violation belongs to.
     */
    public function submission()
    {
        return $this->belongsTo(QuizSubmission::class, 'quiz_submission_id');
    }

    /**
     * Get the user who committed this violation.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
