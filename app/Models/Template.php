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

class Template extends Model
{
    use HasFactory, SoftDeletes, BelongsToEnvironment, HasCreatedBy;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'template_code',
        'description',
        'status',
        'created_by',
        'team_id',
        'environment_id',
    ];

    /**
     * Get the blocks associated with the template.
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(Block::class)->orderBy('order');
    }

    /**
     * Get the courses created from this template.
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /**
     * Get the user who created the template.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the team that owns the template.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the users who purchased this template from the marketplace.
     */
    public function buyers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'purchased_templates')
            ->withPivot('order_id', 'source', 'purchased_at')
            ->withTimestamps();
    }
}
