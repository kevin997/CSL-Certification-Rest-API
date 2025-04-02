<?php

namespace App\Models;

use App\Enums\ActivityType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activity extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'type',
        'order',
        'block_id',
        'status',
        'created_by',
        'content_type',
        'content_id',
        'settings',
        'learning_objectives',
        'conditions',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'settings' => 'json',
            'learning_objectives' => 'json',
            'conditions' => 'json',
        ];
    }

    /**
     * Get the block that owns the activity.
     */
    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    /**
     * Get the user who created the activity.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the content model (polymorphic).
     */
    public function content(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the lesson content associated with this activity.
     */
    public function lessonContent(): HasOne
    {
        return $this->hasOne(LessonContent::class);
    }
}
