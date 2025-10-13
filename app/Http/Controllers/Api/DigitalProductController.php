<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AssetDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Schema(
 *     schema="AssetDeliveryWithRelations",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="download_token", type="string", format="uuid", example="550e8400-e29b-41d4-a716-446655440000"),
 *     @OA\Property(property="access_granted_at", type="string", format="date-time"),
 *     @OA\Property(property="expires_at", type="string", format="date-time"),
 *     @OA\Property(property="access_count", type="integer", example=3),
 *     @OA\Property(property="max_access_count", type="integer", example=10),
 *     @OA\Property(property="last_accessed_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="status", type="string", enum={"active", "expired", "revoked"}, example="active"),
 *     @OA\Property(
 *         property="product_asset",
 *         type="object",
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="title", type="string", example="Course Materials PDF"),
 *         @OA\Property(property="description", type="string", example="Comprehensive course materials"),
 *         @OA\Property(property="asset_type", type="string", example="external_link"),
 *         @OA\Property(property="external_url", type="string", example="https://drive.google.com/file/d/abc123/view")
 *     ),
 *     @OA\Property(
 *         property="order",
 *         type="object",
 *         @OA\Property(property="id", type="integer", example=1),
 *         @OA\Property(property="order_number", type="string", example="ORD-2025-001")
 *     )
 * )
 */
class DigitalProductController extends Controller
{
    /**
     * Get list of user's purchased digital products.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     *
     * @OA\Get(
     *     path="/digital-products",
     *     summary="Get user's purchased digital products",
     *     description="Returns list of digital product deliveries for the authenticated user",
     *     operationId="getDigitalProducts",
     *     tags={"Digital Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by delivery status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"active", "expired", "revoked"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/AssetDeliveryWithRelations")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function index(Request $request)
    {
        $query = AssetDelivery::where('user_id', Auth::id())
            ->where('environment_id', $request->user()->environment_id)
            ->with([
                'productAsset' => function ($query) {
                    $query->select('id', 'product_id', 'asset_type', 'title', 'description', 'external_url', 'display_order');
                },
                'productAsset.product' => function ($query) {
                    $query->select('id', 'name', 'thumbnail_path');
                },
                'order' => function ($query) {
                    $query->select('id', 'order_number', 'created_at');
                }
            ])
            ->orderBy('created_at', 'desc');

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $deliveries = $query->get();

        // Add isValid flag to each delivery
        $deliveries = $deliveries->map(function ($delivery) {
            $delivery->is_valid = $delivery->isValid();
            return $delivery;
        });

        return response()->json([
            'status' => 'success',
            'data' => $deliveries,
        ]);
    }

    /**
     * Access a digital product asset by token.
     *
     * @param  string  $token
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     *
     * @OA\Get(
     *     path="/digital-products/access/{token}",
     *     summary="Access digital product by token",
     *     description="Access a digital product asset using download token. Records access and returns redirect URL for external links.",
     *     operationId="accessDigitalProduct",
     *     tags={"Digital Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="token",
     *         in="path",
     *         description="Download token (UUID)",
     *         required=true,
     *         @OA\Schema(type="string", format="uuid")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Asset accessed successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Access granted"),
     *             @OA\Property(property="redirect_url", type="string", example="https://drive.google.com/file/d/abc123/view"),
     *             @OA\Property(property="asset_type", type="string", example="external_link"),
     *             @OA\Property(property="title", type="string", example="Course Materials PDF"),
     *             @OA\Property(property="access_count", type="integer", example=4),
     *             @OA\Property(property="max_access_count", type="integer", example=10),
     *             @OA\Property(property="expires_at", type="string", format="date-time")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Access denied - expired, limit reached, or revoked",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="This download link has expired or reached its access limit")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Token not found or unauthorized",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="error"),
     *             @OA\Property(property="message", type="string", example="Asset not found or you do not have access")
     *         )
     *     )
     * )
     */
    public function access($token, Request $request)
    {
        // Find delivery by token
        $delivery = AssetDelivery::where('download_token', $token)
            ->with('productAsset')
            ->first();

        if (!$delivery) {
            return response()->json([
                'status' => 'error',
                'message' => 'Asset not found or you do not have access',
            ], Response::HTTP_NOT_FOUND);
        }

        // Verify ownership (user must own this delivery)
        if ($delivery->user_id !== Auth::id()) {
            Log::warning("Unauthorized access attempt to token {$token} by user " . Auth::id());
            return response()->json([
                'status' => 'error',
                'message' => 'Asset not found or you do not have access',
            ], Response::HTTP_NOT_FOUND);
        }

        // Verify environment
        if ($delivery->environment_id !== $request->user()->environment_id) {
            Log::warning("Cross-environment access attempt to token {$token} by user " . Auth::id());
            return response()->json([
                'status' => 'error',
                'message' => 'Asset not found or you do not have access',
            ], Response::HTTP_NOT_FOUND);
        }

        // Check if delivery is still valid
        if (!$delivery->isValid()) {
            $reason = $delivery->status === AssetDelivery::STATUS_REVOKED
                ? 'This access has been revoked'
                : 'This download link has expired or reached its access limit';

            return response()->json([
                'status' => 'error',
                'message' => $reason,
                'expires_at' => $delivery->expires_at,
                'access_count' => $delivery->access_count,
                'max_access_count' => $delivery->max_access_count,
            ], Response::HTTP_FORBIDDEN);
        }

        // Record access
        $delivery->recordAccess(
            $request->ip(),
            $request->userAgent()
        );

        $asset = $delivery->productAsset;

        // Handle external link
        if ($asset->asset_type === 'external_link') {
            return response()->json([
                'status' => 'success',
                'message' => 'Access granted',
                'redirect_url' => $asset->external_url,
                'asset_type' => $asset->asset_type,
                'title' => $asset->title,
                'access_count' => $delivery->access_count,
                'max_access_count' => $delivery->max_access_count,
                'expires_at' => $delivery->expires_at,
            ]);
        }

        // For future: Handle file downloads and email content
        return response()->json([
            'status' => 'error',
            'message' => 'Asset type not yet supported',
        ], Response::HTTP_NOT_IMPLEMENTED);
    }
}
