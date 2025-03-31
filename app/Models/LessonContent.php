<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class LessonContent extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'introduction',
        'conclusion',
        'enable_discussion',
        'enable_instructor_feedback',
        'enable_questions',
        'show_results',
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
        ];
    }

    /**
     * Get the activity that owns this content.
     */
    public function activity(): MorphOne
    {
        return $this->morphOne(Activity::class, 'content');
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
}
