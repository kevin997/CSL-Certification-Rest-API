<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One open of a protected document, recorded for the audit trail.
 */
class DocumentAccessLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'documentation_attachment_id',
        'environment_id',
        'user_id',
        'ip_hash',
        'user_agent',
    ];

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(DocumentationAttachment::class, 'documentation_attachment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
