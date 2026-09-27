<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SocialAuthProvider extends Model
{
    protected $fillable = [
        'environment_id',
        'provider',
        'client_id',
        'client_secret',
        'enabled',
    ];

    protected $hidden = [
        'client_secret',
    ];

    protected function casts(): array
    {
        return [
            'client_secret' => 'encrypted',
            'enabled' => 'boolean',
        ];
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }
}
