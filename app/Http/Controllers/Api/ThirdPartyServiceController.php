<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\ThirdPartyService;
use App\Services\CertificateGenerationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ThirdPartyServiceController extends Controller
{
    /**
     * Display a listing of third party services.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        $query = ThirdPartyService::query();

        // Filter by service type if provided
        if ($request->has('service_type')) {
            $query->where('service_type', $request->service_type);
        }

        // Filter by active status if provided
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $services = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $services,
        ]);
    }

    /**
     * Store a newly created third party service.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'base_url' => 'nullable|url',
            'api_key' => 'nullable|string',
            'api_secret' => 'nullable|string',
            'bearer_token' => 'nullable|string',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
            'is_active' => 'boolean',
            'service_type' => 'required|string|max:255',
            'config' => 'nullable|array',
            'environment_id' => 'nullable|integer|exists:environments,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $service = ThirdPartyService::create($request->all());

        Log::info('Third party service created', [
            'service_id' => $service->id,
            'service_type' => $service->service_type,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Third party service created successfully',
            'data' => $service,
        ], 201);
    }

    /**
     * Display the specified third party service.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $service = ThirdPartyService::find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Third party service not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $service,
        ]);
    }

    /**
     * Update the specified third party service.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        $service = ThirdPartyService::find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Third party service not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'base_url' => 'nullable|url',
            'api_key' => 'nullable|string',
            'api_secret' => 'nullable|string',
            'bearer_token' => 'nullable|string',
            'username' => 'nullable|string',
            'password' => 'nullable|string',
            'is_active' => 'boolean',
            'service_type' => 'sometimes|required|string|max:255',
            'config' => 'nullable|array',
            'environment_id' => 'nullable|integer|exists:environments,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $service->update($request->all());

        Log::info('Third party service updated', [
            'service_id' => $service->id,
            'service_type' => $service->service_type,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Third party service updated successfully',
            'data' => $service,
        ]);
    }

    /**
     * Remove the specified third party service.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        $service = ThirdPartyService::find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Third party service not found',
            ], 404);
        }

        Log::info('Third party service deleted', [
            'service_id' => $service->id,
            'service_type' => $service->service_type,
        ]);

        $service->delete();

        return response()->json([
            'success' => true,
            'message' => 'Third party service deleted successfully',
        ]);
    }

    /**
     * Refresh the authentication token for a service.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function refreshToken($id)
    {
        $service = ThirdPartyService::find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Third party service not found',
            ], 404);
        }

        // Handle different service types
        switch ($service->service_type) {
            case 'certificate_generation':
                $certificateService = new CertificateGenerationService();
                $success = $certificateService->authenticate();

                if ($success) {
                    // Reload the service to get the updated token
                    $service->refresh();

                    return response()->json([
                        'success' => true,
                        'message' => 'Token refreshed successfully',
                        'data' => $service,
                    ]);
                } else {
                    return response()->json([
                        'success' => false,
                        'message' => 'Failed to refresh token. Check service credentials.',
                    ], 500);
                }

            default:
                return response()->json([
                    'success' => false,
                    'message' => 'Token refresh not implemented for this service type',
                ], 400);
        }
    }

    /**
     * Test the connection to a third party service.
     *
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function testConnection($id)
    {
        $service = ThirdPartyService::find($id);

        if (!$service) {
            return response()->json([
                'success' => false,
                'message' => 'Third party service not found',
            ], 404);
        }

        try {
            // Handle different service types
            switch ($service->service_type) {
                case 'certificate_generation':
                    $certificateService = new CertificateGenerationService();
                    $success = $certificateService->authenticate();

                    return response()->json([
                        'success' => $success,
                        'message' => $success ? 'Connection successful' : 'Connection failed',
                        'service_type' => $service->service_type,
                    ]);

                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Connection test not implemented for this service type',
                    ], 400);
            }
        } catch (\Exception $e) {
            Log::error('Third party service connection test failed', [
                'service_id' => $service->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Connection test failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available service types.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getServiceTypes()
    {
        $types = [
            [
                'value' => 'certificate_generation',
                'label' => 'Certificate Generation Service',
                'description' => 'Service for generating and managing certificates',
            ],
            [
                'value' => 'payment_gateway',
                'label' => 'Payment Gateway',
                'description' => 'Third party payment processing service',
            ],
            [
                'value' => 'email_service',
                'label' => 'Email Service',
                'description' => 'External email delivery service',
            ],
            [
                'value' => 'sms_service',
                'label' => 'SMS Service',
                'description' => 'SMS notification service',
            ],
            [
                'value' => 'analytics',
                'label' => 'Analytics Service',
                'description' => 'Third party analytics and tracking',
            ],
            [
                'value' => 'storage',
                'label' => 'Storage Service',
                'description' => 'Cloud storage service',
            ],
            [
                'value' => 'whatsapp',
                'label' => 'WhatsApp',
                'description' => 'WhatsApp group or number for learner communication',
            ],
            [
                'value' => 'other',
                'label' => 'Other',
                'description' => 'Other third party service',
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $types,
        ]);
    }

    /**
     * Get public WhatsApp configuration for the current environment.
     * Used by learners to see the WhatsApp button in the study room.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getWhatsAppConfig(Request $request)
    {
        // Detect environment from domain headers (same pattern as BrandingController::getPublicBranding)
        $domain = null;

        $frontendDomainHeader = $request->header('X-Frontend-Domain');
        $origin = $request->header('Origin');
        $referer = $request->header('Referer');

        if ($frontendDomainHeader) {
            $domain = $frontendDomainHeader;
        } elseif ($origin) {
            $parsedOrigin = parse_url($origin);
            $domain = $parsedOrigin['host'] ?? null;
        } elseif ($referer) {
            $parsedReferer = parse_url($referer);
            $domain = $parsedReferer['host'] ?? null;
        }

        if (!$domain) {
            $domain = $request->getHost();
        }

        // Find environment by domain
        $environment = Environment::where('primary_domain', $domain)
            ->orWhere(function ($query) use ($domain) {
                $query->whereNotNull('additional_domains')
                    ->whereJsonContains('additional_domains', $domain);
            })
            ->where('is_active', true)
            ->first();

        if (!$environment) {
            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        }

        // Query without global scopes to avoid session-based filtering on public route
        $service = ThirdPartyService::withoutGlobalScopes()
            ->where('service_type', 'whatsapp')
            ->where('environment_id', $environment->id)
            ->where('is_active', true)
            ->first();

        if (!$service) {
            return response()->json([
                'success' => true,
                'data' => null,
            ]);
        }

        // Return only safe config (no credentials)
        $config = $service->config ?? [];

        return response()->json([
            'success' => true,
            'data' => [
                'type' => $config['type'] ?? 'group',
                'value' => $config['value'] ?? '',
                'welcome_message' => $config['welcome_message'] ?? '',
                'is_active' => $service->is_active,
            ],
        ]);
    }
}
