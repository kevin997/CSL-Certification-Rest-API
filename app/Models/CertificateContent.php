<?php

namespace App\Models;

use App\Traits\HasCreatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class CertificateContent extends Model
{
    use HasFactory, SoftDeletes, HasCreatedBy;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'activity_id',
        'description',
        'template_path',
        'certificate_template_id', // Foreign key to the certificate_templates table
        'fields_config', // JSON configuration for modifiable fields
        'completion_criteria', // JSON configuration for completion criteria
        'auto_issue', // Whether to automatically issue when course is completed
        'expiry_period', // Number of days/months/years after which the certificate expires (null for never)
        'expiry_period_unit', // Unit for expiry period (days, months, years)
        'metadata', // JSON metadata for the certificate (e.g., generated certificates, user data)
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
            'completion_criteria' => 'json',
            'auto_issue' => 'boolean',
            'expiry_period' => 'integer',
            'certificate_template_id' => 'integer',
            'metadata' => 'json',
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
