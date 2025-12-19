<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicEnvironmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'primary_domain' => $this->primary_domain,
            'branding' => [
                'logo_url' => $this->branding?->logo_path 
                    ? (str_starts_with($this->branding->logo_path, 'http') 
                        ? $this->branding->logo_path 
                        : asset(\Illuminate\Support\Facades\Storage::url($this->branding->logo_path)))
                    : null,
                'primary_color' => $this->branding?->primary_color ?? $this->theme_color,
                'secondary_color' => $this->branding?->secondary_color,
                'hero_background_image' => $this->branding?->hero_background_image,
            ],
            'niche' => $this->niche,
            'country_code' => $this->country_code,
        ];
    }
}
