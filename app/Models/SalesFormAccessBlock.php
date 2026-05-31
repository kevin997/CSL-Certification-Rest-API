<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesFormAccessBlock extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_form_id',
        'course_id',
        'block_id',
        'activity_id',
    ];

    public function salesForm(): BelongsTo
    {
        return $this->belongsTo(SalesForm::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(Block::class);
    }

    public function activity(): BelongsTo
    {
        return $this->belongsTo(Activity::class);
    }
}
