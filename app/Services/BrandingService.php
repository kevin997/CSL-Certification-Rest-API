<?php

namespace App\Services;

use App\Models\Branding;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BrandingService
{
    /**
     * Get all branding profiles
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllBrandingProfiles(array $filters = [])
    {
        $query = Branding::query();
        
        // Apply filters
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }
        
        if (isset($filters['is_active'])) {
            $query->where('is_active', $filters['is_active']);
        }
        
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        // Apply sorting
        $sortField = $filters['sort_field'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $query->orderBy($sortField, $sortDirection);
        
        return $query->get();
    }
    
    /**
     * Get branding profile by ID
     *
     * @param int $id
     * @return Branding|null
     */
    public function getBrandingById(int $id): ?Branding
    {
        return Branding::find($id);
    }
    
    /**
     * Get user's active branding profile
     *
     * @param int $userId
     * @return Branding|null
     */
    public function getUserActiveBranding(int $userId): ?Branding
    {
        return Branding::where('user_id', $userId)
            ->where('is_active', true)
            ->first();
    }
    
    /**
     * Create a new branding profile
     *
     * @param array $data
     * @return Branding|null
     */
    public function createBranding(array $data): ?Branding
    {
        // Process logo if provided
        if (isset($data['logo']) && $data['logo']) {
            $data['logo'] = $this->processLogoUpload($data['logo']);
        }
        
        // Process colors
        if (isset($data['colors']) && is_array($data['colors'])) {
            $data['colors'] = json_encode($data['colors']);
        }
        
        // Process fonts
        if (isset($data['fonts']) && is_array($data['fonts'])) {
            $data['fonts'] = json_encode($data['fonts']);
        }
        
        // Process custom CSS
        if (isset($data['custom_css'])) {
            $data['custom_css'] = $this->sanitizeCSS($data['custom_css']);
        }
        
        // Process custom JS
        if (isset($data['custom_js'])) {
            $data['custom_js'] = $this->sanitizeJS($data['custom_js']);
        }
        
        // Set as active if requested
        if (isset($data['set_as_active']) && $data['set_as_active']) {
            // Deactivate all other branding profiles for this user
            Branding::where('user_id', $data['user_id'])
                ->update(['is_active' => false]);
            
            $data['is_active'] = true;
        }
        
        // Create branding profile
        return Branding::create($data);
    }
    
    /**
     * Update a branding profile
     *
     * @param int $id
     * @param array $data
     * @return Branding|null
     */
    public function updateBranding(int $id, array $data): ?Branding
    {
        $branding = $this->getBrandingById($id);
        
        if (!$branding) {
            return null;
        }
        
        // Process logo if provided
        if (isset($data['logo']) && $data['logo']) {
            // Delete old logo if exists
            if ($branding->logo) {
                $this->deleteLogoFile($branding->logo);
            }
            
            $data['logo'] = $this->processLogoUpload($data['logo']);
        }
        
        // Process colors
        if (isset($data['colors']) && is_array($data['colors'])) {
            $data['colors'] = json_encode($data['colors']);
        }
        
        // Process fonts
        if (isset($data['fonts']) && is_array($data['fonts'])) {
            $data['fonts'] = json_encode($data['fonts']);
        }
        
        // Process custom CSS
        if (isset($data['custom_css'])) {
            $data['custom_css'] = $this->sanitizeCSS($data['custom_css']);
        }
        
        // Process custom JS
        if (isset($data['custom_js'])) {
            $data['custom_js'] = $this->sanitizeJS($data['custom_js']);
        }
        
        // Set as active if requested
        if (isset($data['set_as_active']) && $data['set_as_active']) {
            // Deactivate all other branding profiles for this user
            Branding::where('user_id', $branding->user_id)
                ->where('id', '!=', $id)
                ->update(['is_active' => false]);
            
            $data['is_active'] = true;
        }
        
        // Update branding profile
        $branding->update($data);
        
        return $branding;
    }
    
    /**
     * Delete a branding profile
     *
     * @param int $id
     * @return bool
     */
    public function deleteBranding(int $id): bool
    {
        $branding = $this->getBrandingById($id);
        
        if (!$branding) {
            return false;
        }
        
        // Delete logo file if exists
        if ($branding->logo) {
            $this->deleteLogoFile($branding->logo);
        }
        
        return $branding->delete();
    }
    
    /**
     * Set branding profile as active
     *
     * @param int $id
     * @return Branding|null
     */
    public function setAsActive(int $id): ?Branding
    {
        $branding = $this->getBrandingById($id);
        
        if (!$branding) {
            return null;
        }
        
        // Deactivate all other branding profiles for this user
        Branding::where('user_id', $branding->user_id)
            ->where('id', '!=', $id)
            ->update(['is_active' => false]);
        
        // Activate this branding profile
        $branding->update(['is_active' => true]);
        
        return $branding;
    }
    
    /**
     * Apply branding to course
     *
     * @param int $brandingId
     * @param int $courseId
     * @return bool
     */
    public function applyBrandingToCourse(int $brandingId, int $courseId): bool
    {
        $branding = $this->getBrandingById($brandingId);
        $course = Course::find($courseId);
        
        if (!$branding || !$course) {
            return false;
        }
        
        // Check if user has permission to apply branding to this course
        if ($branding->user_id !== $course->user_id) {
            return false;
        }
        
        // Apply branding to course
        $course->update([
            'branding_id' => $brandingId,
            'metadata' => json_encode([
                'branding' => [
                    'applied_at' => now()->format('Y-m-d H:i:s'),
                    'profile_name' => $branding->name
                ]
            ])
        ]);
        
        return true;
    }
    
    /**
     * Remove branding from course
     *
     * @param int $courseId
     * @return bool
     */
    public function removeBrandingFromCourse(int $courseId): bool
    {
        $course = Course::find($courseId);
        
        if (!$course) {
            return false;
        }
        
        // Remove branding from course
        $course->update([
            'branding_id' => null,
            'metadata' => json_encode([
                'branding' => [
                    'removed_at' => now()->format('Y-m-d H:i:s')
                ]
            ])
        ]);
        
        return true;
    }
    
    /**
     * Get branding CSS
     *
     * @param int $id
     * @return string
     */
    public function getBrandingCSS(int $id): string
    {
        $branding = $this->getBrandingById($id);
        
        if (!$branding) {
            return '';
        }
        
        $css = "/* Branding CSS for {$branding->name} */\n";
        
        // Add colors
        if ($branding->colors) {
            $colors = json_decode($branding->colors, true);
            
            $css .= ":root {\n";
            
            foreach ($colors as $key => $value) {
                $css .= "  --{$key}: {$value};\n";
            }
            
            $css .= "}\n\n";
        }
        
        // Add fonts
        if ($branding->fonts) {
            $fonts = json_decode($branding->fonts, true);
            
            if (isset($fonts['primary'])) {
                $css .= "body, .font-primary {\n";
                $css .= "  font-family: {$fonts['primary']};\n";
                $css .= "}\n\n";
            }
            
            if (isset($fonts['secondary'])) {
                $css .= "h1, h2, h3, h4, h5, h6, .font-secondary {\n";
                $css .= "  font-family: {$fonts['secondary']};\n";
                $css .= "}\n\n";
            }
        }
        
        // Add custom CSS
        if ($branding->custom_css) {
            $css .= $branding->custom_css;
        }
        
        return $css;
    }
    
    /**
     * Get branding JS
     *
     * @param int $id
     * @return string
     */
    public function getBrandingJS(int $id): string
    {
        $branding = $this->getBrandingById($id);
        
        if (!$branding) {
            return '';
        }
        
        $js = "/* Branding JS for {$branding->name} */\n";
        
        // Add custom JS
        if ($branding->custom_js) {
            $js .= $branding->custom_js;
        }
        
        return $js;
    }
    
    /**
     * Process logo upload
     *
     * @param mixed $logo
     * @return string|null
     */
    protected function processLogoUpload($logo): ?string
    {
        // In a real application, this would handle file uploads
        // For this demo, we'll just return a placeholder URL
        
        $filename = 'logo_' . Str::random(16) . '.png';
        $path = 'branding/logos/' . $filename;
        
        // Simulating file storage
        return $path;
    }
    
    /**
     * Delete logo file
     *
     * @param string $path
     * @return bool
     */
    protected function deleteLogoFile(string $path): bool
    {
        // In a real application, this would delete the file
        // For this demo, we'll just return true
        
        return true;
    }
    
    /**
     * Sanitize CSS
     *
     * @param string $css
     * @return string
     */
    protected function sanitizeCSS(string $css): string
    {
        // In a real application, this would sanitize CSS to prevent XSS
        // For this demo, we'll just return the CSS as is
        
        return $css;
    }
    
    /**
     * Sanitize JS
     *
     * @param string $js
     * @return string
     */
    protected function sanitizeJS(string $js): string
    {
        // In a real application, this would sanitize JS to prevent XSS
        // For this demo, we'll just return the JS as is
        
        return $js;
    }
    
    /**
     * Get default branding settings
     *
     * @return array
     */
    public function getDefaultBrandingSettings(): array
    {
        return [
            'colors' => [
                'primary' => '#3490dc',
                'secondary' => '#6574cd',
                'accent' => '#f6993f',
                'success' => '#38c172',
                'danger' => '#e3342f',
                'warning' => '#ffed4a',
                'info' => '#6cb2eb',
                'background' => '#f8fafc',
                'text' => '#2d3748'
            ],
            'fonts' => [
                'primary' => 'Roboto, sans-serif',
                'secondary' => 'Montserrat, sans-serif'
            ]
        ];
    }
    
    /**
     * Get branding validation rules
     *
     * @return array
     */
    public function getValidationRules(): array
    {
        return [
            'user_id' => 'required|integer|exists:users,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'logo' => 'nullable|string',
            'colors' => 'nullable',
            'fonts' => 'nullable',
            'custom_css' => 'nullable|string',
            'custom_js' => 'nullable|string',
            'is_active' => 'boolean',
            'set_as_active' => 'boolean'
        ];
    }
}
