<?php

namespace App\Models;

use App\Traits\HasCreatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class DocumentationAttachment extends Model
{
    use HasCreatedBy, HasFactory, SoftDeletes;

    protected $fillable = [
        'documentation_content_id',
        'uuid',
        'environment_id',
        'public_id',
        'source_url',
        'checksum',
        'file_path',
        'file_name',
        'file_type',
        'file_size',
        'created_by',
        'last_accessed_at',
    ];

    protected $hidden = [
        'source_url',
        'file_path',
        'public_id',
    ];

    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'last_accessed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $attachment) {
            if (! $attachment->uuid) {
                $attachment->uuid = (string) Str::uuid();
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function documentationContent(): BelongsTo
    {
        return $this->belongsTo(DocumentationContent::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    public function accessLogs(): HasMany
    {
        return $this->hasMany(DocumentAccessLog::class);
    }

    public function resolvedEnvironmentId(): ?int
    {
        if ($this->environment_id) {
            return (int) $this->environment_id;
        }

        $content = $this->documentationContent;
        $activity = $content?->activity
            ?: ($content?->activity_id ? Activity::query()->find($content->activity_id) : null);
        $template = $activity?->block?->template;

        return $template?->environment_id ? (int) $template->environment_id : null;
    }
}
