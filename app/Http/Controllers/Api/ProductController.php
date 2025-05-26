<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * @OA\Schema(
 *     schema="Product",
 *     required={"name", "description", "price", "currency", "is_subscription", "status"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="name", type="string", example="CSL Certification Course"),
 *     @OA\Property(property="description", type="string", example="Complete certification course for CSL professionals"),
 *     @OA\Property(property="price", type="number", format="float", example=99.99),
 *     @OA\Property(property="discount_price", type="number", format="float", example=79.99, nullable=true),
 *     @OA\Property(property="currency", type="string", example="USD"),
 *     @OA\Property(property="is_subscription", type="boolean", example=true),
 *     @OA\Property(property="subscription_interval", type="string", example="monthly", nullable=true),
 *     @OA\Property(property="subscription_interval_count", type="integer", example=1, nullable=true),
 *     @OA\Property(property="trial_days", type="integer", example=14, nullable=true),
 *     @OA\Property(property="status", type="string", enum={"draft", "active", "inactive"}, example="active"),
 *     @OA\Property(property="thumbnail_path", type="string", example="products/thumbnail.jpg", nullable=true),
 *     @OA\Property(property="created_by", type="integer", example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(
 *         property="courses",
 *         type="array",
 *         @OA\Items(
 *             type="object",
 *             @OA\Property(property="id", type="integer", example=1),
 *             @OA\Property(property="title", type="string", example="Introduction to CSL"),
 *         )
 *     )
 * )
 */
class ProductController extends Controller
{
    /**
     * Display a listing of products.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/products",
     *     summary="Get list of products",
     *     description="Returns paginated list of products with optional filtering",
     *     operationId="getProductsList",
     *     tags={"Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term for product name or description",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by product status",
     *         required=false,
     *         @OA\Schema(type="string", enum={"draft", "active", "inactive"})
     *     ),
     *     @OA\Parameter(
     *         name="is_subscription",
     *         in="query",
     *         description="Filter by subscription type",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="min_price",
     *         in="query",
     *         description="Minimum price filter",
     *         required=false,
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Parameter(
     *         name="max_price",
     *         in="query",
     *         description="Maximum price filter",
     *         required=false,
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Parameter(
     *         name="sort_field",
     *         in="query",
     *         description="Field to sort by",
     *         required=false,
     *         @OA\Schema(type="string", enum={"name", "price", "status", "created_at"}, default="created_at")
     *     ),
     *     @OA\Parameter(
     *         name="sort_direction",
     *         in="query",
     *         description="Sort direction",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}, default="desc")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/Product")
     *                 ),
     *                 @OA\Property(property="total", type="integer", example=50),
     *                 @OA\Property(property="per_page", type="integer", example=15)
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
        $query = Product::query()->with('category');
        
        // Apply filters
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->has('is_subscription')) {
            $query->where('is_subscription', $request->is_subscription);
        }
        
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }
        
        // Apply sorting
        $sortField = $request->input('sort_field', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $allowedSortFields = ['name', 'price', 'status', 'created_at'];
        
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }
        
        // Pagination
        $perPage = $request->input('per_page', 15);
        $products = $query->paginate($perPage);
        
        return response()->json([
            'status' => 'success',
            'data' => $products,
        ]);
    }

    /**
     * Store a newly created product.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/products",
     *     summary="Create a new product",
     *     description="Creates a new product with the provided data",
     *     operationId="storeProduct",
     *     tags={"Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Product data",
     *         @OA\JsonContent(
     *             required={"name", "description", "price", "currency", "is_subscription", "status"},
     *             @OA\Property(property="name", type="string", example="CSL Certification Course"),
     *             @OA\Property(property="description", type="string", example="Complete certification course for CSL professionals"),
     *             @OA\Property(property="price", type="number", format="float", example=99.99),
     *             @OA\Property(property="discount_price", type="number", format="float", example=79.99, nullable=true),
     *             @OA\Property(property="currency", type="string", example="USD"),
     *             @OA\Property(property="is_subscription", type="boolean", example=true),
     *             @OA\Property(property="subscription_interval", type="string", example="monthly", nullable=true),
     *             @OA\Property(property="subscription_interval_count", type="integer", example=1, nullable=true),
     *             @OA\Property(property="trial_days", type="integer", example=14, nullable=true),
     *             @OA\Property(property="status", type="string", enum={"draft", "active", "inactive"}, example="active"),
     *             @OA\Property(property="thumbnail_path", type="string", example="products/thumbnail.jpg", nullable=true),
     *             @OA\Property(
     *                 property="courses",
     *                 type="array",
     *                 @OA\Items(type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Product created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Product created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Product")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission"
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
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'currency' => 'required|string|size:3',
            'is_subscription' => 'required|boolean',
            'subscription_interval' => 'required_if:is_subscription,true|nullable|string|in:daily,weekly,monthly,yearly',
            'subscription_interval_count' => 'required_if:is_subscription,true|nullable|integer|min:1',
            'trial_days' => 'nullable|integer|min:0',
            'status' => 'required|string|in:draft,active,inactive',
            'thumbnail_path' => 'nullable|string',
            'courses' => 'nullable|array',
            'courses.*' => 'exists:courses,id',
            'is_featured' => 'nullable|boolean',
            'category_id' => 'required|exists:product_categories,id',
            'sku' => 'required|string|max:255',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Create product
        $product = new Product();
        $product->name = $request->name;
        $product->description = $request->description;
        $product->price = $request->price;
        $product->discount_price = $request->discount_price;
        $product->currency = $request->currency;
        $product->is_subscription = $request->is_subscription;
        $product->subscription_interval = $request->subscription_interval;
        $product->subscription_interval_count = $request->subscription_interval_count;
        $product->trial_days = $request->trial_days;
        $product->status = $request->status;
        $product->thumbnail_path = $request->thumbnail_path;
        $product->created_by = Auth::id();
        $product->is_featured = $request->is_featured;
        $product->category_id = $request->category_id;
        $product->sku = $request->sku;
        $product->meta_title = $request->meta_title;
        $product->meta_description = $request->meta_description;
        $product->meta_keywords = $request->meta_keywords;
        $product->save();

        // Attach courses if provided
        if ($request->has('courses') && is_array($request->courses)) {
            $product->courses()->attach($request->courses);
        }

        // Load the category relationship
        $product->load('category');

        return response()->json([
            'status' => 'success',
            'message' => 'Product created successfully',
            'data' => $product
        ], 201);
    }

    /**
     * Display the specified product.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/products/{id}",
     *     summary="Get product details",
     *     description="Returns details of a specific product",
     *     operationId="getProductById",
     *     tags={"Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/Product")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function show($id)
    {
        $product = Product::with(['courses', 'category'])->find($id);

        return response()->json([
            'status' => 'success',
            'data' => $product,
        ]);
    }

    /**
     * Update the specified product.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * 
     * @OA\Put(
     *     path="/products/{id}",
     *     summary="Update product",
     *     description="Updates an existing product with the provided data",
     *     operationId="updateProduct",
     *     tags={"Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         description="Product data",
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Updated CSL Course"),
     *             @OA\Property(property="description", type="string", example="Updated description"),
     *             @OA\Property(property="price", type="number", format="float", example=129.99),
     *             @OA\Property(property="discount_price", type="number", format="float", example=99.99, nullable=true),
     *             @OA\Property(property="currency", type="string", example="USD"),
     *             @OA\Property(property="is_subscription", type="boolean", example=true),
     *             @OA\Property(property="subscription_interval", type="string", example="monthly", nullable=true),
     *             @OA\Property(property="subscription_interval_count", type="integer", example=1, nullable=true),
     *             @OA\Property(property="trial_days", type="integer", example=14, nullable=true),
     *             @OA\Property(property="status", type="string", enum={"draft", "active", "inactive"}, example="active"),
     *             @OA\Property(property="thumbnail_path", type="string", example="products/new-thumbnail.jpg", nullable=true),
     *             @OA\Property(
     *                 property="courses",
     *                 type="array",
     *                 @OA\Items(type="integer", example=1)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Product updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Product")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission"
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
    public function update(Request $request, $id)
    {
        $product = Product::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'sometimes|required|string',
            'price' => 'sometimes|required|numeric|min:0',
            'discount_price' => 'nullable|numeric|min:0',
            'currency' => 'sometimes|required|string|size:3',
            'is_subscription' => 'sometimes|required|boolean',
            'subscription_interval' => 'required_if:is_subscription,true|nullable|string|in:daily,weekly,monthly,yearly',
            'subscription_interval_count' => 'required_if:is_subscription,true|nullable|integer|min:1',
            'trial_days' => 'nullable|integer|min:0',
            'status' => 'sometimes|required|string|in:draft,active,inactive',
            'thumbnail_path' => 'nullable|string',
            'is_featured' => 'nullable|boolean',
            'category_id' => 'required|exists:product_categories,id',
            'courses' => 'nullable|array',
            'courses.*' => 'exists:courses,id',
            'sku' => 'required|string|max:255',
            'meta_title' => 'nullable|string|max:255',
            'meta_description' => 'nullable|string',
            'meta_keywords' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Update product fields
        if ($request->has('name')) {
            $product->name = $request->name;
        }
        
        if ($request->has('description')) {
            $product->description = $request->description;
        }
        
        if ($request->has('price')) {
            $product->price = $request->price;
        }
        
        if ($request->has('discount_price')) {
            $product->discount_price = $request->discount_price;
        }
        
        if ($request->has('currency')) {
            $product->currency = $request->currency;
        }
        
        if ($request->has('is_subscription')) {
            $product->is_subscription = $request->is_subscription;
        }
        
        if ($request->has('subscription_interval')) {
            $product->subscription_interval = $request->subscription_interval;
        }
        
        if ($request->has('subscription_interval_count')) {
            $product->subscription_interval_count = $request->subscription_interval_count;
        }
        
        if ($request->has('trial_days')) {
            $product->trial_days = $request->trial_days;
        }
        
        if ($request->has('status')) {
            $product->status = $request->status;
        }
        
        if ($request->has('thumbnail_path')) {
            $product->thumbnail_path = $request->thumbnail_path;
        }
        
        if ($request->has('category_id')) {
            $product->category_id = $request->category_id;
        }
        
        if ($request->has('is_featured')) {
            $product->is_featured = $request->is_featured;
        }

        if ($request->has('sku')) {
            $product->sku = $request->sku;
        }

        if ($request->has('meta_title')) {
            $product->meta_title = $request->meta_title;
        }

        if ($request->has('meta_description')) {
            $product->meta_description = $request->meta_description;
        }

        if ($request->has('meta_keywords')) {
            $product->meta_keywords = $request->meta_keywords;
        }
        
        $product->save();

        // Update courses if provided
        if ($request->has('courses')) {
            $product->courses()->sync($request->courses);
        }

        // Load the category relationship
        $product->load('category');

        return response()->json([
            'status' => 'success',
            'message' => 'Product updated successfully',
            'data' => $product,
        ]);
    }

    /**
     * Remove the specified product.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * 
     * @OA\Delete(
     *     path="/products/{id}",
     *     summary="Delete product",
     *     description="Deletes a product if it has no associated orders",
     *     operationId="deleteProduct",
     *     tags={"Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Product deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Cannot delete product with existing orders"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     )
     * )
     */
    public function destroy($id)
    {
        $product = Product::findOrFail($id);

        // Check if user has permission to delete this product
        if (!Auth::user()->is_admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to delete products',
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if product has orders
        if ($product->orders()->count() > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete product with existing orders',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Detach courses
        $product->courses()->detach();
        
        // Delete product
        $product->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Product deleted successfully',
        ]);
    }

    /**
     * Activate the specified product.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/products/{id}/activate",
     *     summary="Activate product",
     *     description="Sets the product status to active",
     *     operationId="activateProduct",
     *     tags={"Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product activated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Product activated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Product")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     )
     * )
     */
    public function activate($id)
    {
        $product = Product::findOrFail($id);

        // Check if user has permission to activate this product
        if (!Auth::user()->is_admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to activate products',
            ], Response::HTTP_FORBIDDEN);
        }

        $product->status = 'active';
        $product->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Product activated successfully',
            'data' => $product,
        ]);
    }

    /**
     * Deactivate the specified product.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/products/{id}/deactivate",
     *     summary="Deactivate product",
     *     description="Sets the product status to inactive",
     *     operationId="deactivateProduct",
     *     tags={"Products"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product ID",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product deactivated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Product deactivated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Product")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product not found"
     *     )
     * )
     */
    public function deactivate($id)
    {
        $product = Product::findOrFail($id);

        // Check if user has permission to deactivate this product
        if (!Auth::user()->is_admin) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to deactivate products',
            ], Response::HTTP_FORBIDDEN);
        }

        $product->status = 'inactive';
        $product->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Product deactivated successfully',
            'data' => $product,
        ]);
    }
}
