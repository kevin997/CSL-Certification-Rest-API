<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Links\LinkPreviewService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Returns social-preview metadata for links pasted into rich content.
 *
 * The browser cannot read a third-party page across origins, so the API
 * fetches it once and the web app renders a card. Fetching happens only for
 * authenticated users and is cached per URL by the service.
 */
class LinkPreviewController extends Controller
{
    public function __construct(private readonly LinkPreviewService $previews) {}

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'urls' => ['required', 'array', 'max:'.LinkPreviewService::MAX_URLS_PER_REQUEST],
            'urls.*' => ['required', 'string', 'max:2048'],
        ]);

        return response()->json([
            'data' => $this->previews->previewMany($validated['urls']),
        ]);
    }
}
