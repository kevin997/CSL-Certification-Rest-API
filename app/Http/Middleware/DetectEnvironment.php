<?php

namespace App\Http\Middleware;

use App\Models\Environment;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
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
        
        // Log::info('DetectEnvironment: Processing request', [
        //     'api_domain' => $apiDomain,
        //     'frontend_domain_header' => $frontendDomainHeader,
        //     'detected_domain' => $domain,
        //     'origin' => $origin,
        //     'referer' => $referer,
        //     'url' => $request->fullUrl()
        // ]);
        
        // Find the environment that matches the domain (cached — avoids a DB hit per request).
        $environment = Environment::findByDomain($domain);

        // If no environment found with the detected domain, try with known platform domains.
        // These are fixed domains for the platform itself (not tenant-owned subdomains).
        // Ideally these would be stored as additional_domains in the DB, but until then we
        // resolve them here and cache the resulting mapping to avoid repeated DB lookups.
        if (!$environment) {
            $knownFrontendDomains = [
                'csl-certification.vercel.app',
                'learning.cfpcsl.com',
                'learning.csl-brands.com',
                'csl-certification-git-develop-kevin997s-projects.vercel.app',
            ];

            foreach ($knownFrontendDomains as $knownDomain) {
                if (strpos($domain, $knownDomain) !== false ||
                    strpos($knownDomain, $domain) !== false) {

                    $environment = Environment::findByDomain($knownDomain);

                    if ($environment) {
                        // Cache this alias mapping so the next request skips the loop entirely.
                        Cache::put("env_by_domain:{$domain}", $environment, 300);
                        break;
                    }
                }
            }
        }
        
        if (!$environment) {
            Log::warning('DetectEnvironment: No environment resolved for domain', [
                'detected_domain' => $domain,
                'frontend_header' => $frontendDomainHeader,
            ]);
            // Do NOT fall back to an arbitrary environment — that silently leaks another
            // tenant's data. Routes that strictly require a resolved environment should
            // use the ResolveEnvironment middleware instead (returns 404 if unresolved).
        } else {
            // Log::info('DetectEnvironment: Environment found', [
            //     'environment_id' => $environment->id,
            //     'environment_name' => $environment->name,
               // 'matched_domain' => $domain
           /// ]);
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
                    // Log::info('DetectEnvironment: Associated user with environment', [
                    //     'user_id' => $request->user()->id,
                    //     'environment_id' => $environment->id
                    // ]);
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