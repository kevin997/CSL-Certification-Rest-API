<?php

namespace App\Models;

use App\Traits\HasCreatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class QuizContent extends Model
{
    use HasFactory, SoftDeletes, HasCreatedBy;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'activity_id',
        'title',
        'description',
        'instructions',
        'instruction_format',
        'passing_score',
        'time_limit',
        'max_attempts',
        'randomize_questions',
        'show_correct_answers',
        'questions',  // JSON field for storing questions directly
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'randomize_questions' => 'boolean',
            'show_correct_answers' => 'boolean',
            'questions' => 'array',
            'instruction_format' => 'string',
        ];
    }

    /**
     * Get the activity that owns this content.
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    /**
     * Get the questions for this quiz.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class);
    }
}
