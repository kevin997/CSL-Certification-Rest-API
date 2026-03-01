<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VideoHandInContent extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'activity_id',
        'title',
        'description',
        'instructions',
        'instructions_format',
        'max_duration',
        'allowed_formats',
        'max_file_size',
        'due_date',
        'allow_late_submissions',
    ];

    protected $casts = [
        'allowed_formats' => 'array',
        'allow_late_submissions' => 'boolean',
        'due_date' => 'datetime',
        'max_duration' => 'integer',
        'max_file_size' => 'integer',
    ];

    public function activity()
    {
        return $this->belongsTo(Activity::class);
    }
}
