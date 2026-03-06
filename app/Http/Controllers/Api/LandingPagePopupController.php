<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branding;
use App\Models\LandingPagePopup;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Response;

class LandingPagePopupController extends Controller
{
    /**
     * Find branding owned by the current user.
     */
    private function findBranding(int $brandingId): ?Branding
    {
        $branding = Branding::where('user_id', Auth::id())->find($brandingId);

        if (!$branding) {
            // Try finding by environment owner
            $branding = Branding::whereHas('environment', function ($query) {
                $query->where('owner_id', Auth::id());
            })->find($brandingId);
        }

        return $branding;
    }

    /**
     * List all popups for a branding record.
     */
    public function index(int $brandingId)
    {
        $branding = $this->findBranding($brandingId);

        if (!$branding) {
            return response()->json([
                'status' => 'error',
                'message' => 'Branding not found',
            ], 404);
        }

        $popups = $branding->popups()->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $popups,
        ]);
    }

    /**
     * Create a new popup.
     */
    public function store(Request $request, int $brandingId)
    {
        $branding = $this->findBranding($brandingId);

        if (!$branding) {
            return response()->json([
                'status' => 'error',
                'message' => 'Branding not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'content' => 'nullable|array',
            'trigger_type' => 'required|string|in:time_delay,scroll_percentage,exit_intent,page_load',
            'trigger_value' => 'nullable|integer|min:0',
            'display_frequency' => 'required|string|in:once,every_visit,once_per_session',
            'is_active' => 'nullable|boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after:start_date',
            'position' => 'nullable|string|in:center,bottom-right,bottom-left,top,full-screen',
            'size' => 'nullable|string|in:small,medium,large',
            'background_color' => 'nullable|string|max:7',
            'text_color' => 'nullable|string|max:7',
            'overlay_color' => 'nullable|string|max:7',
            'overlay_opacity' => 'nullable|integer|min:0|max:100',
            'cta_text' => 'nullable|string|max:100',
            'cta_url' => 'nullable|string|max:500',
            'cta_button_color' => 'nullable|string|max:7',
            'image_url' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $popup = $branding->popups()->create($validator->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Popup created successfully',
            'data' => $popup,
        ], 201);
    }

    /**
     * Show a specific popup.
     */
    public function show(int $brandingId, int $popupId)
    {
        $branding = $this->findBranding($brandingId);

        if (!$branding) {
            return response()->json([
                'status' => 'error',
                'message' => 'Branding not found',
            ], 404);
        }

        $popup = $branding->popups()->find($popupId);

        if (!$popup) {
            return response()->json([
                'status' => 'error',
                'message' => 'Popup not found',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $popup,
        ]);
    }

    /**
     * Update a popup.
     */
    public function update(Request $request, int $brandingId, int $popupId)
    {
        $branding = $this->findBranding($brandingId);

        if (!$branding) {
            return response()->json([
                'status' => 'error',
                'message' => 'Branding not found',
            ], 404);
        }

        $popup = $branding->popups()->find($popupId);

        if (!$popup) {
            return response()->json([
                'status' => 'error',
                'message' => 'Popup not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'title' => 'sometimes|required|string|max:255',
            'content' => 'nullable|array',
            'trigger_type' => 'sometimes|string|in:time_delay,scroll_percentage,exit_intent,page_load',
            'trigger_value' => 'nullable|integer|min:0',
            'display_frequency' => 'sometimes|string|in:once,every_visit,once_per_session',
            'is_active' => 'nullable|boolean',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date',
            'position' => 'nullable|string|in:center,bottom-right,bottom-left,top,full-screen',
            'size' => 'nullable|string|in:small,medium,large',
            'background_color' => 'nullable|string|max:7',
            'text_color' => 'nullable|string|max:7',
            'overlay_color' => 'nullable|string|max:7',
            'overlay_opacity' => 'nullable|integer|min:0|max:100',
            'cta_text' => 'nullable|string|max:100',
            'cta_url' => 'nullable|string|max:500',
            'cta_button_color' => 'nullable|string|max:7',
            'image_url' => 'nullable|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $popup->update($validator->validated());

        return response()->json([
            'status' => 'success',
            'message' => 'Popup updated successfully',
            'data' => $popup->fresh(),
        ]);
    }

    /**
     * Delete a popup.
     */
    public function destroy(int $brandingId, int $popupId)
    {
        $branding = $this->findBranding($brandingId);

        if (!$branding) {
            return response()->json([
                'status' => 'error',
                'message' => 'Branding not found',
            ], 404);
        }

        $popup = $branding->popups()->find($popupId);

        if (!$popup) {
            return response()->json([
                'status' => 'error',
                'message' => 'Popup not found',
            ], 404);
        }

        $popup->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Popup deleted successfully',
        ]);
    }

    /**
     * Toggle popup active status.
     */
    public function toggle(Request $request, int $brandingId, int $popupId)
    {
        $branding = $this->findBranding($brandingId);

        if (!$branding) {
            return response()->json([
                'status' => 'error',
                'message' => 'Branding not found',
            ], 404);
        }

        $popup = $branding->popups()->find($popupId);

        if (!$popup) {
            return response()->json([
                'status' => 'error',
                'message' => 'Popup not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'is_active' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $popup->update(['is_active' => $request->boolean('is_active')]);

        return response()->json([
            'status' => 'success',
            'data' => $popup->fresh(),
            'message' => $popup->is_active
                ? 'Popup activated successfully'
                : 'Popup deactivated successfully',
        ]);
    }

    /**
     * Get active popups for the public landing page (no auth required).
     */
    public function publicPopups(Request $request)
    {
        // Get domain from request
        $domain = $request->header('X-Frontend-Domain')
            ?? $request->header('X-Forwarded-Host')
            ?? $request->query('domain')
            ?? $request->getHost();

        $domain = preg_replace('/:\d+$/', '', $domain);

        // Find environment by domain
        $environment = \App\Models\Environment::where('primary_domain', $domain)
            ->orWhere('primary_domain', 'LIKE', '%' . $domain . '%')
            ->first();

        if (!$environment) {
            return response()->json([
                'status' => 'success',
                'data' => [],
            ]);
        }

        // Get branding for environment
        $branding = Branding::where('environment_id', $environment->id)
            ->where('landing_page_enabled', true)
            ->where('is_active', true)
            ->first();

        if (!$branding) {
            return response()->json([
                'status' => 'success',
                'data' => [],
            ]);
        }

        // Get currently active popups
        $popups = $branding->popups()
            ->currentlyActive()
            ->get([
                'id', 'title', 'content', 'trigger_type', 'trigger_value',
                'display_frequency', 'position', 'size',
                'background_color', 'text_color', 'overlay_color', 'overlay_opacity',
                'cta_text', 'cta_url', 'cta_button_color', 'image_url',
            ]);

        return response()->json([
            'status' => 'success',
            'data' => $popups,
        ]);
    }
}
