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
        // First try to get domain from Origin or Referer header (for cross-domain requests)
        $domain = null;
        $apiDomain = $request->header('X-Frontend-Domain'); // The API server domain
        
        // Try to get the frontend domain from headers
        $domain = $apiDomain;
        
        // Log::info('DetectEnvironment: Processing request', [
        //     'api_domain' => $apiDomain,
        //     'detected_domain' => $domain,
        //     'url' => $request->fullUrl(),
        //     'headers' => $request->headers->all()
        // ]);
        
        // Find the environment that matches the domain
        $environment = Environment::where('primary_domain', $domain)
            ->orWhereJsonContains('additional_domains', $domain)
            ->where('is_active', true)
            ->first();
        
        // If no environment found with the frontend domain, try with known frontend domains
        if (!$environment) {
            $knownFrontendDomains = [
                'csl-certification.vercel.app',
                'csl-certification-git-develop-kevin997s-projects.vercel.app'
            ];
            
            foreach ($knownFrontendDomains as $frontendDomain) {
                // Check if the request might be coming from this frontend
                if (strpos($origin ?? '', $frontendDomain) !== false || 
                    strpos($referer ?? '', $frontendDomain) !== false) {
                    
                    $environment = Environment::where('primary_domain', $frontendDomain)
                        ->orWhereJsonContains('additional_domains', $frontendDomain)
                        ->where('is_active', true)
                        ->first();
                    
                    if ($environment) {
                        $domain = $frontendDomain;
                        break;
                    }
                }
            }
        }
        
        if ($environment) {
            // Share the environment with all views
            view()->share('environment', $environment);
            
            // Add environment to the request for easy access in controllers
            $request->merge(['environment' => $environment]);
            
            // Store in request instead of session (Laravel 12 preference)
            $request->attributes->set('current_environment_id', $environment->id);
            
            // Also store in session as fallback (safer option)
            session(['current_environment_id' => $environment->id]);
            
            // User association
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
            
            // Only add environment info if it's not already there
            if (!isset($data['environment']) && $environment) {
                $environmentData = [
                    'id' => $environment->id,
                    'name' => $environment->name,
                    'primary_domain' => $environment->primary_domain,
                    'detected_domain' => $domain,
                ];
                
                if (is_array($data)) {
                    $data['environment'] = $environmentData;
                    $response->setData($data);
                }
            } else if (!$environment) {
                // Add debugging information about why no environment was found
                if (is_array($data)) {
                    $data['_debug'] = [
                        'message' => 'No environment found for this domain',
                        'requested_domain' => $domain,
                        'api_domain' => $apiDomain
                    ];
                    $response->setData($data);
                }
            }
        }
        
        return $response;
    }
}