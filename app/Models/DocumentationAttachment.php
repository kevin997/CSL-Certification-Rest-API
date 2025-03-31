<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentationAttachment extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'documentation_content_id',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'created_by',
    ];

    /**
     * Get the documentation content that owns this attachment.
     */
    public function documentationContent(): BelongsTo
    {
        return $this->belongsTo(DocumentationContent::class);
    }

    /**
     * Get the user who created this attachment.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
