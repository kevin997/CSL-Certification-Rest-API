<?php

namespace App\Models;

use App\Traits\HasCreatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @OA\Schema(
 *     schema="WebinarContent",
 *     title="WebinarContent",
 *     description="Webinar content model for webinar-type activities linked to LiveKit sessions",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="activity_id", type="integer", example=1),
 *     @OA\Property(property="live_session_id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="title", type="string", example="Introduction to Web Development"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="scheduled_at", type="string", format="date-time"),
 *     @OA\Property(property="duration_minutes", type="integer", example=60),
 *     @OA\Property(property="max_participants", type="integer", example=100),
 *     @OA\Property(property="allow_recording", type="boolean", example=false),
 *     @OA\Property(property="enable_chat", type="boolean", example=true),
 *     @OA\Property(property="enable_qa", type="boolean", example=true),
 *     @OA\Property(property="enable_reactions", type="boolean", example=true),
 *     @OA\Property(property="mute_participants_on_join", type="boolean", example=true),
 *     @OA\Property(property="disable_participant_video", type="boolean", example=false),
 *     @OA\Property(property="access_type", type="string", enum={"enrolled", "public", "invited"}),
 *     @OA\Property(property="settings", type="object", nullable=true),
 *     @OA\Property(property="hosts", type="array", @OA\Items(type="integer"), nullable=true),
 *     @OA\Property(property="co_hosts", type="array", @OA\Items(type="integer"), nullable=true),
 *     @OA\Property(property="join_instructions", type="string", nullable=true),
 *     @OA\Property(property="prerequisites", type="string", nullable=true),
 *     @OA\Property(property="recording_url", type="string", nullable=true),
 *     @OA\Property(property="status", type="string", enum={"draft", "scheduled", "live", "completed", "cancelled"}),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class WebinarContent extends Model
{
    use HasFactory, SoftDeletes, HasCreatedBy;

    protected $fillable = [
        'activity_id',
        'live_session_id',
        'title',
        'description',
        'scheduled_at',
        'duration_minutes',
        'max_participants',
        'allow_recording',
        'enable_chat',
        'enable_qa',
        'enable_reactions',
        'mute_participants_on_join',
        'disable_participant_video',
        'access_type',
        'settings',
        'hosts',
        'co_hosts',
        'join_instructions',
        'prerequisites',
        'recording_url',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'allow_recording' => 'boolean',
            'enable_chat' => 'boolean',
            'enable_qa' => 'boolean',
            'enable_reactions' => 'boolean',
            'mute_participants_on_join' => 'boolean',
            'disable_participant_video' => 'boolean',
            'settings' => 'array',
            'hosts' => 'array',
            'co_hosts' => 'array',
        ];
    }

    /**
     * Get the activity that owns this webinar content.
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }

    /**
     * Get the live session associated with this webinar.
     */
    public function liveSession(): BelongsTo
    {
        return $this->belongsTo(LiveSession::class);
    }

    /**
     * Get the user who created this webinar.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Check if the webinar is currently live.
     */
    public function isLive(): bool
    {
        return $this->status === 'live' ||
            ($this->liveSession && $this->liveSession->status === 'live');
    }

    /**
     * Check if the webinar can be joined.
     */
    public function canJoin(): bool
    {
        if ($this->status === 'live') {
            return true;
        }

        if ($this->status === 'scheduled' && $this->scheduled_at) {
            $minutesUntilStart = now()->diffInMinutes($this->scheduled_at, false);
            return $minutesUntilStart <= 15 && $minutesUntilStart >= - ($this->duration_minutes ?? 60);
        }

        return false;
    }

    /**
     * Check if the webinar has ended.
     */
    public function hasEnded(): bool
    {
        return $this->status === 'completed' ||
            ($this->liveSession && $this->liveSession->status === 'ended');
    }

    /**
     * Sync status with associated live session.
     */
    public function syncWithLiveSession(): void
    {
        if (!$this->liveSession) {
            return;
        }

        $sessionStatus = $this->liveSession->status;
        $newStatus = match ($sessionStatus) {
            'live' => 'live',
            'ended' => 'completed',
            'cancelled' => 'cancelled',
            'scheduled' => 'scheduled',
            default => $this->status,
        };

        if ($newStatus !== $this->status) {
            $this->update(['status' => $newStatus]);
        }
    }

    /**
     * Scope for webinars with a specific status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for upcoming webinars.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('status', 'scheduled')
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at', 'asc');
    }

    /**
     * Scope for live webinars.
     */
    public function scopeLive($query)
    {
        return $query->where('status', 'live');
    }
}
