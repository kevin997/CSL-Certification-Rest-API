<?php

namespace App\Models;

use App\Traits\HasGradedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @OA\Schema(
 *     schema="AssignmentSubmission",
 *     type="object",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="assignment_content_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="user_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="submission_text", type="string", example="This is my assignment submission"),
 *     @OA\Property(property="status", type="string", example="submitted", description="draft, submitted, graded"),
 *     @OA\Property(property="score", type="number", format="float", example=85, nullable=true),
 *     @OA\Property(property="feedback", type="string", example="Good work!", nullable=true),
 *     @OA\Property(property="attempt_number", type="integer", example=1),
 *     @OA\Property(property="submitted_at", type="string", format="date-time", example="2025-05-27T15:00:00Z"),
 *     @OA\Property(property="graded_by", type="integer", format="int64", example=2, nullable=true),
 *     @OA\Property(property="graded_at", type="string", format="date-time", example="2025-05-28T10:00:00Z", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-05-27T14:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-05-27T15:00:00Z"),
 *     @OA\Property(property="deleted_at", type="string", format="date-time", example=null, nullable=true),
 *     @OA\Property(
 *         property="user",
 *         type="object",
 *         @OA\Property(property="id", type="integer", format="int64", example=1),
 *         @OA\Property(property="name", type="string", example="John Doe"),
 *         @OA\Property(property="email", type="string", format="email", example="john@example.com")
 *     ),
 *     @OA\Property(
 *         property="files",
 *         type="array",
 *         @OA\Items(type="object",
 *             @OA\Property(property="id", type="integer", format="int64", example=1),
 *             @OA\Property(property="filename", type="string", example="assignment.pdf"),
 *             @OA\Property(property="path", type="string", example="submissions/assignment.pdf"),
 *             @OA\Property(property="size", type="integer", example=1024),
 *             @OA\Property(property="mime_type", type="string", example="application/pdf")
 *         )
 *     )
 * )
 */

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
