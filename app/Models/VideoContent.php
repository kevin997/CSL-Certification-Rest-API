<?php

namespace App\Models;

use App\Traits\HasCreatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class VideoContent extends Model
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
        'video_url',
        'video_type', // youtube, vimeo, mp4, webm
        'duration',
        'thumbnail_url',
        'transcript',
        'captions_url',
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
            'duration' => 'integer',
        ];
    }

    /**
     * Get the activity that owns this content.
     */
    public function activity(): MorphOne
    {
        return $this->morphOne(Activity::class, 'content');
    }
}
