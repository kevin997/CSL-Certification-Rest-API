<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductLandingPage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class ProductLandingPageController extends Controller
{
    /**
     * The page for a product, or an empty one.
     *
     * Deliberately does not filter on `enabled`: the editor has to be able to
     * open a page that is switched off. Only the public endpoint enforces it.
     */
    public function show(int $id): JsonResponse
    {
        // Product::find applies the environment global scope, so another
        // tenant's product is simply absent.
        $product = Product::find($id);

        if (! $product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $page = ProductLandingPage::where('product_id', $product->id)->first();

        // A read must not write. An untouched product reports an empty page
        // rather than having one created for it.
        return response()->json([
            'status' => 'success',
            'data' => [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'page_data' => $page?->page_data,
                'seo_title' => $page?->seo_title,
                'seo_description' => $page?->seo_description,
                'enabled' => (bool) $page?->enabled,
            ],
        ]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $validator = Validator::make($request->all(), [
            'page_data' => 'nullable|array',
            'seo_title' => 'nullable|string|max:191',
            'seo_description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $page = ProductLandingPage::updateOrCreate(
            ['product_id' => $product->id],
            [
                'environment_id' => $product->environment_id,
                'page_data' => $request->input('page_data'),
                'seo_title' => $request->input('seo_title'),
                'seo_description' => $request->input('seo_description'),
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Landing page saved',
            'data' => $page,
        ]);
    }

    public function toggle(Request $request, int $id): JsonResponse
    {
        $product = Product::find($id);

        if (! $product) {
            return response()->json([
                'status' => 'error',
                'message' => 'Product not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $validator = Validator::make($request->all(), [
            'enabled' => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $page = ProductLandingPage::updateOrCreate(
            ['product_id' => $product->id],
            [
                'environment_id' => $product->environment_id,
                'enabled' => $request->boolean('enabled'),
            ]
        );

        return response()->json([
            'status' => 'success',
            'data' => $page,
        ]);
    }
}
