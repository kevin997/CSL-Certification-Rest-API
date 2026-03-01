<?php

namespace App\Models;

use App\Traits\HasCreatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentationContent extends Model
{
    use HasFactory, SoftDeletes, HasCreatedBy;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'activity_id',
        'title',
        'description',
        'content',
        'format',
        'version',
        'tags',
        'attachments',
        'related_links',
        'allow_downloads',
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
            'allow_downloads' => 'boolean',
            'tags' => 'array',
            'attachments' => 'array',
            'related_links' => 'array',
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
     * Get the attachments for this documentation.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(DocumentationAttachment::class);
    }
}
