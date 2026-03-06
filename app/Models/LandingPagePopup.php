<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LandingPagePopup extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'branding_id',
        'title',
        'content',
        'trigger_type',
        'trigger_value',
        'display_frequency',
        'is_active',
        'start_date',
        'end_date',
        'position',
        'size',
        'background_color',
        'text_color',
        'overlay_color',
        'overlay_opacity',
        'cta_text',
        'cta_url',
        'cta_button_color',
        'image_url',
    ];

    protected function casts(): array
    {
        return [
            'content' => 'array',
            'is_active' => 'boolean',
            'trigger_value' => 'integer',
            'overlay_opacity' => 'integer',
            'start_date' => 'datetime',
            'end_date' => 'datetime',
        ];
    }

    /**
     * Get the branding that owns this popup.
     */
    public function branding(): BelongsTo
    {
        return $this->belongsTo(Branding::class);
    }

    /**
     * Scope: only active popups within their date range.
     */
    public function scopeCurrentlyActive($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('start_date')
                    ->orWhere('start_date', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('end_date')
                    ->orWhere('end_date', '>=', now());
            });
    }
}
