<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class PersonalizationRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'whatsapp_number',
        'academy_name',
        'description',
        'organization_type',
        'niche',
        'status',
    ];
}
