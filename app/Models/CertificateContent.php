<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class CertificateContent extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'template_path',
        'fields_config', // JSON configuration for modifiable fields
        'auto_issue', // Whether to automatically issue when course is completed
        'expiry_days', // Number of days after which the certificate expires (null for never)
        'created_by',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fields_config' => 'json',
            'auto_issue' => 'boolean',
            'expiry_days' => 'integer',
        ];
    }

    /**
     * Get the activity that owns this content.
     */
    public function activity(): MorphOne
    {
        return $this->morphOne(Activity::class, 'content');
    }

    /**
     * Get the issued certificates for this certificate content.
     */
    public function issuedCertificates(): HasMany
    {
        return $this->hasMany(IssuedCertificate::class);
    }

    /**
     * Get the user who created this certificate content.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
