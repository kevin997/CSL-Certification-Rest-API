<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class EnrollmentCode extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'code',
        'status',
        'created_by',
        'used_by',
        'used_at',
        'deactivated_by',
        'deactivated_at',
        'expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'used_at' => 'datetime',
        'deactivated_at' => 'datetime',
        'expires_at' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Get the product that owns the enrollment code.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the user who created the code.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who used the code.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'used_by');
    }

    /**
     * Get the user who deactivated the code.
     */
    public function deactivator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deactivated_by');
    }

    /**
     * Check if the code is expired.
     *
     * @return bool
     */
    public function isExpired(): bool
    {
        if (!$this->expires_at) {
            return false;
        }

        return Carbon::now()->isAfter($this->expires_at);
    }

    /**
     * Check if the code is active and can be used.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && !$this->isExpired();
    }

    /**
     * Mark the code as used.
     *
     * @param int $userId
     * @return void
     */
    public function markAsUsed(int $userId): void
    {
        $this->update([
            'status' => 'used',
            'used_by' => $userId,
            'used_at' => Carbon::now(),
        ]);
    }

    /**
     * Deactivate the code.
     *
     * @param int $userId
     * @return void
     */
    public function deactivate(int $userId): void
    {
        $this->update([
            'status' => 'deactivated',
            'deactivated_by' => $userId,
            'deactivated_at' => Carbon::now(),
        ]);
    }

    /**
     * Update expired codes.
     * This can be called via a scheduled task.
     *
     * @return int Number of codes updated
     */
    public static function updateExpiredCodes(): int
    {
        return self::where('status', 'active')
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', Carbon::now())
            ->update(['status' => 'expired']);
    }

    /**
     * Generate a unique 4-character code.
     *
     * @return string
     */
    public static function generateUniqueCode(): string
    {
        $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $maxAttempts = 100;
        $attempt = 0;

        do {
            $code = '';
            for ($i = 0; $i < 4; $i++) {
                $code .= $characters[random_int(0, strlen($characters) - 1)];
            }

            $attempt++;

            if ($attempt >= $maxAttempts) {
                throw new \RuntimeException('Unable to generate unique enrollment code after ' . $maxAttempts . ' attempts');
            }
        } while (self::where('code', $code)->exists());

        return $code;
    }
}
