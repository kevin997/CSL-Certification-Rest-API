<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Jetstream\HasProfilePhoto;
use Laravel\Jetstream\HasTeams;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Facades\Storage;

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
        'whatsapp_number',
        'role',
        'is_admin',
        'company_name',
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
            'role' => UserRole::class,
            'is_admin' => 'boolean',
        ];
    }

    /**
     * Get the URL to the user's profile photo.
     * This overrides the method from HasProfilePhoto trait to handle Cloudinary URLs.
     *
     * @return string
     */
    public function getProfilePhotoUrlAttribute()
    {
        // If profile_photo_path is a Cloudinary URL, return it directly
        if ($this->profile_photo_path && strpos($this->profile_photo_path, 'cloudinary.com') !== false) {
            return $this->profile_photo_path;
        }

        // Otherwise, fall back to the default behavior from the trait
        return $this->profile_photo_path
            ? Storage::disk($this->profilePhotoDisk())->url($this->profile_photo_path)
            : $this->defaultProfilePhotoUrl();
    }

    /**
     * Check if the user is a learner.
     */
    public function isLearner(): bool
    {
        return $this->role === UserRole::LEARNER;
    }

    /**
     * Check if the user is a teacher (individual or company).
     */
    public function isTeacher(): bool
    {
        return $this->role === UserRole::INDIVIDUAL_TEACHER || $this->role === UserRole::COMPANY_TEACHER;
    }

    /**
     * Check if the user is an admin.
     */
    public function isAdmin(): bool
    {
        return $this->role === UserRole::ADMIN || $this->role === UserRole::SUPER_ADMIN;
    }

    /**
     * Check if the user is a sales agent.
     */
    public function isSalesAgent(): bool
    {
        return $this->role === UserRole::SALES_AGENT;
    }

    /**
     * Get the templates created by this user.
     */
    public function templates(): HasMany
    {
        return $this->hasMany(Template::class, 'created_by');
    }

    /**
     * Get the courses created by this user.
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class, 'created_by');
    }

    /**
     * Get the products created by this user.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'created_by');
    }

    /**
     * Get the orders made by this user.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'user_id');
    }

    /**
     * Get the referrals created by this user.
     */
    public function referrals(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    /**
     * Get the branding associated with this user.
     */
    public function branding(): HasMany
    {
        return $this->hasMany(Branding::class);
    }

    /**
     * Get the environments owned by this user.
     */
    public function ownedEnvironments(): HasMany
    {
        return $this->hasMany(Environment::class, 'owner_id');
    }

    /**
     * Get the environments this user belongs to.
     */
    public function environments(): BelongsToMany
    {
        return $this->belongsToMany(Environment::class)
            ->withPivot('role', 'permissions', 'environment_email', 'environment_password', 'email_verified_at', 'use_environment_credentials')
            ->withTimestamps();
    }

    /**
     * Get the current environment for this user based on the domain.
     *
     * @param string $domain
     * @return \App\Models\Environment|null
     */
    public function getCurrentEnvironment(string $domain): ?Environment
    {
        return $this->environments()
            ->where(function ($query) use ($domain) {
                $query->where('primary_domain', $domain)
                    ->orWhereJsonContains('additional_domains', $domain);
            })
            ->first();
    }

    /**
     * Get environment-specific credentials for a given environment.
     *
     * @param int $environmentId
     * @return object|null
     */
    public function getEnvironmentCredentials(int $environmentId): ?object
    {
        $pivot = $this->environments()
            ->where('environment_id', $environmentId)
            ->first()?->pivot;

        if ($pivot && $pivot->use_environment_credentials) {
            return (object) [
                'email' => $pivot->environment_email,
                'password' => $pivot->environment_password,
                'email_verified_at' => $pivot->email_verified_at,
            ];
        }

        return null;
    }

    /**
     * Set environment-specific credentials for a given environment.
     *
     * @param int $environmentId
     * @param string $email
     * @param string $password
     * @param bool $useEnvironmentCredentials
     * @return bool
     */
    public function setEnvironmentCredentials(int $environmentId, string $email, string $password, bool $useEnvironmentCredentials = true): bool
    {
        $pivot = $this->environments()
            ->where('environment_id', $environmentId)
            ->first()?->pivot;

        if (!$pivot) {
            return false;
        }

        return $this->environments()->updateExistingPivot($environmentId, [
            'environment_email' => $email,
            'environment_password' => \Illuminate\Support\Facades\Hash::make($password),
            'use_environment_credentials' => $useEnvironmentCredentials,
        ]);
    }

    /**
     * Get the enrollments for this user.
     */
    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'user_id');
    }

    /**
     * Get the subscriptions for this user.
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class, 'user_id');
    }

    /**
     * Get the disk that profile photos are stored on.
     *
     * @return string
     */
    protected function profilePhotoDisk(): string
    {
        return 'public';
    }
}
