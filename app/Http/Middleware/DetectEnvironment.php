<?php

namespace App\Http\Middleware;

use App\Models\Environment;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DetectEnvironment
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Extract the domain from the request
        $domain = $request->getHost();
        
        // Find the environment that matches this domain
        $environment = Environment::where('primary_domain', $domain)
            ->orWhereJsonContains('additional_domains', $domain)
            ->where('is_active', true)
            ->first();
        
        // If no environment is found, we'll continue without setting one
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
        if ($response instanceof \Illuminate\Http\JsonResponse && $environment) {
            $data = $response->getData(true);
            
            // Only add environment info if it's not already there
            if (!isset($data['environment'])) {
                $environmentData = [
                    'id' => $environment->id,
                    'name' => $environment->name,
                    'primary_domain' => $environment->primary_domain
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
