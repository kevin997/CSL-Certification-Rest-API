<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branding;
use App\Models\Environment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;

/**
 * @OA\Schema(
 *     schema="Branding",
 *     required={"user_id"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="user_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="company_name", type="string", example="CSL Certification", nullable=true),
 *     @OA\Property(property="logo_path", type="string", example="branding/logos/company-logo.png", nullable=true),
 *     @OA\Property(property="favicon_path", type="string", example="branding/favicons/favicon.ico", nullable=true),
 *     @OA\Property(property="primary_color", type="string", example="#3498db", nullable=true),
 *     @OA\Property(property="secondary_color", type="string", example="#2ecc71", nullable=true),
 *     @OA\Property(property="accent_color", type="string", example="#e74c3c", nullable=true),
 *     @OA\Property(property="font_family", type="string", example="Roboto, sans-serif", nullable=true),
 *     @OA\Property(property="custom_css", type="string", example=".header { background-color: #f8f9fa; }", nullable=true),
 *     @OA\Property(property="custom_js", type="string", example="console.log('Custom JS loaded');", nullable=true),
 *     @OA\Property(property="custom_domain", type="string", example="learn.mycompany.com", nullable=true),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(
 *         property="user",
 *         ref="#/components/schemas/User"
 *     )
 * )
 */
class BrandingController extends Controller
{
    /**
     * Display the current user's branding settings.
     *
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/branding",
     *     summary="Get user's branding settings",
     *     description="Returns the authenticated user's branding settings",
     *     operationId="getUserBranding",
     *     tags={"Branding"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/Branding")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Branding settings not found"
     *     )
     * )
     */
    public function index()
    {
        $branding = Branding::where('user_id', Auth::id())->first();

        if (!$branding) {
            return response()->json([
                'status' => 'success',
                'data' => null,
                'message' => 'No branding settings found',
            ]);
        }

        return response()->json([
            'status' => 'success',
            'data' => $branding,
        ]);
    }

