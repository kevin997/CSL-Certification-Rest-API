<?php

namespace App\Models;

use App\Services\TelegramService;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    use HasFactory;
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'log_type',
        'source',
        'action',
        'entity_type',
        'entity_id',
        'environment_id',
        'user_id',
        'request_data',
        'response_data',
        'metadata',
        'notes',
        'ip_address',
        'user_agent',
        'status',
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'request_data' => 'array',
        'response_data' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
    
    /**
     * Log types constants
     */
    public const TYPE_WEBHOOK = 'webhook';
    public const TYPE_CALLBACK = 'callback';
    public const TYPE_API = 'api';
    public const TYPE_SYSTEM = 'system';
    
    /**
     * Status constants
     */
    public const STATUS_SUCCESS = 'success';
    public const STATUS_FAILURE = 'failure';
    public const STATUS_ERROR = 'error';
    public const STATUS_WARNING = 'warning';

    /**
     * Boot the model.
     */
    protected static function booted(): void
    {
        static::created(function (AuditLog $auditLog) {
            $auditLog->sendTelegramNotification('created');
        });

        static::updated(function (AuditLog $auditLog) {
            if ($auditLog->wasChanged(['status', 'response_data', 'metadata', 'notes'])) {
                $auditLog->sendTelegramNotification('updated');
            }
        });
    }
    
    /**
     * Get the environment that owns the audit log.
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }
    
    /**
     * Get the user that owns the audit log.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Static helper to quickly create a webhook audit log
     * 
     * @param string $source Gateway or service name
     * @param array $requestData Full request data
     * @param string|null $entityType Type of entity
     * @param string|null $entityId ID of entity
     * @param int|null $environmentId Environment ID
     * @param array|null $responseData Response data if available
     * @param array|null $metadata Additional metadata
     * @param string|null $status Status of the webhook
     * @return static
     */
    public static function logWebhook(
        string $source, 
        array $requestData, 
        ?string $entityType = null,
        ?string $entityId = null,
        ?int $environmentId = null,
        ?array $responseData = null,
        ?array $metadata = null,
        ?string $status = null
    ): self {
        $ip = request()->ip();
        $userAgent = request()->userAgent();
        
        return self::create([
            'log_type' => self::TYPE_WEBHOOK,
            'source' => $source,
            'action' => $metadata['event_type'] ?? null,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'environment_id' => $environmentId,
            'request_data' => $requestData,
            'response_data' => $responseData,
            'metadata' => $metadata,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'status' => $status ?? self::STATUS_SUCCESS,
        ]);
    }
    
    /**
     * Static helper to quickly create a callback audit log
     * 
     * @param string $source Gateway name
     * @param string $action Success or failure
     * @param array $requestData Full request data
     * @param string|null $entityType Type of entity
     * @param string|null $entityId ID of entity
     * @param int|null $environmentId Environment ID
     * @param string|null $notes Additional notes
     * @param string|null $status Status of the callback
     * @return static
     */
    public static function logCallback(
        string $source, 
        string $action,
        array $requestData, 
        ?string $entityType = null,
        ?string $entityId = null,
        ?int $environmentId = null,
        ?string $notes = null,
        ?string $status = null
    ): self {
        $ip = request()->ip();
        $userAgent = request()->userAgent();
        
        return self::create([
            'log_type' => self::TYPE_CALLBACK,
            'source' => $source,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'environment_id' => $environmentId,
            'request_data' => $requestData,
            'notes' => $notes,
            'ip_address' => $ip,
            'user_agent' => $userAgent,
            'status' => $status ?? self::STATUS_SUCCESS,
        ]);
    }

    /**
     * Create an audit log for transaction lifecycle events.
     */
    public static function logTransactionEvent(Transaction $transaction, string $action, array $metadata = []): self
    {
        return self::create([
            'log_type' => self::TYPE_SYSTEM,
            'source' => 'transaction',
            'action' => $action,
            'entity_type' => Transaction::class,
            'entity_id' => (string) $transaction->getKey(),
            'environment_id' => $transaction->environment_id,
            'user_id' => $transaction->customer_id,
            'response_data' => self::sanitizePayload([
                'id' => $transaction->id,
                'transaction_id' => $transaction->transaction_id,
                'gateway_transaction_id' => $transaction->gateway_transaction_id,
                'order_id' => $transaction->order_id,
                'invoice_id' => $transaction->invoice_id,
                'payment_gateway_setting_id' => $transaction->payment_gateway_setting_id,
                'payment_method' => $transaction->payment_method,
                'status' => $transaction->status,
                'amount' => $transaction->amount,
                'fee_amount' => $transaction->fee_amount,
                'tax_amount' => $transaction->tax_amount,
                'total_amount' => $transaction->total_amount,
                'currency' => $transaction->currency,
                'customer_email' => $transaction->customer_email,
            ]),
            'metadata' => self::sanitizePayload($metadata),
            'notes' => "Transaction {$action}",
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'status' => self::statusFromTransactionStatus($transaction->status),
        ]);
    }

    /**
     * Create an audit log for payment gateway operations.
     */
    public static function logPaymentGatewayOperation(
        string $source,
        string $action,
        ?array $requestData = null,
        ?array $responseData = null,
        ?array $metadata = null,
        ?string $status = null,
        ?string $entityType = null,
        ?string $entityId = null,
        ?int $environmentId = null,
        ?int $userId = null,
        ?string $notes = null
    ): self {
        return self::create([
            'log_type' => self::TYPE_SYSTEM,
            'source' => $source,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'environment_id' => $environmentId,
            'user_id' => $userId,
            'request_data' => self::sanitizePayload($requestData),
            'response_data' => self::sanitizePayload($responseData),
            'metadata' => self::sanitizePayload($metadata),
            'notes' => $notes,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'status' => $status ?? self::STATUS_SUCCESS,
        ]);
    }

    /**
     * Redact sensitive values before storing or notifying.
     */
    public static function sanitizePayload(mixed $payload): mixed
    {
        if ($payload === null) {
            return null;
        }

        if ($payload instanceof Model) {
            $payload = $payload->toArray();
        }

        if (!is_array($payload)) {
            return $payload;
        }

        $sanitized = [];
        foreach ($payload as $key => $value) {
            if (is_string($key) && preg_match('/secret|token|key|password|credential|authorization|signature|pin|otp/i', $key)) {
                $sanitized[$key] = '[redacted]';
                continue;
            }

            $sanitized[$key] = is_array($value) ? self::sanitizePayload($value) : $value;
        }

        return $sanitized;
    }

    private static function statusFromTransactionStatus(?string $status): string
    {
        return match ($status) {
            Transaction::STATUS_COMPLETED => self::STATUS_SUCCESS,
            Transaction::STATUS_FAILED,
            Transaction::STATUS_CANCELLED => self::STATUS_FAILURE,
            default => self::STATUS_WARNING,
        };
    }

    private function sendTelegramNotification(string $event): void
    {
        if (app()->runningUnitTests() || !$this->shouldNotifyTelegram()) {
            return;
        }

        try {
            $telegram = app(TelegramService::class);
            $chatId = $telegram->getChatId();

            if (!$chatId || !config('services.telegram.bot_token')) {
                return;
            }

            $telegram->sendMessage($chatId, $this->toTelegramMessage($telegram, $event), []);
        } catch (\Throwable $e) {
            Log::warning('Failed to send audit log Telegram notification', [
                'audit_log_id' => $this->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function shouldNotifyTelegram(): bool
    {
        $haystack = strtolower(implode(' ', array_filter([
            $this->source,
            $this->action,
            $this->entity_type,
            $this->log_type,
        ])));

        return str_contains($haystack, 'transaction')
            || str_contains($haystack, 'payment')
            || str_contains($haystack, 'gateway')
            || str_contains($haystack, 'webhook')
            || str_contains($haystack, 'callback');
    }

    private function toTelegramMessage(TelegramService $telegram, string $event): string
    {
        $lines = [
            '*Audit Log ' . ucfirst($event) . '*',
            '*ID:* ' . $this->id,
            '*Type:* ' . ($this->log_type ?? 'n/a'),
            '*Source:* ' . ($this->source ?? 'n/a'),
            '*Action:* ' . ($this->action ?? 'n/a'),
            '*Status:* ' . ($this->status ?? 'n/a'),
            '*Entity:* ' . trim(($this->entity_type ?? 'n/a') . ' #' . ($this->entity_id ?? 'n/a')),
            '*Environment:* ' . ($this->environment_id ?? 'n/a'),
            '*User:* ' . ($this->user_id ?? 'n/a'),
        ];

        if ($this->notes) {
            $lines[] = '*Notes:* ' . str($this->notes)->limit(300);
        }

        $lines[] = '*Time:* ' . optional($this->updated_at ?? $this->created_at)->toDateTimeString();

        return collect($lines)
            ->map(fn ($line) => $telegram->escapeMarkdownV2((string) $line))
            ->implode("\n");
    }
}
