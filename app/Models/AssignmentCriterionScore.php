<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssignmentCriterionScore extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'assignment_submission_id',
        'assignment_criterion_id',
        'score',
        'feedback',
    ];

    /**
     * Get the submission that this score belongs to.
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(AssignmentSubmission::class, 'assignment_submission_id');
    }

    /**
     * Get the criterion that this score is for.
     */
    public function criterion(): BelongsTo
    {
        return $this->belongsTo(AssignmentCriterion::class, 'assignment_criterion_id');
    }
}
