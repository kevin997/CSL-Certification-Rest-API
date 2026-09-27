<?php

namespace App\Models;

use App\Scopes\EnvironmentScope;
use App\Traits\BelongsToEnvironment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ThirdPartyService extends Model
{
    use BelongsToEnvironment, HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'base_url',
        'api_key',
        'api_secret',
        'bearer_token',
        'username',
        'password',
        'is_active',
        'service_type',
        'config',
    ];

    protected $hidden = [
        'api_key',
        'api_secret',
        'bearer_token',
        'username',
        'password',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'api_key' => 'encrypted',
        'api_secret' => 'encrypted',
        'bearer_token' => 'encrypted',
        'username' => 'encrypted',
        'password' => 'encrypted',
        'is_active' => 'boolean',
        'config' => 'json',
    ];

    /**
     * Get a service by its type
     */
    public static function getServiceByType(string $type): ?ThirdPartyService
    {
        return self::withoutGlobalScope(EnvironmentScope::class)
            ->whereNull('environment_id')
            ->where('service_type', $type)
            ->where('is_active', true)
            ->first();
    }
}
