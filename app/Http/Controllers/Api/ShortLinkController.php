<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Sales\ShortLinkService;
use Illuminate\Http\JsonResponse;

/**
 * Resolve a short order checkout link to its full, freshly signed URL.
 *
 * Public by design: the short code is the credential, and it only opens the
 * payment page for an order that is still pending.
 */
class ShortLinkController extends Controller
{
    public function resolve(string $code, ShortLinkService $shortLinks): JsonResponse
    {
        $url = $shortLinks->resolve($code);

        if (! $url) {
            return response()->json([
                'success' => false,
                'message' => 'This payment link is invalid or has expired.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => ['url' => $url],
        ]);
    }
}
