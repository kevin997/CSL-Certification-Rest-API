<?php

namespace App\Http\Middleware;

use App\Models\Environment;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class DetectEnvironment
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Try to get domain from headers in priority order
        $domain = null;
        $apiDomain = $request->getHost(); // The API server domain
        
        // First check for the explicit X-Frontend-Domain header
        $frontendDomainHeader = $request->header('X-Frontend-Domain');
        
        // Then try Origin or Referer as fallbacks
        $origin = $request->header('Origin');
        $referer = $request->header('Referer');
        
        if ($frontendDomainHeader) {
            // Use the explicit frontend domain header if provided
            $domain = $frontendDomainHeader;
        } elseif ($origin) {
            // Extract domain from Origin
            $parsedOrigin = parse_url($origin);
            $domain = $parsedOrigin['host'] ?? null;
        } elseif ($referer) {
            // Extract domain from Referer as fallback
            $parsedReferer = parse_url($referer);
            $domain = $parsedReferer['host'] ?? null;
        }
        
        // If still no domain, fall back to the API domain
        if (!$domain) {
            $domain = $apiDomain;
        }
        
        Log::info('DetectEnvironment: Processing request', [
            'api_domain' => $apiDomain,
            'frontend_domain_header' => $frontendDomainHeader,
            'detected_domain' => $domain,
            'origin' => $origin,
            'referer' => $referer,
            'url' => $request->fullUrl()
        ]);
        
        // Find the environment that matches the domain
        $environment = Environment::where('primary_domain', $domain)
            ->orWhereJsonContains('additional_domains', $domain)
            ->where('is_active', true)
            ->first();
        
        // If no environment found with the detected domain, try with known frontend domains
        if (!$environment) {
            $knownFrontendDomains = [
                'csl-certification.vercel.app',
                'learning.cfpcsl.com',
                'learning.csl-brands.com',
                'csl-certification-git-develop-kevin997s-projects.vercel.app'
            ];
            
            // First check if detected domain is similar to a known domain (partial match)
            foreach ($knownFrontendDomains as $frontendDomain) {
                if (strpos($domain, $frontendDomain) !== false || 
                    strpos($frontendDomain, $domain) !== false) {
                    
                    $environment = Environment::where('primary_domain', $frontendDomain)
                        ->orWhereJsonContains('additional_domains', $frontendDomain)
                        ->where('is_active', true)
                        ->first();
                    
                    if ($environment) {
                        Log::info('DetectEnvironment: Found environment by partial domain match', [
                            'detected_domain' => $domain,
                            'matched_domain' => $frontendDomain
                        ]);
                        break;
                    }
                }
            }
        }
        
        // Log the SQL query for debugging
        if (!$environment) {
            $query = Environment::where('primary_domain', $domain)
                ->orWhereJsonContains('additional_domains', $domain)
                ->where('is_active', true)
                ->toSql();
            
            Log::info('DetectEnvironment: Query used', [
                'sql' => $query,
                'domain' => $domain
            ]);
            
            // Check all environments to see if there's any partial match
            $allEnvironments = Environment::where('is_active', true)->get(['id', 'name', 'primary_domain', 'additional_domains']);
            
            Log::info('DetectEnvironment: Available environments', [
                'count' => $allEnvironments->count(),
                'environments' => $allEnvironments->toArray()
            ]);
            
            // Fallback to first active environment
            $environment = $allEnvironments->first();
            if ($environment) {
                Log::info('DetectEnvironment: Using fallback environment', [
                    'environment_id' => $environment->id,
                    'environment_name' => $environment->name
                ]);
            }
        } else {
            Log::info('DetectEnvironment: Environment found', [
                'environment_id' => $environment->id,
                'environment_name' => $environment->name,
                'matched_domain' => $domain
            ]);
        }
        
        if ($environment) {
            // Share the environment with all views
            view()->share('environment', $environment);
            
            // Add environment to the request for easy access in controllers
            $request->merge(['environment' => $environment]);
            
            // Store environment in session for persistence
            session(['current_environment_id' => $environment->id]);
            
            // If user is authenticated, associate them with this environment if not already
            if ($request->user()) {
                // Check if the user is already associated with this environment
                $existingAssociation = $request->user()->environments()
                    ->where('environment_id', $environment->id)
                    ->exists();
                
                // If not, create the association
                if (!$existingAssociation) {
                    $request->user()->environments()->attach($environment->id, [
                        'joined_at' => now(),
                    ]);
                    Log::info('DetectEnvironment: Associated user with environment', [
                        'user_id' => $request->user()->id,
                        'environment_id' => $environment->id
                    ]);
                }
                
                // Store the environment credentials context for the auth provider
                $environmentCredentials = $request->user()->getEnvironmentCredentials($environment->id);
                if ($environmentCredentials) {
                    session([
                        'environment_credentials' => [
                            'environment_id' => $environment->id,
                            'email' => $environmentCredentials->email,
                        ]
                    ]);
                }
            }
        }
        
        // Process the request
        $response = $next($request);
        
        // Add environment information to API responses
        if ($response instanceof \Illuminate\Http\JsonResponse) {
            $data = $response->getData(true);
            
            if (!isset($data['environment'])) {
                $environmentData = $environment ? [
                    'id' => $environment->id,
                    'is_demo' => $environment->is_demo,
                    'name' => $environment->name,
                    'primary_domain' => $environment->primary_domain,
                    'detected_domain' => $domain,
                    'header_domain' => $frontendDomainHeader,
                ] : [
                    'message' => 'No environment found',
                    'detected_domain' => $domain,
                    'header_domain' => $frontendDomainHeader,
                ];
                
                if (is_array($data)) {
                    $data['environment'] = $environmentData;
                    $response->setData($data);
                }
            }
        }
        
        return $response;
    }
}