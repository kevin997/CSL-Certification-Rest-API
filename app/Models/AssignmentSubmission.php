<?php

namespace App\Models;

use App\Traits\HasGradedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssignmentSubmission extends Model
{
    use HasFactory, SoftDeletes, HasGradedBy;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'assignment_content_id',
        'user_id',
        'submission_text',
        'status', // draft, submitted, graded
        'score',
        'feedback',
        'attempt_number',
        'submitted_at',
        'graded_by',
        'graded_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submitted_at' => 'datetime',
            'graded_at' => 'datetime',
        ];
    }

    /**
     * Get the assignment content that this submission belongs to.
     */
    public function assignmentContent(): BelongsTo
    {
        return $this->belongsTo(AssignmentContent::class);
    }

    /**
     * Get the user who made this submission.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who graded this submission.
     */
    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    /**
     * Get the files attached to this submission.
     */
    public function files(): HasMany
    {
        return $this->hasMany(AssignmentSubmissionFile::class);
    }

    /**
     * Get the criterion scores for this submission.
     */
    public function criterionScores(): HasMany
    {
        return $this->hasMany(AssignmentCriterionScore::class);
    }
}
