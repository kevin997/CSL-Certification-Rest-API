<?php

namespace App\Http\Controllers\Api\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Environment;
use App\Services\Tax\TaxZoneService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Onboarding",
 *     description="Onboarding validation endpoints"
 * )
 */
class OnboardingController extends Controller
{
    /**
     * Tax zone service instance
     */
    protected TaxZoneService $taxZoneService;

    /**
     * Create a new controller instance.
     */
    public function __construct(TaxZoneService $taxZoneService)
    {
        $this->taxZoneService = $taxZoneService;
    }
    /**
     * Check if email already exists in the user table
     * 
     * @OA\Post(
     *     path="/api/onboarding/validate-email",
     *     operationId="validateEmail",
     *     tags={"Onboarding"},
     *     summary="Check if email already exists",
     *     description="Validates if an email address is already registered in the system",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email"},
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Email validation result",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="available", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Email is available")
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid email format"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function validateEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email format',
                'errors' => $validator->errors()
            ], 422);
        }

        $email = $request->input('email');
        
        // Check if email exists in users table
        $userExists = User::where('email', $email)->exists();
        
        if ($userExists) {
            return response()->json([
                'success' => true,
                'available' => false,
                'message' => 'Email is already registered'
            ], 200);
        }

        return response()->json([
            'success' => true,
            'available' => true,
            'message' => 'Email is available'
        ], 200);
    }

    /**
     * Check if domain/subdomain is available
     * 
     * @OA\Post(
     *     path="/api/onboarding/validate-domain",
     *     operationId="validateDomain",
     *     tags={"Onboarding"},
     *     summary="Check if domain/subdomain is available",
     *     description="Validates if a domain or subdomain is already in use by checking primary_domain and additional_domains in environments table",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"domain", "type"},
     *             @OA\Property(property="domain", type="string", example="john.csl-brands.com"),
     *             @OA\Property(property="type", type="string", enum={"subdomain", "custom"}, example="subdomain")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Domain validation result",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="available", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Domain is available"),
     *             @OA\Property(property="suggestions", type="array", @OA\Items(type="string"), example={"john2.csl-brands.com", "john-dev.csl-brands.com"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid domain format"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function validateDomain(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'domain' => 'required|string|max:255',
            'type' => 'required|in:subdomain,custom'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid domain format',
                'errors' => $validator->errors()
            ], 422);
        }

        $domain = strtolower(trim($request->input('domain')));
        $type = $request->input('type');

        // Prevent localhost from being used
        if (str_contains($domain, 'localhost')) {
            return response()->json([
                'success' => false,
                'message' => 'localhost is not allowed in domain names',
                'errors' => ['domain' => ['localhost is not allowed']]
            ], 422);
        }

        // Additional validation for subdomain format
        if ($type === 'subdomain') {
            if (!preg_match('/^[a-zA-Z0-9-]+\.csl-brands\.com$/', $domain)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Subdomain must be in format: yourname.csl-brands.com',
                    'errors' => ['domain' => ['Invalid subdomain format']]
                ], 422);
            }
        }

        // Check if domain exists in environments table
        $domainExists = Environment::where(function ($query) use ($domain) {
            $query->where('primary_domain', $domain)
                  ->orWhereJsonContains('additional_domains', $domain);
        })->exists();

        if ($domainExists) {
            // Generate suggestions for subdomains
            $suggestions = [];
            if ($type === 'subdomain') {
                $baseName = explode('.', $domain)[0];
                $suggestions = $this->generateSubdomainSuggestions($baseName);
            }

            return response()->json([
                'success' => true,
                'available' => false,
                'message' => 'Domain is already in use',
                'suggestions' => $suggestions
            ], 200);
        }

        return response()->json([
            'success' => true,
            'available' => true,
            'message' => 'Domain is available'
        ], 200);
    }

    /**
     * Generate alternative subdomain suggestions
     * 
     * @param string $baseName
     * @return array
     */
    private function generateSubdomainSuggestions(string $baseName): array
    {
        $suggestions = [];
        $suffixes = ['2', '3', 'dev', 'test', 'demo', 'new', '2024', 'pro'];
        
        foreach ($suffixes as $suffix) {
            $suggestions[] = $baseName . $suffix . '.csl-brands.com';
        }

        // Add some random suggestions
        $randomSuffixes = ['app', 'learn', 'training', 'edu', 'online'];
        foreach ($randomSuffixes as $suffix) {
            $suggestions[] = $baseName . '-' . $suffix . '.csl-brands.com';
        }

        return array_slice($suggestions, 0, 5); // Return top 5 suggestions
    }

