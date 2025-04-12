<?php

namespace App\Models;

use App\Traits\HasCreatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class CourseSection extends Model
{
    use HasFactory, SoftDeletes, HasCreatedBy;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'course_id',
        'title',
        'description',
        'order',
        'is_published',
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
            'order' => 'integer',
            'is_published' => 'boolean',
        ];
    }

    /**
     * Get the course that this section belongs to.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    // The creator() method is now provided by the HasCreatedBy trait

    /**
     * Get the course section items.
     */
    public function items(): HasMany
    {
        return $this->hasMany(CourseSectionItem::class)->orderBy('order');
    }
    
    /**
     * Get the activities associated with this section through section items.
     */
    public function activities(): BelongsToMany
    {
        return $this->belongsToMany(Activity::class, 'course_section_items')
            ->withPivot(['title', 'description', 'order', 'is_published', 'is_required', 'created_by'])
            ->orderBy('course_section_items.order')
            ->withTimestamps();
    }
}
