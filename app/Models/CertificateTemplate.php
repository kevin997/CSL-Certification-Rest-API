<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @OA\Schema(
 *     schema="CertificateTemplate",
 *     type="object",
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
     * Get the default template for a specific type
     *
     * @param string $type
     * @return CertificateTemplate|null
     */
    public static function getDefaultTemplate(string $type = 'completion'): ?CertificateTemplate
    {
        return self::where('template_type', $type)
            ->where('is_default', true)
            ->first();
    }
}
