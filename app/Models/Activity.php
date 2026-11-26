<?php

namespace App\Models;

use App\Enums\ActivityType;
use App\Traits\HasCreatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Activity extends Model
{
    use HasCreatedBy, HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'type',
        'order',
        'block_id',
        'status',
        'created_by',
        'content_type',
        'content_id',
        'settings',
        'learning_objectives',
        'conditions',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ActivityType::class,
            'settings' => 'json',
            'learning_objectives' => 'json',
            'conditions' => 'json',
        ];
    }

    /**
     * Get the block that owns the activity.
     */
    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    /**
     * Get the user who created the activity.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the content model (polymorphic).
     */
    public function content(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the lesson content associated with this activity.
     */
    public function lessonContent(): HasOne
    {
        return $this->hasOne(LessonContent::class);
    }

    /**
     * Get the quiz content associated with this activity.
     */
    public function quizContent(): HasOne
    {
        return $this->hasOne(QuizContent::class);
    }

    /**
     * Get the text content associated with this activity.
     */
    public function textContent(): HasOne
    {
        return $this->hasOne(TextContent::class);
    }

    /**
     * Get the documentation content associated with this activity.
     */
    public function documentationContent(): HasOne
    {
        return $this->hasOne(DocumentationContent::class);
    }

    /**
     * Get the assignment content associated with this activity.
     */
    public function assignmentContent(): HasOne
    {
        return $this->hasOne(AssignmentContent::class);
    }

    /**
     * Get the certificate content associated with this activity.
     */
    public function certificateContent(): HasOne
    {
        return $this->hasOne(CertificateContent::class);
    }

    /**
     * Get the feedback content associated with this activity.
     */
    public function feedbackContent(): HasOne
    {
        return $this->hasOne(FeedbackContent::class);
    }

    /**
     * Get the video hand-in content associated with this activity.
     */
    public function videoHandInContent(): HasOne
    {
        return $this->hasOne(VideoHandInContent::class);
    }

    /**
     * Get the first video content associated with this activity.
     */
    public function videoContent(): HasOne
    {
        return $this->hasOne(VideoContent::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Get all video content rows associated with this activity.
     */
    public function videoContents(): HasMany
    {
        return $this->hasMany(VideoContent::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Get the webinar content associated with this activity.
     */
    public function webinarContent(): HasOne
    {
        return $this->hasOne(WebinarContent::class);
    }
}
