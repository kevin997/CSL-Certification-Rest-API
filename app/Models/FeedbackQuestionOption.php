<?php

namespace App\Models;

use App\Traits\BelongsToEnvironment;
use App\Traits\HasCreatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeedbackQuestionOption extends Model
{
    use HasFactory, SoftDeletes, HasCreatedBy, BelongsToEnvironment;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'feedback_question_id',
        'option_text',
        'subquestion_text',
        'answer_option_id',
        'points',
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
            'points' => 'integer',
            'order' => 'integer',
            'answer_option_id' => 'integer',
        ];
    }

    /**
     * Get the feedback question that owns this option.
     */
    public function feedbackQuestion(): BelongsTo
    {
        return $this->belongsTo(FeedbackQuestion::class);
    }

    /**
     * Get the user who created this option.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
