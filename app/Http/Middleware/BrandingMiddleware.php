<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Branding;
use App\Models\Environment;
use Illuminate\Support\Facades\Auth;

class BrandingMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        // Only process JSON responses
        if (!$this->isJsonResponse($response)) {
            return $response;
        }

        // Get the current environment from the request
        $environmentId = null;
        
        // Check for environment in the token abilities
        if (Auth::check()) {
            $user = Auth::user();
            $token = $request->bearerToken();
            
            if ($token) {
                $tokenId = explode('|', $token)[0];
                $tokenModel = $user->tokens()->find($tokenId);
                
                if ($tokenModel) {
                    foreach ($tokenModel->abilities as $ability) {
                        if (strpos($ability, 'environment_id:') === 0) {
                            $environmentId = (int) substr($ability, strlen('environment_id:'));
                            break;
                        }
                    }
                }
            }
        }
        
        // If no environment found in token, try to get it from the domain
        if (!$environmentId) {
            $domain = $request->headers->get('X-Frontend-Domain');

            // Validate and sanitize domain input
            if ($domain && $this->isValidDomain($domain)) {
                $environment = Environment::where('primary_domain', $domain)
            ->orWhere(function($query) use ($domain) {
                $query->whereNotNull('additional_domains')
                      ->whereJsonContains('additional_domains', $domain);
            })
            ->where('is_active', true)
            ->first();
            } else {
                $environment = null;
            }
                
            if ($environment) {
                $environmentId = $environment->id;
            }
        }

        // Get branding based on environment
        $branding = null;
        if ($environmentId) {
            $branding = Branding::where('environment_id', $environmentId)
                ->where('is_active', true)
                ->first();
        } elseif (Auth::check()) {
            // Fallback to user's branding if no environment
            $branding = Branding::where('user_id', Auth::id())
                ->where('is_active', true)
                ->first();
        }

        if ($branding) {
            // For JSON responses, we'll modify the response content
            $content = json_decode($response->getContent(), true);

            // Add branding data to the response
            if (is_array($content)) {
                $content['branding'] = [
                    'company_name' => $this->sanitizeString($branding->company_name),
                    'logo_url' => $branding->logo_path ? url('storage/' . $this->sanitizePath($branding->logo_path)) : null,
                    'favicon_url' => $branding->favicon_path ? url('storage/' . $this->sanitizePath($branding->favicon_path)) : null,
                    'primary_color' => $this->sanitizeColor($branding->primary_color),
                    'secondary_color' => $this->sanitizeColor($branding->secondary_color),
                    'accent_color' => $this->sanitizeColor($branding->accent_color),
                    'font_family' => $this->sanitizeFontFamily($branding->font_family),
                    'custom_css' => $this->sanitizeCSS($branding->custom_css),
                    'custom_js' => null, // Remove JS for security - handle separately if needed
                    'environment_id' => $environmentId,
                ];

                $response->setContent(json_encode($content, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            }
        }

        return $response;
    }
    
    /**
     * Check if the response is a JSON response.
     *
     * @param  \Illuminate\Http\Response  $response
     * @return bool
     */
    protected function isJsonResponse($response)
    {
        return $response->headers->get('Content-Type') === 'application/json';
    }

    /**
     * Validate domain format
     *
     * @param string $domain
     * @return bool
     */
    protected function isValidDomain($domain)
    {
        return filter_var('http://' . $domain, FILTER_VALIDATE_URL) !== false;
    }

    /**
     * Sanitize string input
     *
     * @param string|null $input
     * @return string|null
     */
    protected function sanitizeString($input)
    {
        if (!$input) return null;
        return htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sanitize file path
     *
     * @param string|null $path
     * @return string|null
     */
    protected function sanitizePath($path)
    {
        if (!$path) return null;
        // Remove any directory traversal attempts
        $path = str_replace(['../', '../', '.\\', '..\\'], '', $path);
        return preg_replace('/[^a-zA-Z0-9_\-\.\/]/', '', $path);
    }

    /**
     * Sanitize color value
     *
     * @param string|null $color
     * @return string|null
     */
    protected function sanitizeColor($color)
    {
        if (!$color) return null;
        // Only allow hex colors or CSS color names
        if (preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ||
            preg_match('/^#[0-9A-Fa-f]{3}$/', $color) ||
            preg_match('/^[a-zA-Z]+$/', $color)) {
            return $color;
        }
        return null;
    }

    /**
     * Sanitize font family
     *
     * @param string|null $fontFamily
     * @return string|null
     */
    protected function sanitizeFontFamily($fontFamily)
    {
        if (!$fontFamily) return null;
        // Only allow alphanumeric characters, spaces, commas, and hyphens
        return preg_replace('/[^a-zA-Z0-9\s,\-]/', '', $fontFamily);
    }

    /**
     * Sanitize CSS input
     *
     * @param string|null $css
     * @return string|null
     */
    protected function sanitizeCSS($css)
    {
        if (!$css) return null;

        // Remove potentially dangerous CSS constructs
        $css = preg_replace('/<script[\s\S]*?>[\s\S]*?<\/script>/i', '', $css);
        $css = preg_replace('/javascript:/i', '', $css);
        $css = preg_replace('/expression\s*\(/i', '', $css);
        $css = preg_replace('/vbscript:/i', '', $css);
        $css = preg_replace('/data:/i', '', $css);
        $css = preg_replace('/@import/i', '', $css);
        $css = preg_replace('/url\s*\(/i', '', $css);

        return $css;
    }
}
