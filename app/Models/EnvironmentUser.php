<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnvironmentUser extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'environment_user';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'environment_id',
        'user_id',
        'role',
        'permissions',
        'joined_at',
        'use_environment_credentials',
        'environment_email',
        'environment_password',
        'email_verified_at',
        'is_account_setup'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permissions' => 'json',
            'joined_at' => 'datetime',
            'email_verified_at' => 'datetime',
            'use_environment_credentials' => 'boolean',
            'is_account_setup' => 'boolean',
        ];
    }

    /**
     * Get the environment that this relationship belongs to.
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * Get the user that this relationship belongs to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}