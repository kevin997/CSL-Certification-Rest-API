<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductAsset;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Schema(
 *     schema="ProductAsset",
 *     required={"product_id", "asset_type", "title"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="product_id", type="integer", example=1),
 *     @OA\Property(property="asset_type", type="string", enum={"file", "external_link", "email_content"}, example="external_link"),
 *     @OA\Property(property="external_url", type="string", example="https://drive.google.com/file/d/abc123/view", nullable=true),
 *     @OA\Property(property="title", type="string", example="Course Materials PDF"),
 *     @OA\Property(property="description", type="string", example="Comprehensive course materials including slides and exercises", nullable=true),
 *     @OA\Property(property="display_order", type="integer", example=1),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class ProductAssetController extends Controller
{
    /**
     * Display a listing of assets for a specific product.
     *
     * @param  int  $productId
     * @return \Illuminate\Http\Response
     *
     * @OA\Get(
     *     path="/products/{product}/assets",
     *     summary="Get list of product assets",
     *     description="Returns list of assets (external links) for a specific product. Only accessible by product owner.",
     *     operationId="getProductAssets",
     *     tags={"Product Assets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="product",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *         @OA\Schema(type="integer")
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
     *                 @OA\Items(ref="#/components/schemas/ProductAsset")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not own this product"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     )
     * )
     */
    public function index($productId)
    {
        $product = Product::find($productId);

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Authorization: ensure user owns the product
        if ($product->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to access this product\'s assets',
            ], Response::HTTP_FORBIDDEN);
        }

        $assets = $product->productAssets()
            ->orderBy('display_order', 'asc')
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $assets,
        ]);
    }

    /**
     * Store a newly created asset for a product.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $productId
     * @return \Illuminate\Http\Response
     *
     * @OA\Post(
     *     path="/products/{product}/assets",
     *     summary="Add external link asset to product",
     *     description="Creates a new external link asset for a product. Only accessible by product owner.",
     *     operationId="storeProductAsset",
     *     tags={"Product Assets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="product",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Asset data",
     *         @OA\JsonContent(
     *             required={"asset_type", "title", "external_url"},
     *             @OA\Property(property="asset_type", type="string", enum={"external_link"}, example="external_link"),
     *             @OA\Property(property="external_url", type="string", format="url", example="https://drive.google.com/file/d/abc123/view"),
     *             @OA\Property(property="title", type="string", maxLength=255, example="Course Materials PDF"),
     *             @OA\Property(property="description", type="string", example="Comprehensive course materials", nullable=true),
     *             @OA\Property(property="display_order", type="integer", example=1, nullable=true, default=0),
     *             @OA\Property(property="is_active", type="boolean", example=true, nullable=true, default=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Asset created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Asset added successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ProductAsset")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not own this product"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request, $productId)
    {
        $product = Product::find($productId);

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Authorization: ensure user owns the product
        if ($product->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to add assets to this product',
            ], Response::HTTP_FORBIDDEN);
        }

        $validator = Validator::make($request->all(), [
            'asset_type' => 'required|string|in:external_link,email_content,file',
            'external_url' => 'required_if:asset_type,external_link|nullable|url|max:2048',
            'email_template' => 'required_if:asset_type,email_content|nullable|string',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Create asset
        $asset = new ProductAsset();
        $asset->product_id = $product->id;
        $asset->asset_type = $request->asset_type;
        $asset->title = $request->title;
        $asset->description = $request->description;
        $asset->display_order = $request->display_order ?? 0;
        $asset->is_active = $request->is_active ?? true;

        // Set type-specific fields
        if ($request->asset_type === 'external_link') {
            $asset->external_url = $request->external_url;
        } elseif ($request->asset_type === 'email_content') {
            $asset->email_template = $request->email_template;
        }

        $asset->save();

        // Refresh to get the latest data
        $asset->refresh();

        // Update product to require fulfillment
        if (!$product->requires_fulfillment) {
            $product->requires_fulfillment = true;
            $product->product_type = $product->product_type ?? 'digital';
            $product->save();
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Asset added successfully',
            'data' => $asset,
        ], Response::HTTP_CREATED);
    }

    /**
     * Update the specified asset.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $productId
     * @param  int  $assetId
     * @return \Illuminate\Http\Response
     *
     * @OA\Put(
     *     path="/products/{product}/assets/{asset}",
     *     summary="Update product asset",
     *     description="Updates an existing product asset. Only accessible by product owner.",
     *     operationId="updateProductAsset",
     *     tags={"Product Assets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="product",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="asset",
     *         in="path",
     *         description="Asset ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Asset data to update",
     *         @OA\JsonContent(
     *             @OA\Property(property="external_url", type="string", format="url", example="https://drive.google.com/file/d/abc123/view"),
     *             @OA\Property(property="title", type="string", maxLength=255, example="Updated Title"),
     *             @OA\Property(property="description", type="string", example="Updated description", nullable=true),
     *             @OA\Property(property="display_order", type="integer", example=2),
     *             @OA\Property(property="is_active", type="boolean", example=false)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Asset updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Asset updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ProductAsset")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not own this product"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product or asset not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update(Request $request, $productId, $assetId)
    {
        $product = Product::find($productId);

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Authorization: ensure user owns the product
        if ($product->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to update this product\'s assets',
            ], Response::HTTP_FORBIDDEN);
        }

        $asset = ProductAsset::where('product_id', $productId)->find($assetId);

        if (!$asset) {
            return response()->json([
                'status' => 'error',
                'message' => 'Asset not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $validator = Validator::make($request->all(), [
            'asset_type' => 'nullable|string|in:external_link,email_content,file',
            'external_url' => 'nullable|url|max:2048',
            'email_template' => 'nullable|string',
            'title' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Update only provided fields
        if ($request->has('asset_type')) {
            $asset->asset_type = $request->asset_type;
        }
        if ($request->has('external_url')) {
            $asset->external_url = $request->external_url;
        }
        if ($request->has('email_template')) {
            $asset->email_template = $request->email_template;
        }
        if ($request->has('title')) {
            $asset->title = $request->title;
        }
        if ($request->has('description')) {
            $asset->description = $request->description;
        }
        if ($request->has('display_order')) {
            $asset->display_order = $request->display_order;
        }
        if ($request->has('is_active')) {
            $asset->is_active = $request->is_active;
        }

        $asset->save();

        // Refresh to get the latest data
        $asset->refresh();

        return response()->json([
            'status' => 'success',
            'message' => 'Asset updated successfully',
            'data' => $asset,
        ]);
    }

    /**
     * Remove the specified asset.
     *
     * @param  int  $productId
     * @param  int  $assetId
     * @return \Illuminate\Http\Response
     *
     * @OA\Delete(
     *     path="/products/{product}/assets/{asset}",
     *     summary="Delete product asset",
     *     description="Deletes a product asset. Only accessible by product owner.",
     *     operationId="deleteProductAsset",
     *     tags={"Product Assets"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="product",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="asset",
     *         in="path",
     *         description="Asset ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Asset deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Asset deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not own this product"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product or asset not found"
     *     )
     * )
     */
    public function destroy($productId, $assetId)
    {
        $product = Product::find($productId);

        if (!$product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Authorization: ensure user owns the product
        if ($product->created_by !== Auth::id()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to delete this product\'s assets',
            ], Response::HTTP_FORBIDDEN);
        }

        $asset = ProductAsset::where('product_id', $productId)->find($assetId);

        if (!$asset) {
            return response()->json([
                'status' => 'error',
                'message' => 'Asset not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $asset->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Asset deleted successfully',
        ]);
    }
}
