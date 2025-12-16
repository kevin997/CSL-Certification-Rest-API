<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademyVisitor extends Model
{
    protected $table = 'academy_visitors';

    protected $fillable = [
        'environment_id',
        'visit_hash',
        'visits_count',
        'first_seen_at',
        'last_seen_at',
        'ip_hash',
        'user_agent',
        'accept_language',
        'country_code',
        'country_name',
        'state_prov',
        'city',
        'isp',
        'geo_data',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'geo_data' => 'array',
    ];

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }
}
