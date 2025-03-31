<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LessonContentPart extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'lesson_content_id',
        'title',
        'content_type', // wysiwyg, video
        'content',
        'video_url',
        'video_provider',
        'order',
        'created_by',
    ];

    /**
     * Get the lesson content that owns this part.
     */
    public function lessonContent(): BelongsTo
    {
        return $this->belongsTo(LessonContent::class);
    }

    /**
     * Get the discussions for this content part.
     */
    public function discussions(): HasMany
    {
        return $this->hasMany(LessonDiscussion::class, 'content_part_id');
    }

    /**
     * Get the questions asked by learners for this content part.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(LessonQuestion::class, 'content_part_id');
    }

    /**
     * Get the user who created this content part.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
