<?php

namespace App\Models;

use App\Traits\HasCreatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LessonContent extends Model
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
        'content',
        'format',
        'estimated_duration',
        'audio_media_asset_id',
        'video_media_asset_id',
        'video_url',
        'resources',
        'introduction',
        'conclusion',
        'enable_discussion',
        'enable_instructor_feedback',
        'enable_questions',
        'show_results',
        'pass_score',
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
            'enable_discussion' => 'boolean',
            'enable_instructor_feedback' => 'boolean',
            'enable_questions' => 'boolean',
            'show_results' => 'boolean',
            'resources' => 'json',
            'estimated_duration' => 'integer',
            'pass_score' => 'integer',
        ];
    }

    /**
     * Get the activity that owns this lesson content.
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * Get the content parts for this lesson.
     */
    public function contentParts(): HasMany
    {
        return $this->hasMany(LessonContentPart::class)->orderBy('order');
    }

    /**
     * Get the questions for this lesson.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(LessonQuestion::class)->orderBy('order');
    }
    
    /**
     * Get the responses for this lesson's questions.
     */
    public function questionResponses(): HasMany
    {
        return $this->hasMany(LessonQuestionResponse::class);
    }
}
