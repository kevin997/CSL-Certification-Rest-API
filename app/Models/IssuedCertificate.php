<?php

namespace App\Models;

use App\Traits\BelongsToEnvironment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class IssuedCertificate extends Model
{
    use HasFactory, SoftDeletes, BelongsToEnvironment;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'certificate_content_id',
        'user_id',
        'environment_id',
        'course_id',
        'certificate_number',
        'issued_date',
        'expiry_date',
        'file_path',
        'status', // active, expired, revoked
        'revoked_reason',
        'revoked_at',
        'revoked_by',
        'custom_fields', // JSON data for custom fields
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_date' => 'datetime',
            'expiry_date' => 'datetime',
            'revoked_at' => 'datetime',
            'custom_fields' => 'json',
        ];
    }

    /**
     * Get the certificate content that this issued certificate belongs to.
     */
    public function certificateContent(): BelongsTo
    {
        return $this->belongsTo(CertificateContent::class);
    }

    /**
     * Get the user who this certificate was issued to.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the course for which this certificate was issued.
     */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Get the user who revoked this certificate (if applicable).
     */
    public function revokedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'revoked_by');
    }
}
