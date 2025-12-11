<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @OA\Schema(
 *     schema="LiveSession",
 *     title="LiveSession",
 *     description="Live Session model for webinars",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="environment_id", type="integer", example=1),
 *     @OA\Property(property="created_by", type="integer", example=1),
 *     @OA\Property(property="course_id", type="integer", nullable=true, example=1),
 *     @OA\Property(property="title", type="string", example="Introduction to Web Development"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="room_name", type="string", example="env_1_session_abc123"),
 *     @OA\Property(property="status", type="string", enum={"scheduled", "live", "ended", "cancelled"}),
 *     @OA\Property(property="scheduled_at", type="string", format="date-time"),
 *     @OA\Property(property="started_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="ended_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="duration_minutes", type="integer", example=60),
 *     @OA\Property(property="max_participants", type="integer", example=100),
 *     @OA\Property(property="settings", type="object", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class LiveSession extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'environment_id',
        'created_by',
        'course_id',
        'title',
        'description',
        'room_name',
        'status',
        'scheduled_at',
        'started_at',
        'ended_at',
        'duration_minutes',
        'max_participants',
        'settings',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'settings' => 'array',
    ];

    /**
     * Boot the model and generate room name.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($session) {
            if (empty($session->room_name)) {
                $session->room_name = 'env_' . $session->environment_id . '_session_' . Str::uuid();
            }
        });
    }

    /**
     * Get the environment that owns the session.
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * Get the user who created the session.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the course associated with this session (optional).
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get all participants for this session.
     */
    public function participants(): HasMany
    {
        return $this->hasMany(LiveSessionParticipant::class);
    }

    /**
     * Get the count of current participants.
     */
    public function getParticipantsCountAttribute(): int
    {
        return $this->participants()->whereNotNull('joined_at')->whereNull('left_at')->count();
    }

    /**
     * Check if the session is currently live.
     */
    public function isLive(): bool
    {
        return $this->status === 'live';
    }

    /**
     * Check if the session can be started.
     */
    public function canStart(): bool
    {
        return $this->status === 'scheduled';
    }

    /**
     * Start the session.
     */
    public function start(): bool
    {
        if (!$this->canStart()) {
            return false;
        }

        $this->update([
            'status' => 'live',
            'started_at' => now(),
        ]);

        return true;
    }

    /**
     * End the session.
     */
    public function end(): bool
    {
        if ($this->status !== 'live') {
            return false;
        }

        $endedAt = now();
        $durationMinutes = $this->started_at->diffInMinutes($endedAt);

        $this->update([
            'status' => 'ended',
            'ended_at' => $endedAt,
            'duration_minutes' => $durationMinutes,
        ]);

        return true;
    }

    /**
     * Scope for sessions belonging to an environment.
     */
    public function scopeForEnvironment($query, $environmentId)
    {
        return $query->where('environment_id', $environmentId);
    }

    /**
     * Scope for upcoming sessions.
     */
    public function scopeUpcoming($query)
    {
        return $query->where('status', 'scheduled')
            ->where('scheduled_at', '>=', now())
            ->orderBy('scheduled_at', 'asc');
    }

    /**
     * Scope for live sessions.
     */
    public function scopeLive($query)
    {
        return $query->where('status', 'live');
    }

    /**
     * Scope for past sessions.
     */
    public function scopePast($query)
    {
        return $query->whereIn('status', ['ended', 'cancelled'])
            ->orderBy('ended_at', 'desc');
    }
}
