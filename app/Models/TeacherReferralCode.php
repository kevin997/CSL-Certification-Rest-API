<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeacherReferralCode extends Model
{
    protected $fillable = ['referrer_id', 'code', 'reward_type', 'reward_value', 'is_active'];

    protected function casts(): array
    {
        return ['reward_value' => 'decimal:2', 'is_active' => 'boolean'];
    }
}
