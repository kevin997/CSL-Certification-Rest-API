<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @OA\Schema(
 *     schema="EnvironmentLiveSettings",
 *     title="EnvironmentLiveSettings",
 *     description="Live session settings for an environment",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="environment_id", type="integer", example=1),
 *     @OA\Property(property="live_sessions_enabled", type="boolean", example=true),
 *     @OA\Property(property="monthly_minutes_limit", type="integer", example=1000),
 *     @OA\Property(property="monthly_minutes_used", type="integer", example=250),
 *     @OA\Property(property="max_concurrent_sessions", type="integer", example=1),
 *     @OA\Property(property="max_participants_per_session", type="integer", example=100),
 *     @OA\Property(property="billing_cycle_resets_at", type="string", format="date-time", nullable=true)
 * )
 */
class EnvironmentLiveSettings extends Model
{
    use HasFactory;

    protected $fillable = [
        'environment_id',
        'live_sessions_enabled',
        'monthly_minutes_limit',
        'monthly_minutes_used',
        'max_concurrent_sessions',
        'max_participants_per_session',
        'billing_cycle_resets_at',
    ];

    protected $casts = [
        'live_sessions_enabled' => 'boolean',
        'billing_cycle_resets_at' => 'datetime',
    ];

    /**
     * Get the environment.
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * Check if live sessions are enabled for this environment.
     */
    public function isEnabled(): bool
    {
        return $this->live_sessions_enabled;
    }

    /**
     * Check if the environment has exceeded its monthly limit.
     */
    public function hasExceededLimit(): bool
    {
        if ($this->monthly_minutes_limit === 0) {
            return false; // Unlimited
        }

        return $this->monthly_minutes_used >= $this->monthly_minutes_limit;
    }

    /**
     * Get remaining minutes for the month.
     */
    public function getRemainingMinutes(): int
    {
        if ($this->monthly_minutes_limit === 0) {
            return PHP_INT_MAX; // Unlimited
        }

        return max(0, $this->monthly_minutes_limit - $this->monthly_minutes_used);
    }

    /**
     * Add usage minutes.
     */
    public function addUsage(int $minutes): void
    {
        $this->increment('monthly_minutes_used', $minutes);
    }

    /**
     * Reset monthly usage (called by billing cycle).
     */
    public function resetMonthlyUsage(): void
    {
        $this->update([
            'monthly_minutes_used' => 0,
            'billing_cycle_resets_at' => now()->addMonth(),
        ]);
    }

    /**
     * Check if a new session can be started based on concurrent limits.
     */
    public function canStartNewSession(): bool
    {
        if (!$this->isEnabled()) {
            return false;
        }

        if ($this->hasExceededLimit()) {
            return false;
        }

        $activeSessions = LiveSession::where('environment_id', $this->environment_id)
            ->where('status', 'live')
            ->count();

        return $activeSessions < $this->max_concurrent_sessions;
    }
}
