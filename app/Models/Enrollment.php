<?php

namespace App\Models;

use App\Traits\BelongsToEnvironment;
use App\Traits\HasEnrolledBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Enrollment extends Model
{
    use HasFactory, SoftDeletes, BelongsToEnvironment, HasEnrolledBy;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'course_id',
        'user_id',
        'environment_id',
        'status', // enrolled, in-progress, completed, dropped
        'enrolled_at',
        'completed_at',
        'progress_percentage',
        'last_activity_at',
        'enrolled_by',
    ];

    const STATUS_ENROLLED = 'enrolled';
    const STATUS_IN_PROGRESS = 'in-progress';
    const STATUS_COMPLETED = 'completed';
    const STATUS_DROPPED = 'dropped';

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'enrolled_at' => 'datetime',
            'completed_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'progress_percentage' => 'float',
        ];
    }

    /**
     * Get the course that this enrollment belongs to.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the user who is enrolled.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who enrolled this user (if applicable).
     */
    public function enrolledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }

    /**
     * Get the activity completions for this enrollment.
     */
    public function activityCompletions(): HasMany
    {
        return $this->hasMany(ActivityCompletion::class);
    }
    
    /**
     * Get the analytics data for this enrollment's activities.
     */
    public function analytics(): HasMany
    {
        return $this->hasMany(EnrollmentAnalytics::class);
    }
}
