<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Discussion extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The table associated with the model.
     */
    protected $table = 'course_discussions';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'course_id',
        'environment_id',
        'type',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => 'string',
        ];
    }

    /**
     * Get the course that owns the discussion.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the environment that owns the discussion.
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * Get all messages for this discussion.
     */
    public function messages(): HasMany
    {
        return $this->hasMany(DiscussionMessage::class);
    }

    /**
     * Get all participants for this discussion.
     */
    public function participants(): HasMany
    {
        return $this->hasMany(DiscussionParticipant::class);
    }

    /**
     * Get recent messages for this discussion.
     */
    public function recentMessages(): HasMany
    {
        return $this->hasMany(DiscussionMessage::class)
                    ->orderBy('created_at', 'desc')
                    ->limit(50);
    }
}
