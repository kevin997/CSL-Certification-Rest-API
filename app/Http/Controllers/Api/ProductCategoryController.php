<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

/**
 * @OA\Schema(
 *     schema="ProductCategory",
 *     required={"name", "is_active"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="name", type="string", example="Online Courses"),
 *     @OA\Property(property="slug", type="string", example="online-courses"),
 *     @OA\Property(property="description", type="string", example="All online course products", nullable=true),
 *     @OA\Property(property="parent_id", type="integer", format="int64", example=null, nullable=true),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="display_order", type="integer", example=1),
 *     @OA\Property(property="created_by", type="integer", example=1),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(
 *         property="products_count",
 *         type="integer",
 *         example=5
 *     )
 * )
 */
class ProductCategoryController extends Controller
{
    /**
     * Display a listing of product categories.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/product-categories",
     *     summary="Get list of product categories",
     *     description="Returns paginated list of product categories with optional filtering",
     *     operationId="getProductCategoriesList",
     *     tags={"Product Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term for category name or description",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="parent_id",
     *         in="query",
     *         description="Filter by parent category ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="sort_field",
     *         in="query",
     *         description="Field to sort by",
     *         required=false,
     *         @OA\Schema(type="string", enum={"name", "display_order", "created_at"}, default="display_order")
     *     ),
     *     @OA\Parameter(
     *         name="sort_direction",
     *         in="query",
     *         description="Sort direction",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}, default="asc")
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
     *                     @OA\Items(ref="#/components/schemas/ProductCategory")
     *                 ),
     *                 @OA\Property(property="total", type="integer", example=20),
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
        $query = ProductCategory::withCount('products');
        
        // Apply filters
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        if ($request->has('is_active')) {
            // Cast string 'true'/'false' to integer 1/0
            $isActive = filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            $query->where('is_active', $isActive);
        }
        
        if ($request->has('environment_id')) {
            $query->where('environment_id', $request->environment_id);
        }
        
        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        } else {
            // By default, show only top-level categories
            $query->whereNull('parent_id');
        }
        
        // Apply sorting
        $sortField = $request->input('sort_field', 'display_order');
        $sortDirection = $request->input('sort_direction', 'asc');
        $allowedSortFields = ['name', 'display_order', 'created_at'];
        
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('display_order', 'asc');
        }
        
        // Pagination
        $perPage = $request->input('per_page', 15);
        $categories = $query->paginate($perPage);
        
        return response()->json([
            'status' => 'success',
            'data' => $categories,
        ]);
    }

    /**
     * Store a newly created product category.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/product-categories",
     *     summary="Create a new product category",
     *     description="Creates a new product category with the provided data",
     *     operationId="storeProductCategory",
     *     tags={"Product Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Product category data",
     *         @OA\JsonContent(
     *             required={"name", "is_active"},
     *             @OA\Property(property="name", type="string", example="Online Courses"),
     *             @OA\Property(property="description", type="string", example="All online course products", nullable=true),
     *             @OA\Property(property="parent_id", type="integer", format="int64", example=null, nullable=true),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="display_order", type="integer", example=1, nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Product category created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Product category created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ProductCategory")
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
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:product_categories,id',
            'is_active' => 'required|boolean',
            'display_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Create product category
        $category = new ProductCategory();
        $category->name = $request->name;
        $category->slug = Str::slug($request->name);
        $category->description = $request->description;
        $category->parent_id = $request->parent_id;
        $category->is_active = $request->is_active;
        $category->display_order = $request->display_order ?? 0;
        $category->created_by = Auth::id();
        $category->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Product category created successfully',
            'data' => $category->loadCount('products'),
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified product category.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/product-categories/{id}",
     *     summary="Get product category details",
     *     description="Returns details of a specific product category",
     *     operationId="getProductCategoryById",
     *     tags={"Product Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product Category ID",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/ProductCategory")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product category not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function show($id)
    {
        $category = ProductCategory::withCount('products')->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $category,
        ]);
    }

    /**
     * Update the specified product category.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * 
     * @OA\Put(
     *     path="/product-categories/{id}",
     *     summary="Update product category",
     *     description="Updates an existing product category with the provided data",
     *     operationId="updateProductCategory",
     *     tags={"Product Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product Category ID",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         description="Product category data",
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="Updated Online Courses"),
     *             @OA\Property(property="description", type="string", example="Updated description", nullable=true),
     *             @OA\Property(property="parent_id", type="integer", format="int64", example=null, nullable=true),
     *             @OA\Property(property="is_active", type="boolean", example=true),
     *             @OA\Property(property="display_order", type="integer", example=2, nullable=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product category updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Product category updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/ProductCategory")
     *         )
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product category not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $category = ProductCategory::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'parent_id' => 'nullable|exists:product_categories,id',
            'is_active' => 'sometimes|required|boolean',
            'display_order' => 'nullable|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Prevent circular reference
        if ($request->has('parent_id') && $request->parent_id == $id) {
            return response()->json([
                'status' => 'error',
                'message' => 'A category cannot be its own parent',
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Update product category fields
        if ($request->has('name')) {
            $category->name = $request->name;
            $category->slug = Str::slug($request->name);
        }
        
        if ($request->has('description')) {
            $category->description = $request->description;
        }
        
        if ($request->has('parent_id')) {
            $category->parent_id = $request->parent_id;
        }
        
        if ($request->has('is_active')) {
            $category->is_active = $request->is_active;
        }
        
        if ($request->has('display_order')) {
            $category->display_order = $request->display_order;
        }
        
        $category->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Product category updated successfully',
            'data' => $category->loadCount('products'),
        ]);
    }

    /**
     * Remove the specified product category.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * 
     * @OA\Delete(
     *     path="/product-categories/{id}",
     *     summary="Delete product category",
     *     description="Deletes a product category if it has no products or child categories",
     *     operationId="deleteProductCategory",
     *     tags={"Product Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Product Category ID",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Product category deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Product category deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Cannot delete category with existing products or child categories"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Product category not found"
     *     )
     * )
     */
    public function destroy($id)
    {
        $category = ProductCategory::withCount(['products', 'children'])->findOrFail($id);

        // Check if category has products
        if ($category->products_count > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete category with existing products',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check if category has child categories
        if ($category->children_count > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete category with existing child categories',
            ], Response::HTTP_BAD_REQUEST);
        }
        
