<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeacherReferral extends Model
{
    protected $fillable = [
        'teacher_referral_code_id', 'referrer_id', 'referred_user_id', 'referred_environment_id',
        'qualifying_checkout_id', 'qualifying_transaction_id', 'status', 'reward_type',
        'reward_value', 'reward_amount', 'currency', 'qualified_at', 'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'reward_value' => 'decimal:2', 'reward_amount' => 'decimal:2',
            'qualified_at' => 'datetime', 'reversed_at' => 'datetime',
        ];
    }

    public function referredUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_user_id');
    }
}
