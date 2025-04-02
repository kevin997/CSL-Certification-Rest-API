<?php

namespace App\Models;

use App\Traits\BelongsToEnvironment;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class File extends Model
{
    use HasFactory, BelongsToEnvironment;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'title',
        'description',
        'file_type',
        'file_size',
        'file_url',
        'public_id',
        'resource_type',
        'environment_id',
    ];

    /**
     * Get the environment that owns the file.
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }
}
