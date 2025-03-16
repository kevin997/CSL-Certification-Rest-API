<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeedbackQuestion extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'feedback_content_id',
        'question_text',
        'question_type', // text, textarea, radio, checkbox, select, rating, scale
        'options', // JSON array of options for radio, checkbox, select
        'is_required',
        'order',
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
            'options' => 'json',
            'is_required' => 'boolean',
            'order' => 'integer',
        ];
    }

    /**
     * Get the feedback content that this question belongs to.
     */
    public function feedbackContent(): BelongsTo
    {
        return $this->belongsTo(FeedbackContent::class);
    }

    /**
     * Get the answers for this question.
     */
    public function answers(): HasMany
    {
        return $this->hasMany(FeedbackAnswer::class);
    }

    /**
     * Get the user who created this question.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
