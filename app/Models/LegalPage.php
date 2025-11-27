<?php

namespace App\Models;

use App\Traits\BelongsToEnvironment;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LegalPage extends Model
{
    use HasFactory, SoftDeletes, BelongsToEnvironment;

    /**
     * Valid page types
     */
    public const PAGE_TYPES = [
        'about_us' => 'About Us',
        'privacy_policy' => 'Privacy Policy',
        'legal_notice' => 'Legal Notice',
        'terms_of_service' => 'Terms of Service',
    ];

    /**
     * Default page descriptions
     */
    public const PAGE_DESCRIPTIONS = [
        'about_us' => 'Share your business story, mission, and what makes you unique with your customers.',
        'privacy_policy' => 'Explain how you collect, use, and protect your customers\' personal information.',
        'legal_notice' => 'Provide legal information about your business, contact details, and regulatory compliance.',
        'terms_of_service' => 'Define the rules, rights, and responsibilities for using your products and services.',
    ];

    /**
     * Dynamic tags available for content replacement
     */
    public const DYNAMIC_TAGS = [
        'storeName' => 'The name of your store/company',
        'storeUrl' => 'The URL of your store',
        'ownerName' => 'The name of the owner of the store',
        'lastUpdatedDate' => 'The date of the last update',
        'companyEmail' => 'The company email address',
        'companyPhone' => 'The company phone number',
        'companyAddress' => 'The company address',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'environment_id',
        'user_id',
        'page_type',
        'title',
        'content',
        'seo_title',
        'seo_description',
        'is_published',
        'published_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Get the user who owns this legal page.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the display name for the page type.
     */
    public function getPageTypeNameAttribute(): string
    {
        return self::PAGE_TYPES[$this->page_type] ?? $this->page_type;
    }

    /**
     * Get the description for the page type.
     */
    public function getPageTypeDescriptionAttribute(): string
    {
        return self::PAGE_DESCRIPTIONS[$this->page_type] ?? '';
    }

    /**
     * Process content with dynamic tags replaced.
     */
    public function getProcessedContentAttribute(): string
    {
        if (!$this->content) {
            return '';
        }

        $content = $this->content;
        $environment = $this->environment;
        $branding = $environment ? Branding::where('environment_id', $environment->id)->first() : null;
        $user = $this->user;

        $replacements = [
            '{{storeName}}' => $branding?->company_name ?? $environment?->name ?? 'Our Company',
            '{{storeUrl}}' => $environment?->primary_domain ? 'https://' . $environment->primary_domain : url('/'),
            '{{ownerName}}' => $user?->name ?? 'Owner',
            '{{lastUpdatedDate}}' => $this->updated_at?->format('F j, Y') ?? now()->format('F j, Y'),
            '{{companyEmail}}' => $user?->email ?? '',
            '{{companyPhone}}' => '',
            '{{companyAddress}}' => '',
        ];

        return str_replace(array_keys($replacements), array_values($replacements), $content);
    }

    /**
     * Scope a query to only include published pages.
     */
    public function scopePublished($query)
    {
        return $query->where('is_published', true);
    }

    /**
     * Scope a query to filter by page type.
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('page_type', $type);
    }

    /**
     * Check if the page type is valid.
     */
    public static function isValidPageType(string $type): bool
    {
        return array_key_exists($type, self::PAGE_TYPES);
    }

    /**
     * Get all page types with their metadata.
     */
    public static function getPageTypesWithMetadata(): array
    {
        $types = [];
        foreach (self::PAGE_TYPES as $key => $name) {
            $types[] = [
                'type' => $key,
                'name' => $name,
                'description' => self::PAGE_DESCRIPTIONS[$key] ?? '',
            ];
        }
        return $types;
    }
}
