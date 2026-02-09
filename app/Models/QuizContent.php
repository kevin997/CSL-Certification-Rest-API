<?php

namespace App\Models;

use App\Traits\HasCreatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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
        'audio_media_asset_id',
        'passing_score',
        'time_limit',
        'max_attempts',
        'randomize_questions',
        'show_correct_answers',
        'questions',  // JSON field for storing questions directly
        'created_by',
        // Proctoring settings
        'prevent_tab_switching',
        'tab_switch_action',
        'max_tab_switches',
        'fullscreen_required',
        'disable_right_click',
        'disable_copy_paste',
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
            // Proctoring casts
            'prevent_tab_switching' => 'boolean',
            'fullscreen_required' => 'boolean',
            'disable_right_click' => 'boolean',
            'disable_copy_paste' => 'boolean',
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
     * Get the questions for this quiz (legacy direct relationship).
     *
     * DEPRECATED: This method is maintained for backward compatibility.
     * Use questionsViaP ivot() for the new many-to-many relationship.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(QuizQuestion::class);
    }

    /**
     * Get the questions for this quiz through the pivot table (many-to-many).
     *
     * This is the preferred method for accessing questions as it supports
     * sharing questions across multiple quizzes without duplication.
     */
    public function questionsViaPivot(): BelongsToMany
    {
        return $this->belongsToMany(
            QuizQuestion::class,
            'activity_quiz_questions',
            'quiz_content_id',
            'quiz_question_id'
        )
        ->withPivot('order')
        ->withTimestamps()
        ->orderBy('activity_quiz_questions.order');
    }

    /**
     * Get all questions for this quiz (combines both legacy and pivot).
     *
     * This method provides a unified way to access questions regardless
     * of whether they use the old direct relationship or new pivot table.
     * It prioritizes pivot table questions and falls back to legacy.
     */
    public function allQuestions()
    {
        // Check if there are any pivot records
        $pivotQuestions = $this->questionsViaPivot()->get();

        if ($pivotQuestions->isNotEmpty()) {
            return $pivotQuestions;
        }

        // Fallback to legacy direct relationship
        return $this->questions()->orderBy('order')->get();
    }
}
