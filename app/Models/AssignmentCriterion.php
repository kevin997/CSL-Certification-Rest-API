<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class AssignmentCriterion extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'assignment_content_id',
        'title',
        'description',
        'points',
        'order',
    ];

    /**
     * Get the assignment content that owns this criterion.
     */
    public function assignmentContent(): BelongsTo
    {
        return $this->belongsTo(AssignmentContent::class);
    }
}
