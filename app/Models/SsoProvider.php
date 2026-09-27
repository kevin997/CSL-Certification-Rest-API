<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SsoProvider extends Model
{
    protected $fillable = [
        'environment_id',
        'name',
        'driver',
        'issuer_url',
        'client_id',
        'client_secret',
        'idp_entity_id',
        'idp_sso_url',
        'idp_slo_url',
        'idp_x509_cert',
        'scopes',
        'enabled',
        'auto_provision',
        'default_role',
    ];

    protected $hidden = [
        'client_secret',
    ];

    protected function casts(): array
    {
        return [
            'client_secret' => 'encrypted',
            'scopes' => 'array',
            'enabled' => 'boolean',
            'auto_provision' => 'boolean',
        ];
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * @return array<int, string>
     */
    public function scopeList(): array
    {
        return $this->scopes ?? ['openid', 'email', 'profile'];
    }

    /**
     * The exact URLs an IdP admin must register on their side — surfaced so
     * the settings UI can offer them for copy instead of making operators
     * guess at route shapes.
     *
     * @return array<string, string>
     */
    protected function registration(): Attribute
    {
        return Attribute::get(fn (): array => match ($this->driver) {
            'saml' => [
                'entity_id' => route('sso.metadata', ['provider' => $this->id]),
                'acs_url' => route('sso.samlAcs', ['provider' => $this->id]),
                'metadata_url' => route('sso.metadata', ['provider' => $this->id]),
            ],
            default => [
                'callback_url' => route('sso.callback'),
            ],
        });
    }
}
