<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single chunk of grounding content (blog article paragraph range, or doc
 * excerpt) with its embedding vector, used to retrieval-ground marketing
 * content generation instead of relying on a thin excerpt — see
 * IndexKnowledgeCommand and GenerateMarketingContentCommand.
 *
 * @property string $source_type
 * @property string $source_id
 * @property string|null $url
 * @property string|null $title
 * @property int $chunk_index
 * @property string $content
 * @property array|null $embedding
 * @property string $hash
 */
class ContentChunk extends Model
{
    protected $fillable = [
        'source_type',
        'source_id',
        'url',
        'title',
        'chunk_index',
        'content',
        'embedding',
        'hash',
    ];

    protected $casts = [
        'embedding' => 'array',
    ];
}
