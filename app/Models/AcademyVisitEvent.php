<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AcademyVisitEvent extends Model
{
    protected $table = 'academy_visit_events';

    protected $fillable = [
        'environment_id',
        'visit_hash',
        'path',
        'referrer',
        'ip_hash',
        'user_agent',
        'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
    ];

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }
}
