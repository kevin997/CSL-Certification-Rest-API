<?php

namespace App\Models;

use App\Traits\BelongsToEnvironment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branding extends Model
{
    use HasFactory, SoftDeletes, BelongsToEnvironment;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'environment_id',
        'company_name',
        'logo_path',
        'favicon_path',
        'primary_color',
        'secondary_color',
        'accent_color',
        'font_family',
        'custom_css',
        'landing_page_enabled',
        'hero_title',
        'hero_subtitle',
        'hero_background_image',
        'hero_overlay_color',
        'hero_overlay_opacity',
        'hero_cta_text',
        'hero_cta_url',
        'landing_page_sections',
        'seo_title',
        'seo_description',
        'custom_js',
        'custom_domain',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'landing_page_enabled' => 'boolean',
            'landing_page_sections' => 'array',
            'hero_overlay_opacity' => 'integer',
        ];
    }

    /**
     * Get the landing page configuration as an array.
     */
    public function getLandingPageConfig(): array
    {
        return [
            'enabled' => $this->landing_page_enabled,
            'hero' => [
                'title' => $this->hero_title,
                'subtitle' => $this->hero_subtitle,
                'background_image' => $this->hero_background_image,
                'overlay_color' => $this->hero_overlay_color,
                'overlay_opacity' => $this->hero_overlay_opacity,
                'cta_text' => $this->hero_cta_text,
                'cta_url' => $this->hero_cta_url,
            ],
            'sections' => $this->landing_page_sections ?? [],
            'seo' => [
                'title' => $this->seo_title,
                'description' => $this->seo_description,
            ],
        ];
    }

    /**
     * Get the user who owns this branding.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the environment this branding belongs to.
     */
    public function environment(): BelongsTo
    {
        return $this->belongsTo(Environment::class);
    }
}
