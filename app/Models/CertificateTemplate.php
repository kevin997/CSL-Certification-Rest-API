<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @OA\Schema(
 *     schema="CertificateTemplate",
 *     type="object",
 *
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="name", type="string", example="Completion Certificate"),
 *     @OA\Property(property="description", type="string", example="Standard completion certificate template"),
 *     @OA\Property(property="filename", type="string", example="completion_template.pdf"),
 *     @OA\Property(property="file_path", type="string", example="certificates/templates/completion_template.pdf"),
 *     @OA\Property(property="thumbnail_path", type="string", example="certificates/templates/thumbnails/completion_template.jpg"),
 *     @OA\Property(property="template_type", type="string", example="completion", description="Type of certificate: completion, achievement, etc."),
 *     @OA\Property(property="is_default", type="boolean", example=true),
 *     @OA\Property(property="created_by", type="integer", format="int64", example=1),
 *     @OA\Property(property="metadata", type="object", example="{'fields': {'name': {'x': 100, 'y': 200}, 'date': {'x': 300, 'y': 400}}}", nullable=true),
 *     @OA\Property(property="remote_id", type="string", example="cert_template_123", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time", example="2025-05-27T14:00:00Z"),
 *     @OA\Property(property="updated_at", type="string", format="date-time", example="2025-05-27T15:00:00Z"),
 *     @OA\Property(property="deleted_at", type="string", format="date-time", example=null, nullable=true)
 * )
 */
class CertificateTemplate extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'environment_id',
        'name',
        'description',
        'filename',
        'file_path',
        'thumbnail_path',
        'template_type',
        'is_default',
        'created_by',
        'metadata',
        'remote_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_default' => 'boolean',
        'metadata' => 'json',
    ];

    /**
     * Get the certificate contents that use this template
     */
    public function certificateContents(): HasMany
    {
        return $this->hasMany(CertificateContent::class);
    }

    /**
     * The environment (tenant) that owns this template.
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }

    /**
     * Restrict to the templates one environment owns.
     *
     * Rows attributed to no environment stay hidden rather than shared: a
     * template of unknown ownership is exactly the case this scope exists to
     * stop leaking. Use certificates:attribute-template-environments to claim
     * them.
     */
    public function scopeForEnvironment(Builder $query, ?int $environmentId): Builder
    {
        if ($environmentId === null) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where('environment_id', $environmentId);
    }

    /**
     * Get the default template of a type, within one environment.
     */
    public static function getDefaultTemplate(string $type = 'completion', ?int $environmentId = null): ?CertificateTemplate
    {
        return self::query()
            ->forEnvironment($environmentId ?? session('current_environment_id'))
            ->where('template_type', $type)
            ->where('is_default', true)
            ->first();
    }
}
