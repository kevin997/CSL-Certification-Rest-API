<?php

namespace App\Models;

use App\Traits\BelongsToEnvironment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Per-environment Email/WhatsApp automation config for a single trigger.
 *
 * One row per (environment, trigger). Rows are created lazily on first save;
 * the API serves defaults for triggers without a row.
 */
class MarketingAutomation extends Model
{
    use HasFactory, BelongsToEnvironment;

    public const TRIGGER_FORM_SUBMITTED = 'form_submitted';
    public const TRIGGER_ORDER_PLACED = 'order_placed';
    public const TRIGGER_PAYMENT_CONFIRMED = 'payment_confirmed';
    public const TRIGGER_ORDER_ABANDONED = 'order_abandoned';

    public const TRIGGERS = [
        self::TRIGGER_FORM_SUBMITTED,
        self::TRIGGER_ORDER_PLACED,
        self::TRIGGER_PAYMENT_CONFIRMED,
        self::TRIGGER_ORDER_ABANDONED,
    ];

    public const RECIPIENT_INSTRUCTOR = 'instructor';
    public const RECIPIENT_CUSTOMER = 'customer';

    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_WHATSAPP = 'whatsapp';

    /**
     * Default recipient per trigger (used when no row exists yet).
     */
    public const DEFAULT_RECIPIENTS = [
        self::TRIGGER_FORM_SUBMITTED => self::RECIPIENT_INSTRUCTOR,
        self::TRIGGER_ORDER_PLACED => self::RECIPIENT_INSTRUCTOR,
        self::TRIGGER_PAYMENT_CONFIRMED => self::RECIPIENT_CUSTOMER,
        self::TRIGGER_ORDER_ABANDONED => self::RECIPIENT_CUSTOMER,
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'environment_id',
        'trigger',
        'enabled',
        'channels',
        'recipient',
        'email_subject',
        'email_body',
        'whatsapp_template',
        'config',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'enabled' => 'boolean',
        'channels' => 'array',
        'config' => 'array',
    ];

    /**
     * Get the enabled automation for a trigger in a specific environment
     * (bypasses the request-bound global scope — automations fire from
     * queued jobs and console commands with no request context).
     */
    public static function enabledFor(int $environmentId, string $trigger): ?self
    {
        return self::withoutGlobalScopes()
            ->where('environment_id', $environmentId)
            ->where('trigger', $trigger)
            ->where('enabled', true)
            ->first();
    }

    public function usesChannel(string $channel): bool
    {
        return in_array($channel, $this->channels ?? [], true);
    }

    /**
     * Per-trigger extra config value (e.g. abandoned_delay_hours).
     */
    public function configValue(string $key, mixed $default = null): mixed
    {
        return data_get($this->config, $key, $default);
    }
}
