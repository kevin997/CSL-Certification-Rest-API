<?php

namespace App\Models;

use App\Traits\BelongsToEnvironment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class SalesFormSubmission extends Model
{
    use HasFactory, BelongsToEnvironment;

    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'sales_form_id',
        'environment_id',
        'user_id',
        'access_code',
        'answers',
        'name',
        'email',
        'phone',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'answers' => 'array',
        ];
    }

    public function salesForm(): BelongsTo
    {
        return $this->belongsTo(SalesForm::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Generate a unique 8-character access code.
     */
    public static function generateUniqueAccessCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (static::where('access_code', $code)->exists());

        return $code;
    }
}
