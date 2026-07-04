<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MediaAsset extends Model
{
    protected $fillable = [
        'environment_id',
        'owner_user_id',
        'media_service_id',
        'provider',
        'provider_asset_id',
        'playback_url',
        'title',
        'type',
        'mime_type',
        'size',
        'duration',
        'status',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'owner_user_id' => 'integer',
        'environment_id' => 'integer',
        'size' => 'integer',
        'duration' => 'integer',
    ];
}
