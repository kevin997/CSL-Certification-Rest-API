<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeedbackSubmission extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'feedback_content_id',
        'user_id',
        'submission_date',
        'status', // draft, submitted, reviewed
        'reviewed_at',
        'reviewed_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'submission_date' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * Get the feedback content that this submission belongs to.
     */
    public function feedbackContent(): BelongsTo
    {
        return $this->belongsTo(FeedbackContent::class);
    }

    /**
     * Get the user who submitted this feedback.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the user who reviewed this submission.
     */
    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * Get the answers for this submission.
     */
    public function answers(): HasMany
    {
        return $this->hasMany(FeedbackAnswer::class);
    }
}
