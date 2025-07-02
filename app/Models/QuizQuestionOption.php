<?php

namespace App\Models;

use App\Traits\HasCreatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuizQuestionOption extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'quiz_question_id',
        'option_text',
        'is_correct',
        'feedback',
        'order',
        'points',
        'subquestion_text',
        'answer_option_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
        ];
    }

    /**
     * Get the question that owns this option.
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(QuizQuestion::class, 'quiz_question_id');
    }
}