    /**
     * Store or update branding settings.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/branding",
     *     summary="Store or update branding settings",
     *     description="Creates or updates the authenticated user's branding settings",
     *     operationId="storeBranding",
     *     tags={"Branding"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         description="Branding data",
     *         @OA\JsonContent(
     *             @OA\Property(property="company_name", type="string", example="CSL Certification"),
     *             @OA\Property(property="primary_color", type="string", example="#3498db"),
     *             @OA\Property(property="secondary_color", type="string", example="#2ecc71"),
     *             @OA\Property(property="accent_color", type="string", example="#e74c3c"),
     *             @OA\Property(property="font_family", type="string", example="Roboto, sans-serif"),
     *             @OA\Property(property="custom_css", type="string", example=".header { background-color: #f8f9fa; }"),
     *             @OA\Property(property="custom_js", type="string", example="console.log('Custom JS loaded');"),
     *             @OA\Property(property="custom_domain", type="string", example="learn.mycompany.com"),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Branding settings updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Branding settings updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Branding")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:255',
            'logo_url' => 'nullable|string|url',
            'favicon_url' => 'nullable|string|url',
            'primary_color' => 'nullable|string|max:7',
            'secondary_color' => 'nullable|string|max:7',
            'accent_color' => 'nullable|string|max:7',
            'font_family' => 'nullable|string|max:100',
            'custom_css' => 'nullable|string',
            'custom_js' => 'nullable|string',
            'custom_domain' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Find existing branding or create new
        $branding = Branding::firstOrNew(['user_id' => Auth::id()]);
        $branding->company_name = $request->company_name;

        // Handle logo URL
        if ($request->has('logo_url')) {
            $branding->logo_path = $request->logo_url;
        }

        // Handle favicon URL
        if ($request->has('favicon_url')) {
            $branding->favicon_path = $request->favicon_url;
        }

        // Update other fields if provided
        if ($request->has('primary_color')) {
            $branding->primary_color = $request->primary_color;
        }

        if ($request->has('secondary_color')) {
            $branding->secondary_color = $request->secondary_color;
        }

        if ($request->has('accent_color')) {
            $branding->accent_color = $request->accent_color;
        }

        if ($request->has('font_family')) {
            $branding->font_family = $request->font_family;
        }

        if ($request->has('custom_css')) {
            $branding->custom_css = $request->custom_css;
        }

        if ($request->has('custom_js')) {
            $branding->custom_js = $request->custom_js;
        }

        if ($request->has('custom_domain')) {
            $branding->custom_domain = $request->custom_domain;
        }

        if ($request->has('is_active')) {
            $branding->is_active = $request->is_active;
        } else {
            $branding->is_active = true;
        }

        $branding->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Branding settings saved successfully',
            'data' => $branding,
        ]);
    }

    /**
     * Reset branding settings to default.
     *
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/branding/reset",
     *     summary="Reset branding settings",
     *     description="Resets the authenticated user's branding settings to default values",
     *     operationId="resetBranding",
     *     tags={"Branding"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Branding settings reset successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Branding settings reset successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Branding")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Branding settings not found"
     *     )
     * )
     */
    public function reset()
    {
        $branding = Branding::where('user_id', Auth::id())->first();

        if (!$branding) {
            return response()->json([
                'status' => 'error',
                'message' => 'No branding settings found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Delete logo and favicon files
        if ($branding->logo_path) {
            Storage::delete($branding->logo_path);
        }

        if ($branding->favicon_path) {
            Storage::delete($branding->favicon_path);
        }

        // Delete branding record
        $branding->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Branding settings reset to default',
        ]);
    }

    /**
     * Preview branding settings.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/branding/preview",
     *     summary="Get branding preview",
     *     description="Returns a preview of the authenticated user's branding settings",
     *     operationId="getBrandingPreview",
     *     tags={"Branding"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="company_name", type="string", example="CSL Certification"),
     *                 @OA\Property(property="logo_url", type="string", example="https://example.com/storage/branding/logos/company-logo.png"),
     *                 @OA\Property(property="favicon_url", type="string", example="https://example.com/storage/branding/favicons/favicon.ico"),
     *                 @OA\Property(property="primary_color", type="string", example="#3498db"),
     *                 @OA\Property(property="secondary_color", type="string", example="#2ecc71"),
     *                 @OA\Property(property="accent_color", type="string", example="#e74c3c"),
     *                 @OA\Property(property="font_family", type="string", example="Roboto, sans-serif"),
     *                 @OA\Property(property="custom_css", type="string", example=".header { background-color: #f8f9fa; }"),
     *                 @OA\Property(property="custom_js", type="string", example="console.log('Custom JS loaded');")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Branding settings not found"
     *     )
     * )
     */
    public function preview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:255',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'favicon' => 'nullable|image|mimes:ico,png|max:1024',
            'primary_color' => 'nullable|string|max:7',
            'secondary_color' => 'nullable|string|max:7',
            'accent_color' => 'nullable|string|max:7',
            'font_family' => 'nullable|string|max:100',
            'custom_css' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Create temporary preview data
        $preview = [
            'company_name' => $request->company_name,
            'primary_color' => $request->primary_color,
            'secondary_color' => $request->secondary_color,
            'accent_color' => $request->accent_color,
            'font_family' => $request->font_family,
            'custom_css' => $request->custom_css,
        ];

        // Handle temporary logo upload
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('branding/previews', 'public');
            $preview['logo_url'] = $logoPath;

            // Schedule cleanup of temporary file
            Storage::deleteDirectory('branding/previews');
        }

