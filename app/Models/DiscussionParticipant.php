<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscussionParticipant extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'discussion_id',
        'user_id',
        'last_read_at',
        'is_online',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_read_at' => 'datetime',
            'is_online' => 'boolean',
        ];
    }

    /**
     * Get the discussion that this participant belongs to.
     */
    public function discussion(): BelongsTo
    {
        return $this->belongsTo(Discussion::class);
    }

    /**
     * Get the user who is the participant.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to get only online participants.
     */
    public function scopeOnline($query)
    {
        return $query->where('is_online', true);
    }

    /**
     * Scope to get participants by discussion.
     */
    public function scopeForDiscussion($query, int $discussionId)
    {
        return $query->where('discussion_id', $discussionId);
    }

    /**
     * Mark participant as online.
     */
    public function markOnline(): void
    {
        $this->update(['is_online' => true]);
    }

    /**
     * Mark participant as offline.
     */
    public function markOffline(): void
    {
        $this->update(['is_online' => false]);
    }

    /**
     * Update last read timestamp.
     */
    public function updateLastRead(): void
    {
        $this->update(['last_read_at' => now()]);
    }
}
