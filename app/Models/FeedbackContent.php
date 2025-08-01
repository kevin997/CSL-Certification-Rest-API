<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasCreatedBy;
use App\Traits\BelongsToEnvironment;


class FeedbackContent extends Model
{
    use HasFactory, SoftDeletes, HasCreatedBy, BelongsToEnvironment;

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
        'feedback_type', // 360, questionnaire, form, survey
        'allow_anonymous',
        'completion_message',
        'resource_files',
        'allow_multiple_submissions',
        'start_date',
        'end_date',
        'created_by',
        'environment_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allow_anonymous' => 'boolean',
            'allow_multiple_submissions' => 'boolean',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'resource_files' => 'json',
            'instruction_format' => 'string',
        ];
    }

    /**
     * Get the activity that owns this content.
     */
    public function activity(): MorphOne
    {
        return $this->morphOne(Activity::class, 'content');
    }

    /**
     * Get the questions for this feedback.
     */
    public function questions(): HasMany
    {
        return $this->hasMany(FeedbackQuestion::class);
    }

    /**
     * Get the submissions for this feedback.
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(FeedbackSubmission::class);
    }

    /**
     * Get the user who created this feedback.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
