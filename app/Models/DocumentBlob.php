<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The bytes of a protected document, stored base64-encoded so the same schema
 * works on MySQL and SQLite. Only the streaming endpoint reads it.
 */
class DocumentBlob extends Model
{
    protected $fillable = [
        'documentation_attachment_id',
        'payload',
        'mime_type',
    ];

    protected $hidden = [
        'payload',
    ];

    public function attachment(): BelongsTo
    {
        return $this->belongsTo(DocumentationAttachment::class, 'documentation_attachment_id');
    }

    public function bytes(): ?string
    {
        $decoded = base64_decode((string) $this->payload, true);

        return $decoded === false ? null : $decoded;
    }
}
