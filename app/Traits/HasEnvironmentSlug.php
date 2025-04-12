<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait HasEnvironmentSlug
{
    /**
     * Boot the trait.
     *
     * @return void
     */
    public static function bootHasEnvironmentSlug()
    {
        static::creating(function (Model $model) {
            if (isset($model->name) && !isset($model->slug)) {
                $model->slug = static::generateUniqueSlug($model);
            }
        });

        static::updating(function (Model $model) {
            // Only update slug if name has changed and slug hasn't been manually set
            if ($model->isDirty('name') && !$model->isDirty('slug')) {
                $model->slug = static::generateUniqueSlug($model);
            }
        });
    }

    /**
     * Generate a unique slug for the model.
     *
     * @param Model $model
     * @return string
     */
    protected static function generateUniqueSlug(Model $model): string
    {
        $slug = Str::slug($model->name);
        $environmentId = $model->environment_id ?? null;
        
        // If environment ID exists, append it to make the slug unique per environment
        if ($environmentId) {
            $baseSlug = $slug;
            $slug = "{$baseSlug}-{$environmentId}";
        }
        
        // Check for uniqueness
        $originalSlug = $slug;
        $count = 1;
        
        // Make sure the slug is unique
        while (static::where('slug', $slug)
            ->where('id', '!=', $model->id ?? 0)
            ->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }
        
        return $slug;
    }
    
    /**
     * Get the display slug without the environment ID.
     *
     * @return string
     */
    public function getDisplaySlugAttribute(): string
    {
        if (!$this->slug) {
            return '';
        }
        
        // Remove the environment ID part from the slug for display purposes
        if ($this->environment_id) {
            return preg_replace("/-{$this->environment_id}(-\d+)?$/", '', $this->slug);
        }
        
        return $this->slug;
    }
}
