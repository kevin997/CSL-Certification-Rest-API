<?php

namespace App\Models;

use App\Scopes\EnvironmentScope;
use App\Traits\BelongsToEnvironment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class ThirdPartyService extends Model
{
    use BelongsToEnvironment, HasFactory, SoftDeletes;

    /**
     * Columns encrypted at rest by the casts below.
     *
     * @var list<string>
     */
    public const CREDENTIAL_FIELDS = [
        'api_key',
        'api_secret',
        'bearer_token',
        'username',
        'password',
    ];

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
     * Read one encrypted credential, tolerating a value this APP_KEY cannot
     * decrypt.
     *
     * The credential columns are cast to `encrypted`, so reading any of them
     * throws DecryptException after a key rotation or a database copied from
     * another installation. That used to turn into an uncaught 500 in the
     * certificate service, where every read sat outside a catch (\Throwable).
     * A service that cannot authenticate should degrade to "not configured",
     * not crash the request.
     */
    public function credential(string $key): ?string
    {
        if (! in_array($key, self::CREDENTIAL_FIELDS, true)) {
            throw new \InvalidArgumentException("Unknown credential column [{$key}].");
        }

        try {
            $value = $this->getAttribute($key);
        } catch (\Throwable $e) {
            Log::warning('Third-party service credential is unreadable; treating it as unset', [
                'service_id' => $this->getKey(),
                'service_type' => $this->getAttribute('service_type'),
                'credential' => $key,
                'exception' => $e::class,
            ]);

            return null;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * Whether every one of the listed credentials can be read.
     */
    public function hasCredentials(string ...$keys): bool
    {
        foreach ($keys as $key) {
            if ($this->credential($key) === null) {
                return false;
            }
        }

        return true;
    }

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
