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
     * @return \Illuminate\Http\JsonResponse
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
            ->where('environment_id', \App\Traits\BelongsToEnvironment::detectEnvironmentId())
            ->with(['order', 'productAsset' => function ($query) {
                    $query->select('id', 'product_id', 'asset_type', 'title', 'description', 'external_url', 'display_order');
                },
                'productAsset.product' => function ($query) {
                    $query->select('id', 'name', 'thumbnail_path', 'created_at');
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
     * @return \Illuminate\Http\JsonResponse
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
    public function access(Request $request, $token)
    {
        $environmentId = \App\Traits\BelongsToEnvironment::detectEnvironmentId();

        $delivery = AssetDelivery::where('download_token', $token)
            ->where('environment_id', $environmentId)
            ->with(['productAsset'])
            ->first();

        if (!$delivery) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid or expired token',
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$delivery->isValid()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Access limit reached or expired',
            ], Response::HTTP_FORBIDDEN);
        }

        // Record access
        $delivery->recordAccess($request->ip(), $request->userAgent());

        $asset = $delivery->productAsset;

        // Common response data
        $responseData = [
            'status' => 'success',
            'asset_type' => $asset->asset_type,
            'title' => $asset->title,
            'access_count' => $delivery->access_count,
            'max_access_count' => $delivery->max_access_count,
            'expires_at' => $delivery->expires_at,
        ];

        if ($asset->asset_type === 'external_link') {
            return response()->json(array_merge($responseData, [
                'redirect_url' => $asset->external_url,
            ]));
        } elseif ($asset->asset_type === 'file') {
            if (!$asset->file_path) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'File path not found',
                ], Response::HTTP_NOT_FOUND);
            }

            $expires = now()->addMinutes(60)->timestamp;
            $signature = hash_hmac('sha256', "stream:{$asset->file_path}:{$expires}", config('services.media_service.secret'));
            $mediaServiceUrl = config('services.media_service.url');
            
            $secureUrl = "{$mediaServiceUrl}/api/stream/{$asset->file_path}?signature={$signature}&expires={$expires}";

            return response()->json(array_merge($responseData, [
                'secure_url' => $secureUrl,
                'file_name' => $asset->file_name,
                'file_type' => $asset->file_type,
            ]));
        }

        return response()->json([
            'status' => 'error',
            'message' => 'Asset type not yet supported',
        ], Response::HTTP_NOT_IMPLEMENTED);
    }
}