        // Handle temporary favicon upload
        if ($request->hasFile('favicon')) {
            $faviconPath = $request->file('favicon')->store('branding/previews', 'public');
            $preview['favicon_url'] = $faviconPath;
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Preview generated successfully',
            'data' => $preview,
        ]);
    }

    /**
     * Get public branding settings for a specific domain.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/branding/public",
     *     summary="Get public branding settings",
     *     description="Returns the public branding settings for a specific domain",
     *     operationId="getPublicBranding",
     *     tags={"Branding"},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="company_name", type="string", example="CSL Certification"),
     *                 @OA\Property(property="logo_url", type="string", example="https://example.com/storage/branding/logos/company-logo.png"),
     *                 @OA\Property(property="favicon_url", type="string", example="https://example.com/storage/branding/favicons/favicon.ico"),
     *                 @OA\Property(property="primary_color", type="string", example="#3498db"),
     *                 @OA\Property(property="secondary_color", type="string", example="#2ecc71"),
     *                 @OA\Property(property="accent_color", type="string", example="#e74c3c"),
     *                 @OA\Property(property="font_family", type="string", example="Roboto, sans-serif"),
     *                 @OA\Property(property="custom_css", type="string", example=".header { background-color: #f8f9fa; }"),
     *                 @OA\Property(property="custom_js", type="string", example="console.log('Custom JS loaded');")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Branding settings not found or not active"
     *     )
     * )
     */
    public function getPublicBranding(Request $request)
    {
        // Try to get domain from headers in priority order, matching DetectEnvironment middleware
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

        // If still no domain, fall back to the API domain or query parameter
        if (!$domain) {
            $domain = $request->query('domain') ?: $apiDomain;
        }

        // First try to find environment by domain
        $environment = null;
        if ($domain) {
            $environment = Environment::where('primary_domain', $domain)
                ->orWhere(function ($query) use ($domain) {
                    $query->whereNotNull('additional_domains')
                        ->whereJsonContains('additional_domains', $domain);
                })
                ->where('is_active', true)
                ->first();
        }

        // If environment found, get branding by environment_id
        if ($environment) {
            $branding = Branding::where('environment_id', $environment->id)
                ->where('is_active', true)
                ->first();

            if ($branding) {
                // Format branding data for public use
                $publicBranding = [
                    'id' => $branding->id,
                    'company_name' => $branding->company_name,
                    'logo_url' => $branding->logo_path ?: null,
                    'favicon_url' => $branding->favicon_path ?: null,
                    'primary_color' => $branding->primary_color,
                    'secondary_color' => $branding->secondary_color,
                    'accent_color' => $branding->accent_color,
                    'font_family' => $branding->font_family,
                    'custom_css' => $branding->custom_css,
                    'custom_js' => $branding->custom_js,
                    'environment_id' => $environment->id,
                ];

                return response()->json([
                    'status' => 'success',
                    'data' => $publicBranding,
                    'environment' => [
                        'id' => $environment->id,
                        'name' => $environment->name,
                        'primary_domain' => $environment->primary_domain,
                    ],
                ]);
            }
        }

        // Fallback: Find branding by custom domain (legacy approach)
        $branding = Branding::where('custom_domain', $domain)
            ->where('is_active', true)
            ->first();

        if (!$branding) {
            // Return default branding
            return response()->json([
                'status' => 'success',
                'data' => [
                    'company_name' => 'CSL',
                    'logo_url' => null,
                    'favicon_url' => null,
                    'primary_color' => '#0db002',
                    'secondary_color' => '#38c172',
                    'accent_color' => '#e3342f',
                    'font_family' => 'Roboto, sans-serif',
                    'custom_css' => null,
                    'custom_js' => null,
                    'environment_id' => null,
                ],
                'environment' => $environment ? [
                    'id' => $environment->id,
                    'name' => $environment->name,
                    'primary_domain' => $environment->primary_domain,
                ] : null,
            ]);
        }

        // Format branding data for public use
        $publicBranding = [
            'id' => $branding->id,
            'company_name' => $branding->company_name,
            'logo_url' => $branding->logo_path ?: null,
            'favicon_url' => $branding->favicon_path ?: null,
            'primary_color' => $branding->primary_color,
            'secondary_color' => $branding->secondary_color,
            'accent_color' => $branding->accent_color,
            'font_family' => $branding->font_family,
            'custom_css' => $branding->custom_css,
            'custom_js' => $branding->custom_js,
            'environment_id' => $environment->id,
        ];

        return response()->json([
            'status' => 'success',
            'data' => $publicBranding,
            'environment' => $environment ? [
                'id' => $environment->id,
                'name' => $environment->name,
                'primary_domain' => $environment->primary_domain,
            ] : null,
        ]);
    }

    /**
     * Create or update branding for a given environment.
     *
     * Route: PUT /api/environments/{id}/branding
     */
    public function upsertForEnvironment(Request $request, int $id)
    {
        $environment = Environment::findOrFail($id);

        // Authorization: allow teachers, or environment owner
        if (!$request->user()->isTeacher() && $environment->owner_id !== $request->user()->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'company_name' => 'required|string|max:255',
            'logo_url' => 'nullable|string|url',
            'favicon_url' => 'nullable|string|url',
            'primary_color' => 'nullable|string|max:7',
            'secondary_color' => 'nullable|string|max:7',
            'accent_color' => 'nullable|string|max:7',
            'font_family' => 'nullable|string|max:100',
            'custom_css' => 'nullable|string',
            'custom_js' => 'nullable|string',
            'custom_domain' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $branding = Branding::firstOrNew([
            'environment_id' => $environment->id,
        ]);

        // Preserve original creator if already set, otherwise set to current user
        if (!$branding->exists || !$branding->user_id) {
            $branding->user_id = Auth::id();
        }

        $branding->company_name = $request->company_name;

        if ($request->has('logo_url')) {
            $branding->logo_path = $request->logo_url;
        }

        if ($request->has('favicon_url')) {
            $branding->favicon_path = $request->favicon_url;
        }

        if ($request->has('primary_color')) {
            $branding->primary_color = $request->primary_color;
        }

        if ($request->has('secondary_color')) {
            $branding->secondary_color = $request->secondary_color;
        }

        if ($request->has('accent_color')) {
            $branding->accent_color = $request->accent_color;
        }

        if ($request->has('font_family')) {
            $branding->font_family = $request->font_family;
        }

        if ($request->has('custom_css')) {
            $branding->custom_css = $request->custom_css;
        }

        if ($request->has('custom_js')) {
            $branding->custom_js = $request->custom_js;
        }

        if ($request->has('custom_domain')) {
            $branding->custom_domain = $request->custom_domain;
        }

        if ($request->has('is_active')) {
            $branding->is_active = $request->is_active;
        } else {
            $branding->is_active = true;
        }

        $branding->environment_id = $environment->id;
        $branding->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Branding settings saved successfully',
            'data' => $branding,
        ]);
    }

    /**
     * Get landing page configuration for a branding record.
     *
     * @OA\Get(
     *     path="/branding/{id}/landing-page",
     *     summary="Get landing page configuration",
     *     description="Returns the landing page configuration for a specific branding record",
     *     operationId="getLandingPageConfig",
     *     tags={"Branding"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Branding ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Branding not found")
     * )
     */
    public function getLandingPageConfig(int $id)
    {
        $branding = Branding::where('user_id', Auth::id())->find($id);

        if (!$branding) {
            return response()->json([
                'status' => 'error',
                'message' => 'Branding not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $branding->getLandingPageConfig(),
        ]);
    }

    /**
     * Update landing page configuration for a branding record.
     *
     * @OA\Put(
     *     path="/branding/{id}/landing-page",
     *     summary="Update landing page configuration",
     *     description="Updates the landing page configuration for a specific branding record",
     *     operationId="updateLandingPageConfig",
     *     tags={"Branding"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Branding ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="hero_title", type="string", maxLength=255),
     *             @OA\Property(property="hero_subtitle", type="string"),
     *             @OA\Property(property="hero_background_image", type="string", maxLength=500),
     *             @OA\Property(property="hero_overlay_color", type="string", maxLength=7),
     *             @OA\Property(property="hero_overlay_opacity", type="integer", minimum=0, maximum=100),
     *             @OA\Property(property="hero_cta_text", type="string", maxLength=100),
     *             @OA\Property(property="hero_cta_url", type="string", maxLength=500),
     *             @OA\Property(property="landing_page_sections", type="array", @OA\Items(type="object")),
     *             @OA\Property(property="seo_title", type="string", maxLength=255),
     *             @OA\Property(property="seo_description", type="string")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", type="object"),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Branding not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function updateLandingPageConfig(Request $request, int $id)
    {
        $branding = Branding::where('user_id', Auth::id())->find($id);

        if (!$branding) {
            return response()->json([
                'status' => 'error',
                'message' => 'Branding not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'hero_title' => 'nullable|string|max:255',
            'hero_subtitle' => 'nullable|string',
            'hero_background_image' => 'nullable|string|max:500',
            'hero_overlay_color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'hero_overlay_opacity' => 'nullable|integer|min:0|max:100',
            'hero_cta_text' => 'nullable|string|max:100',
            'hero_cta_url' => 'nullable|string|max:500',
            'landing_page_sections' => 'nullable|array',
            'landing_page_sections.*.id' => 'nullable|string',
            'landing_page_sections.*.type' => 'required_with:landing_page_sections|string|in:text,features,testimonials,cta,featured_products,custom',
            'landing_page_sections.*.content' => 'nullable|string', // Content is optional for block-based sections
            'landing_page_sections.*.order' => 'nullable|integer',
            'landing_page_sections.*.settings' => 'nullable|array',
            'landing_page_sections.*.settings.title' => 'nullable|string|max:255',
            'landing_page_sections.*.settings.subtitle' => 'nullable|string|max:500',
            'landing_page_sections.*.settings.layout' => 'nullable|string',
            'landing_page_sections.*.settings.padding' => 'nullable|string|in:small,medium,large',
            'landing_page_sections.*.settings.background_color' => 'nullable|string|max:7',
            'landing_page_sections.*.settings.testimonials' => 'nullable|array',
            'landing_page_sections.*.settings.testimonials.*.id' => 'nullable|string',
            'landing_page_sections.*.settings.testimonials.*.name' => 'nullable|string|max:255',
            'landing_page_sections.*.settings.testimonials.*.role' => 'nullable|string|max:255',
            'landing_page_sections.*.settings.testimonials.*.content' => 'nullable|string',
            'landing_page_sections.*.settings.testimonials.*.rating' => 'nullable|integer|min:1|max:5',
            'landing_page_sections.*.settings.features' => 'nullable|array',
            'landing_page_sections.*.settings.features.*.icon' => 'nullable|string',
            'landing_page_sections.*.settings.features.*.title' => 'nullable|string|max:255',
            'landing_page_sections.*.settings.features.*.description' => 'nullable|string',
            'landing_page_sections.*.settings.maxProducts' => 'nullable|integer|min:1|max:12',
            'landing_page_sections.*.settings.primaryButtonText' => 'nullable|string|max:100',
            'landing_page_sections.*.settings.primaryButtonUrl' => 'nullable|string|max:500',
            'landing_page_sections.*.settings.secondaryButtonText' => 'nullable|string|max:100',
            'landing_page_sections.*.settings.secondaryButtonUrl' => 'nullable|string|max:500',
            'seo_title' => 'nullable|string|max:255',
            'seo_description' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $branding->update($validator->validated());

        return response()->json([
            'status' => 'success',
            'data' => $branding->getLandingPageConfig(),
            'message' => 'Landing page configuration updated successfully',
        ]);
    }

    /**
     * Toggle landing page enabled status.
     *
     * @OA\Post(
     *     path="/branding/{id}/landing-page/toggle",
     *     summary="Toggle landing page",
     *     description="Enables or disables the landing page for a specific branding record",
     *     operationId="toggleLandingPage",
     *     tags={"Branding"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Branding ID",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"enabled"},
     *             @OA\Property(property="enabled", type="boolean")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="landing_page_enabled", type="boolean")
     *             ),
     *             @OA\Property(property="message", type="string")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Branding not found"),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function toggleLandingPage(Request $request, int $id)
    {
        $branding = Branding::where('user_id', Auth::id())->find($id);

        if (!$branding) {
            return response()->json([
                'status' => 'error',
                'message' => 'Branding not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'enabled' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $branding->update([
            'landing_page_enabled' => $request->boolean('enabled'),
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'landing_page_enabled' => $branding->landing_page_enabled,
            ],
            'message' => $branding->landing_page_enabled
                ? 'Landing page enabled successfully'
                : 'Landing page disabled successfully',
        ]);
    }

    /**
     * Get public landing page configuration based on domain.
     *
     * @OA\Get(
     *     path="/branding/public/landing-page",
     *     summary="Get public landing page",
     *     description="Returns the public landing page configuration based on domain",
     *     operationId="getPublicLandingPage",
     *     tags={"Branding"},
     *     @OA\Parameter(
     *         name="domain",
     *         in="query",
     *         description="Domain to get landing page for",
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", type="object")
     *         )
     *     ),
     *     @OA\Response(response=404, description="Landing page not found or not enabled")
     * )
     */
    public function getPublicLandingPage(Request $request)
    {
        // Get domain from request header or query parameter
        $domain = $request->header('X-Frontend-Domain')
            ?? $request->header('X-Forwarded-Host')
            ?? $request->query('domain')
            ?? $request->getHost();

        // Clean the domain
        $domain = preg_replace('/:\d+$/', '', $domain);

        // Find environment by domain
        $environment = Environment::where('primary_domain', $domain)
            ->orWhere('primary_domain', 'LIKE', '%' . $domain . '%')
            ->first();

        if (!$environment) {
            return response()->json([
                'status' => 'error',
                'message' => 'Environment not found for domain',
            ], 404);
        }

        // Get branding with landing page enabled
        $branding = Branding::where('environment_id', $environment->id)
            ->where('landing_page_enabled', true)
            ->where('is_active', true)
            ->first();

        if (!$branding) {
            return response()->json([
                'status' => 'error',
                'message' => 'Landing page not enabled for this environment',
                'data' => [
                    'enabled' => false,
                ],
            ], 404);
        }

        // Return landing page config with branding
        return response()->json([
            'status' => 'success',
            'data' => [
                'enabled' => true,
                'landing_page' => $branding->getLandingPageConfig(),
                'branding' => [
                    'company_name' => $branding->company_name,
                    'logo_url' => $branding->logo_path,
                    'primary_color' => $branding->primary_color,
                    'secondary_color' => $branding->secondary_color,
                    'accent_color' => $branding->accent_color,
                    'font_family' => $branding->font_family,
                ],
            ],
            'environment' => [
                'id' => $environment->id,
                'name' => $environment->name,
            ],
        ]);
    }
}
