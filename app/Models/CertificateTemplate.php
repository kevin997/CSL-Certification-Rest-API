<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
