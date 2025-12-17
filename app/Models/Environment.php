<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @OA\Schema(
 *     schema="Environment",
 *     title="Environment",
 *     description="Environment model",
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="name", type="string", example="Acme Corp Training"),
 *     @OA\Property(property="primary_domain", type="string", example="training.acmecorp.com"),
 *     @OA\Property(property="additional_domains", type="string", nullable=true, example="learn.acmecorp.com,edu.acmecorp.com"),
 *     @OA\Property(property="theme_color", type="string", example="#4F46E5"),
 *     @OA\Property(property="logo_url", type="string", nullable=true, example="https://acmecorp.com/logo.png"),
 *     @OA\Property(property="favicon_url", type="string", nullable=true, example="https://acmecorp.com/favicon.ico"),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="owner_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="description", type="string", nullable=true, example="Corporate training environment for Acme Corp employees"),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true)
 * )
 */
class Environment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'primary_domain',
        'additional_domains',
        'theme_color',
        'logo_url',
        'favicon_url',
        'is_active',
        'is_demo',
        'owner_id',
        'description',
        'country_code', //default CM
        'state_code', // default null
        'organization_type',
        'niche',
        'payment_settings'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'additional_domains' => 'array',
        'is_active' => 'boolean',
        'is_demo' => 'boolean',
        'payment_settings' => 'array',
    ];

    /**
     * Get the owner of the environment.
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * Get the users associated with this environment.
     */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withPivot('role', 'permissions')
            ->withTimestamps();
    }

    /**
     * Get all domains (primary and additional) for this environment.
     *
     * @return array<string>
     */
    public function getAllDomains(): array
    {
        $domains = [$this->primary_domain];

        if (!empty($this->additional_domains)) {
            $domains = array_merge($domains, $this->additional_domains);
        }

        return $domains;
    }

    /**
     * Check if a domain belongs to this environment.
     *
     * @param string $domain
     * @return bool
     */
    public function hasDomain(string $domain): bool
    {
        return in_array($domain, $this->getAllDomains());
    }

    /**
     * Get the templates in this environment.
     */
    public function templates(): HasMany
    {
        return $this->hasMany(Template::class);
    }

    /**
     * Get the courses in this environment.
     */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }

    /**
     * Get the products in this environment.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * Get the teams in this environment.
     */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /**
     * Get the orders in this environment.
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the issued certificates in this environment.
     */
    public function issuedCertificates(): HasMany
    {
        return $this->hasMany(IssuedCertificate::class);
    }

    /**
     * Get the brandings in this environment.
     */
    public function brandings(): HasMany
    {
        return $this->hasMany(Branding::class, "environment_id");
    }

    /**
     * Get the active branding for this environment.
     */
    public function branding(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(Branding::class, "environment_id")->where('is_active', true)->latest();
    }

    /**
     * Get the active subscription for this environment.
     */
    public function subscription(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Check if the environment has an active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscription()->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>', now());
            })->exists();
    }

    /**
     * Get the environment type based on the plan.
     */
    public function getEnvironmentType(): string
    {
        $activeSubscription = $this->subscription()
            ->where('status', 'active')
            ->whereHas('plan')
            ->first();

        if (!$activeSubscription) {
            return 'free';
        }

        return $activeSubscription->plan->type ?? 'free';
    }

    /**
     * Check if the environment is a demo environment.
     *
     * @return bool
     */
    public function isDemoEnvironment(): bool
    {
        return $this->is_demo === true;
    }

    /**
     * Get the payment configuration for this environment.
     */
    public function paymentConfig(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EnvironmentPaymentConfig::class);
    }

    /**
     * Get the instructor commissions for this environment.
     */
    public function instructorCommissions(): HasMany
    {
        return $this->hasMany(InstructorCommission::class);
    }

    /**
     * Get the withdrawal requests for this environment.
     */
    public function withdrawalRequests(): HasMany
    {
        return $this->hasMany(WithdrawalRequest::class);
    }

    /**
     * Get the live session settings for this environment.
     */
    public function liveSettings(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(EnvironmentLiveSettings::class);
    }

    /**
     * Get the live sessions for this environment.
     */
    public function liveSessions(): HasMany
    {
        return $this->hasMany(LiveSession::class);
    }
}
