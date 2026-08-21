<?php

namespace App\Models;

use App\Traits\BelongsToEnvironment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductLandingPage extends Model
{
    use BelongsToEnvironment, HasFactory;

    protected $fillable = [
        'product_id',
        'environment_id',
        'page_data',
        'seo_title',
        'seo_description',
        'enabled',
    ];

    protected function casts(): array
    {
        return [
            'page_data' => 'array',
            'enabled' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
