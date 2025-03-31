<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeedbackContent extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'feedback_type', // 360, questionnaire, form, survey
        'is_anonymous',
        'allow_multiple_submissions',
        'start_date',
        'end_date',
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
            'is_anonymous' => 'boolean',
            'allow_multiple_submissions' => 'boolean',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
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
