<?php

namespace App\Models;

use App\Traits\BelongsToEnvironment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * "Notify me" interest in a not-yet-implemented integration
 * (registered from the /settings/integrations catalog). Used to
 * gauge demand and notify users when the integration ships.
 */
class IntegrationInterest extends Model
{
    use HasFactory, BelongsToEnvironment;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'environment_id',
        'user_id',
        'integration_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