    /**
     * Validate both email and domain in a single request
     * 
     * @OA\Post(
     *     path="/api/onboarding/validate",
     *     operationId="validateOnboarding",
     *     tags={"Onboarding"},
     *     summary="Validate email and domain availability",
     *     description="Validates both email and domain/subdomain availability in a single request",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"email", "domain", "type"},
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="domain", type="string", example="john.csl-brands.com"),
     *             @OA\Property(property="type", type="string", enum={"subdomain", "custom"}, example="subdomain")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Validation result",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="email", type="object",
     *                 @OA\Property(property="available", type="boolean", example=true),
     *                 @OA\Property(property="message", type="string", example="Email is available")
     *             ),
     *             @OA\Property(property="domain", type="object",
     *                 @OA\Property(property="available", type="boolean", example=true),
     *                 @OA\Property(property="message", type="string", example="Domain is available"),
     *                 @OA\Property(property="suggestions", type="array", @OA\Items(type="string"))
     *             )
     *         )
     *     )
     * )
     */
    public function validate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'domain' => 'required|string|max:255',
            'type' => 'required|in:subdomain,custom'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $email = $request->input('email');
        $domain = strtolower(trim($request->input('domain')));
        $type = $request->input('type');

        // Validate email
        $userExists = User::where('email', $email)->exists();
        $emailAvailable = !$userExists;

        // Validate domain
        $domainExists = Environment::where(function ($query) use ($domain) {
            $query->where('primary_domain', $domain)
                  ->orWhereJsonContains('additional_domains', $domain);
        })->exists();
        $domainAvailable = !$domainExists;

        // Generate domain suggestions if domain is taken
        $domainSuggestions = [];
        if (!$domainAvailable && $type === 'subdomain') {
            $baseName = explode('.', $domain)[0];
            $domainSuggestions = $this->generateSubdomainSuggestions($baseName);
        }

        return response()->json([
            'success' => true,
            'email' => [
                'available' => $emailAvailable,
                'message' => $emailAvailable ? 'Email is available' : 'Email is already registered'
            ],
            'domain' => [
                'available' => $domainAvailable,
                'message' => $domainAvailable ? 'Domain is available' : 'Domain is already in use',
                'suggestions' => $domainSuggestions
            ]
        ], 200);
    }

    /**
     * Get tax rate for a specific location (open endpoint for onboarding)
     * 
     * @OA\Post(
     *     path="/api/public/tax-rate",
     *     operationId="getTaxRate",
     *     tags={"Onboarding"},
     *     summary="Get tax rate for a specific location",
     *     description="Returns tax rate information for a given country and optional state/province. This is a public endpoint that doesn't require authentication.",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"country_code"},
     *             @OA\Property(property="country_code", type="string", example="US", description="2-letter ISO country code"),
     *             @OA\Property(property="state_code", type="string", example="CA", description="State/province code (optional)")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Tax rate information",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="tax_rate", type="number", format="float", example=8.25, description="Tax rate percentage"),
     *                 @OA\Property(property="commission_rate", type="number", format="float", example=0, description="Commission rate (deprecated, always 0)"),
     *                 @OA\Property(property="tax_zone", type="object",
     *                     @OA\Property(property="id", type="integer", example=1),
     *                     @OA\Property(property="name", type="string", example="California"),
     *                     @OA\Property(property="country_code", type="string", example="US"),
     *                     @OA\Property(property="state_code", type="string", example="CA"),
     *                     @OA\Property(property="rate", type="number", format="float", example=8.25)
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Tax zone not found",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="No tax zone found for the specified location"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="tax_rate", type="number", format="float", example=0),
     *                 @OA\Property(property="commission_rate", type="number", format="float", example=0),
     *                 @OA\Property(property="tax_zone", type="null")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=false),
     *             @OA\Property(property="message", type="string", example="Invalid country code format"),
     *             @OA\Property(property="errors", type="object")
     *         )
     *     )
     * )
     */
    public function getTaxRate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'country_code' => 'required|string|size:2|alpha',
            'state_code' => 'nullable|string|max:10'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid location parameters',
                'errors' => $validator->errors()
            ], 422);
        }

        $countryCode = strtoupper($request->input('country_code'));
        $stateCode = $request->input('state_code') ? strtoupper($request->input('state_code')) : null;

        try {
            // Use the TaxZoneService to find the tax zone
            $taxZone = $this->taxZoneService->findTaxZone($countryCode, $stateCode);

            if ($taxZone) {
                // Tax zone found - return the tax rate information
                return response()->json([
                    'success' => true,
                    'data' => [
                        'tax_rate' => (float) $taxZone->tax_rate,
                        'commission_rate' => 0.0, // Commission is now included in product price
                        'tax_zone' => [
                            'id' => $taxZone->id,
                            'name' => $taxZone->zone_name,
                            'country_code' => $taxZone->country_code,
                            'state_code' => $taxZone->state_code,
                            'rate' => (float) $taxZone->tax_rate
                        ]
                    ]
                ], 200);
            } else {
                // No tax zone found - return 0% tax rate
                return response()->json([
                    'success' => false,
                    'message' => 'No tax zone found for the specified location',
                    'data' => [
                        'tax_rate' => 0.0,
                        'commission_rate' => 0.0,
                        'tax_zone' => null
                    ]
                ], 404);
            }
        } catch (\Exception $e) {
            // Log the error for debugging
            Log::error('Tax rate lookup failed', [
                'country_code' => $countryCode,
                'state_code' => $stateCode,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            // Return error response
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tax rate information',
                'data' => [
                    'tax_rate' => 0.0,
                    'commission_rate' => 0.0,
                    'tax_zone' => null
                ]
            ], 500);
        }
    }
}
