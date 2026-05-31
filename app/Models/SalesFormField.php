<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class SalesFormField extends Model
{
    use HasFactory;

    const TYPE_SHORT_TEXT = 'short_text';
    const TYPE_LONG_TEXT = 'long_text';
    const TYPE_EMAIL = 'email';
    const TYPE_PHONE = 'phone';
    const TYPE_COUNTRY = 'country';
    const TYPE_STATE = 'state';
    const TYPE_CITY = 'city';
    const TYPE_PRODUCT_SELECT = 'product_select';
    const TYPE_CALENDAR = 'calendar';

    protected $fillable = [
        'sales_form_id',
        'type',
        'field_key',
        'label',
        'placeholder',
        'help_text',
        'is_required',
        'order',
        'options',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'order' => 'integer',
            'options' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (SalesFormField $field) {
            if (!$field->field_key) {
                $field->field_key = Str::slug($field->label, '_') ?: ($field->type . '_' . Str::random(4));
            }
        });
    }

    public function salesForm(): BelongsTo
    {
        return $this->belongsTo(SalesForm::class);
    }
}
