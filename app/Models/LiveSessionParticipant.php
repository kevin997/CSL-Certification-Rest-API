<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="LiveSessionParticipant",
 *     title="LiveSessionParticipant",
 *     description="Participant in a live session",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="live_session_id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", example=1),
 *     @OA\Property(property="role", type="string", enum={"host", "co-host", "viewer"}),
 *     @OA\Property(property="joined_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="left_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="duration_seconds", type="integer", example=3600)
 * )
 */
class LiveSessionParticipant extends Model
{
    use HasFactory;

    protected $fillable = [
        'live_session_id',
        'user_id',
        'role',
        'joined_at',
        'left_at',
        'duration_seconds',
    ];

    protected $casts = [
        'joined_at' => 'datetime',
        'left_at' => 'datetime',
    ];

    /**
     * Get the session this participant belongs to.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class, 'live_session_id');
    }

    /**
     * Get the user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if this participant is currently in the session.
     */
    public function isActive(): bool
    {
        return $this->joined_at !== null && $this->left_at === null;
    }

    /**
     * Record the participant joining.
     */
    public function recordJoin(): void
    {
        $this->update([
            'joined_at' => now(),
            'left_at' => null,
        ]);
    }

    /**
     * Record the participant leaving.
     */
    public function recordLeave(): void
    {
        if ($this->joined_at) {
            $durationSeconds = $this->joined_at->diffInSeconds(now());
            $this->update([
                'left_at' => now(),
                'duration_seconds' => $this->duration_seconds + $durationSeconds,
            ]);
        }
    }

    /**
     * Check if participant can publish (host or co-host).
     */
    public function canPublish(): bool
    {
        return in_array($this->role, ['host', 'co-host']);
    }
}
