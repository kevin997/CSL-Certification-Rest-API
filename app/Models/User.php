<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory;
    use HasProfilePhoto;
    use HasTeams;
    use Notifiable;
    use TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'two_factor_recovery_codes',
        'two_factor_secret',
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'profile_photo_url',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Get the environments that the user is a member of.
     * (Identity Unification - Story 3)
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function environments(): BelongsToMany
    {
        return $this->belongsToMany(Environment::class, 'environment_user')
            ->withPivot(['role', 'permissions', 'joined_at', 'use_environment_credentials', 'is_account_setup'])
            ->withTimestamps();
    }

    /**
     * Get the environment user records for this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function environmentUsers(): HasMany
    {
        return $this->hasMany(EnvironmentUser::class);
    }

    /**
     * Get the environments that this user owns.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function ownedEnvironments(): HasMany
    {
        return $this->hasMany(Environment::class, 'owner_id');
    }
}
