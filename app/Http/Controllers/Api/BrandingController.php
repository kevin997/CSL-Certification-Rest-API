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
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'favicon' => 'nullable|image|mimes:ico,png|max:1024',
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

        // Handle logo upload
        if ($request->hasFile('logo')) {
            // Delete old logo if exists
            if ($branding->logo_path) {
                Storage::delete($branding->logo_path);
            }

            // Store new logo
            $logoPath = $request->file('logo')->store('branding/logos', 'public');
            $branding->logo_path = $logoPath;
        }

        // Handle favicon upload
        if ($request->hasFile('favicon')) {
            // Delete old favicon if exists
            if ($branding->favicon_path) {
                Storage::delete($branding->favicon_path);
            }

            // Store new favicon
            $faviconPath = $request->file('favicon')->store('branding/favicons', 'public');
            $branding->favicon_path = $faviconPath;
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
            $preview['logo_url'] = Storage::url($logoPath);

            // Schedule cleanup of temporary file
            Storage::deleteDirectory('branding/previews');
        }

        // Handle temporary favicon upload
        if ($request->hasFile('favicon')) {
            $faviconPath = $request->file('favicon')->store('branding/previews', 'public');
            $preview['favicon_url'] = Storage::url($faviconPath);
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
        $domain = $request->query('domain') ?: $request->header('Host');

        // First try to find environment by domain
        $environment = null;
        if ($domain) {
            $environment = Environment::where('primary_domain', $domain)
                ->orWhere(function($query) use ($domain) {
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
                    'company_name' => $branding->company_name,
                    'logo_url' => $branding->logo_path ? Storage::url($branding->logo_path) : null,
                    'favicon_url' => $branding->favicon_path ? Storage::url($branding->favicon_path) : null,
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
            ]);
        }

        // Format branding data for public use
        $publicBranding = [
            'company_name' => $branding->company_name,
            'logo_url' => $branding->logo_path ? Storage::url($branding->logo_path) : null,
            'favicon_url' => $branding->favicon_path ? Storage::url($branding->favicon_path) : null,
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
        ]);
    }
}