        // Delete category
        $category->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Product category deleted successfully',
        ]);
    }

    /**
     * Get a hierarchical list of product categories.
     *
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/product-categories/hierarchy",
     *     summary="Get hierarchical list of product categories",
     *     description="Returns a nested list of product categories organized by parent-child relationships",
     *     operationId="getProductCategoriesHierarchy",
     *     tags={"Product Categories"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="include_inactive",
     *         in="query",
     *         description="Whether to include inactive categories",
     *         required=false,
     *         @OA\Schema(type="boolean", default=false)
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
     *                 @OA\Items(
     *                     type="object",
     *                     allOf={
     *                         @OA\Schema(ref="#/components/schemas/ProductCategory"),
     *                         @OA\Schema(
     *                             @OA\Property(
     *                                 property="children",
     *                                 type="array",
     *                                 @OA\Items(ref="#/components/schemas/ProductCategory")
     *                             )
     *                         )
     *                     }
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function hierarchy(Request $request)
    {
        $includeInactive = $request->input('include_inactive', false);
        
        $query = ProductCategory::withCount('products')
            ->whereNull('parent_id')
            ->orderBy('display_order', 'asc')
            ->orderBy('name', 'asc');
            
        if (!$includeInactive) {
            $query->where('is_active', true);
        }
        
        $categories = $query->get();
        
        // Load children recursively
        $categories->each(function ($category) use ($includeInactive) {
            $this->loadChildrenRecursive($category, $includeInactive);
        });
        
        return response()->json([
            'status' => 'success',
            'data' => $categories,
        ]);
    }
    
    /**
     * Recursively load children for a category.
     *
     * @param  \App\Models\ProductCategory  $category
     * @param  bool  $includeInactive
     * @return void
     */
    private function loadChildrenRecursive($category, $includeInactive)
    {
        $query = $category->children()
            ->withCount('products')
            ->orderBy('display_order', 'asc')
            ->orderBy('name', 'asc');
            
        if (!$includeInactive) {
            $query->where('is_active', true);
        }
        
        $children = $query->get();
        
        $category->setRelation('children', $children);
        
        $children->each(function ($child) use ($includeInactive) {
            $this->loadChildrenRecursive($child, $includeInactive);
        });
    }
}
