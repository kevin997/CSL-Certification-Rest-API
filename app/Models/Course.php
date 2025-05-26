<?php

namespace App\Models;

use App\Traits\BelongsToEnvironment;
use App\Traits\HasCreatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Course extends Model
{
    use HasFactory, SoftDeletes, BelongsToEnvironment, HasCreatedBy;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'slug',
        'description',
        'template_id',
        'environment_id',
        'created_by',
        'status', // draft, published, archived
        'start_date',
        'end_date',
        'enrollment_limit',
        'is_self_paced',
        'estimated_duration',
        'difficulty_level', // beginner, intermediate, advanced
        'thumbnail_url',  // Changed from thumbnail_path to thumbnail_url
        'featured_image',
        'is_featured',
        'meta_title',
        'meta_description',
        'meta_keywords',
        'published_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'start_date' => 'datetime',
            'end_date' => 'datetime',
            'is_self_paced' => 'boolean',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Get the template that the course is based on.
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    /**
     * Get the user who created this course.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the enrollments for this course.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    /**
     * Get the users enrolled in this course.
     */
    public function enrolledUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'enrollments')
            ->withPivot('status', 'enrolled_at', 'completed_at')
            ->withTimestamps();
    }

    /**
     * Get the course sections.
     */
    public function sections(): HasMany
    {
        return $this->hasMany(CourseSection::class);
    }

    /**
     * Get the issued certificates for this course.
     */
    public function issuedCertificates(): HasMany
    {
        return $this->hasMany(IssuedCertificate::class);
    }
}
