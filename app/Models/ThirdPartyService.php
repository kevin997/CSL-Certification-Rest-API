<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ThirdPartyService extends Model
{
    use HasFactory, SoftDeletes;

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

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
        'config' => 'json',
    ];

    /**
     * Get a service by its type
     *
     * @param string $type
     * @return ThirdPartyService|null
     */
    public static function getServiceByType(string $type): ?ThirdPartyService
    {
        return self::where('service_type', $type)
            ->where('is_active', true)
            ->first();
    }
}
