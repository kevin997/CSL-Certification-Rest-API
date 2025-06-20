<?php

namespace App\Models;

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
}
