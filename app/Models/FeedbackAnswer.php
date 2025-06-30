<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BelongsToEnvironment;

class FeedbackAnswer extends Model
{
    use HasFactory, SoftDeletes, BelongsToEnvironment;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'feedback_submission_id',
        'feedback_question_id',
        'answer_text',
        'answer_value', // For numeric ratings/scales
        'answer_options', // JSON array for checkbox selections
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
            'answer_value' => 'float',
            'answer_options' => 'json',
        ];
    }

    /**
     * Get the submission that this answer belongs to.
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(FeedbackSubmission::class, 'feedback_submission_id');
    }

    /**
     * Get the question that this answer is for.
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(FeedbackQuestion::class, 'feedback_question_id');
    }
}
