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
            $environment = Environment::where('primary_domain', $domain)
        ->orWhere(function($query) use ($domain) {
            $query->whereNotNull('additional_domains')
                  ->whereJsonContains('additional_domains', $domain);
        })
        ->where('is_active', true)
        ->first();
                
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
                    'company_name' => $branding->company_name,
                    'logo_url' => $branding->logo_path ? url('storage/' . $branding->logo_path) : null,
                    'favicon_url' => $branding->favicon_path ? url('storage/' . $branding->favicon_path) : null,
                    'primary_color' => $branding->primary_color,
                    'secondary_color' => $branding->secondary_color,
                    'accent_color' => $branding->accent_color,
                    'font_family' => $branding->font_family,
                    'custom_css' => $branding->custom_css,
                    'custom_js' => $branding->custom_js,
                    'environment_id' => $environmentId,
                ];
                
                $response->setContent(json_encode($content));
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
}
