<?php

namespace App\Models\Analytics;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Traits\HasEnvironmentSlug;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class ChatParticipation extends Model
{
    use HasUuids, HasEnvironmentSlug;

    protected $table = 'chat_participation_analytics';

    protected $fillable = [
        'user_id',
        'course_id',
        'message_count',
        'active_days',
        'engagement_score',
        'first_message_date',
        'last_activity_date',
        'certificate_generated',
        'certificate_id',
        'environment_id'
    ];

    protected $casts = [
        'first_message_date' => 'date',
        'last_activity_date' => 'datetime',
        'certificate_generated' => 'boolean',
        'message_count' => 'integer',
        'active_days' => 'integer',
        'engagement_score' => 'integer'
    ];

    protected $dates = [
        'first_message_date',
        'last_activity_date',
        'created_at',
        'updated_at'
    ];

    /**
     * Boot the model and add global scopes
     */
    protected static function boot()
    {
        parent::boot();

        // Add environment scope if trait is available
        if (method_exists(static::class, 'addGlobalScope')) {
            static::addGlobalScope(new \App\Scopes\EnvironmentScope);
        }
    }

    /**
     * Get the user associated with this participation record
     * Note: In microservices architecture, this might not have a direct relationship
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'user_id');
    }

    /**
     * Scope to get participants eligible for certificates
     */
    public function scopeEligibleForCertificate($query)
    {
        return $query->where('message_count', '>=', config('chat.certificate.min_messages', 10))
                    ->where('active_days', '>=', config('chat.certificate.min_active_days', 3))
                    ->where('engagement_score', '>=', config('chat.certificate.min_engagement_score', 70))
                    ->where('certificate_generated', false);
    }

    /**
     * Scope to get participants by course
     */
    public function scopeByCourse($query, string $courseId)
    {
        return $query->where('course_id', $courseId);
    }

    /**
     * Scope to get participants by engagement score range
     */
    public function scopeByEngagementRange($query, int $min, int $max = 100)
    {
        return $query->whereBetween('engagement_score', [$min, $max]);
    }

    /**
     * Get the participation level based on engagement score
     */
    public function getParticipationLevelAttribute(): string
    {
        $score = $this->engagement_score;

        if ($score >= 90) return 'Outstanding';
        if ($score >= 80) return 'Excellent';
        if ($score >= 70) return 'Good';
        if ($score >= 60) return 'Average';

        return 'Needs Improvement';
    }

    /**
     * Check if user is eligible for participation certificate
     */
    public function getIsEligibleForCertificateAttribute(): bool
    {
        return $this->message_count >= config('chat.certificate.min_messages', 10) &&
               $this->active_days >= config('chat.certificate.min_active_days', 3) &&
               $this->engagement_score >= config('chat.certificate.min_engagement_score', 70) &&
               !$this->certificate_generated;
    }

    /**
     * Get participation streak (consecutive active days)
     */
    public function getParticipationStreakAttribute(): int
    {
        // This would require more complex logic to calculate consecutive active days
        // For now, return active_days as a placeholder
        return $this->active_days;
    }
}