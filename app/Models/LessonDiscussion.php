<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LessonDiscussion extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'lesson_content_id',
        'content_part_id',
        'question_id',
        'user_id',
        'message',
        'parent_id',
        'is_instructor_feedback',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_instructor_feedback' => 'boolean',
        ];
    }

    /**
     * Get the lesson content that this discussion belongs to.
     */
    public function lessonContent(): BelongsTo
    {
        return $this->belongsTo(LessonContent::class);
    }

    /**
     * Get the content part that this discussion is related to (if any).
     */
    public function contentPart(): BelongsTo
    {
        return $this->belongsTo(LessonContentPart::class, 'content_part_id');
    }

    /**
     * Get the question that this discussion is related to (if any).
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(LessonQuestion::class, 'question_id');
    }

    /**
     * Get the user who created this discussion.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the parent discussion (if this is a reply).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(LessonDiscussion::class, 'parent_id');
    }

    /**
     * Get the replies to this discussion.
     */
    public function replies(): HasMany
    {
        return $this->hasMany(LessonDiscussion::class, 'parent_id');
    }
}
