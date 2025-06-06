<?php

namespace App\Models;

use App\Traits\HasCreatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssignmentContent extends Model
{
    use HasFactory, SoftDeletes, HasCreatedBy;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'activity_id',
        'title',
        'description',
        'instructions',
        'instruction_format',
        'due_date',
        'passing_score',
        'max_attempts',
        'allow_late_submissions',
        'late_submission_penalty',
        'enable_feedback',
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'due_date' => 'datetime',
            'allow_late_submissions' => 'boolean',
            'enable_feedback' => 'boolean',
            'instruction_format' => 'string',
        ];
    }

    /**
     * Get the activity that owns this content.
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class, 'activity_id');
    }

    /**
     * Get the criteria for this assignment.
     */
    public function criteria(): HasMany
    {
        return $this->hasMany(AssignmentCriterion::class)->orderBy('order');
    }

    /**
     * Get the submissions for this assignment.
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }
}
