<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaAsset extends Model
{
    protected $fillable = [
        'environment_id',
        'owner_user_id',
        'media_service_id',
        'title',
        'type',
        'status',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'owner_user_id' => 'integer',
        'environment_id' => 'integer',
    ];
}
