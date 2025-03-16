<?php

namespace App\Services;

use App\Models\Product;
use App\Models\Course;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductService
{
    /**
     * Get all products
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllProducts(array $filters = [])
    {
        $query = Product::query();
        
        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['type'])) {
            $query->where('type', $filters['type']);
        }
        
        if (isset($filters['price_min'])) {
            $query->where('price', '>=', $filters['price_min']);
        }
        
        if (isset($filters['price_max'])) {
            $query->where('price', '<=', $filters['price_max']);
        }
        
        if (isset($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        // Apply sorting
        $sortField = $filters['sort_field'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $query->orderBy($sortField, $sortDirection);
        
        return $query->get();
    }
    
    /**
     * Get product by ID
     *
     * @param int $id
     * @return Product|null
     */
    public function getProductById(int $id): ?Product
    {
        return Product::find($id);
    }
    
    /**
     * Create a new product
     *
     * @param array $data
     * @return Product
     */
    public function createProduct(array $data): Product
    {
        // Generate slug if not provided
        if (!isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        
        // Process features if provided as array
        if (isset($data['features']) && is_array($data['features'])) {
            $data['features'] = json_encode($data['features']);
        }
        
        // Process pricing_options if provided as array
        if (isset($data['pricing_options']) && is_array($data['pricing_options'])) {
            $data['pricing_options'] = json_encode($data['pricing_options']);
        }
        
        // Process metadata if provided as array
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
        }
        
        return Product::create($data);
    }
    
    /**
     * Update a product
     *
     * @param int $id
     * @param array $data
     * @return Product|null
     */
    public function updateProduct(int $id, array $data): ?Product
    {
        $product = $this->getProductById($id);
        
        if (!$product) {
            return null;
        }
        
        // Update slug if name is changed and slug is not provided
        if (isset($data['name']) && !isset($data['slug'])) {
            $data['slug'] = Str::slug($data['name']);
        }
        
        // Process features if provided as array
        if (isset($data['features']) && is_array($data['features'])) {
            $data['features'] = json_encode($data['features']);
        }
        
        // Process pricing_options if provided as array
        if (isset($data['pricing_options']) && is_array($data['pricing_options'])) {
            $data['pricing_options'] = json_encode($data['pricing_options']);
        }
        
        // Process metadata if provided as array
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            $data['metadata'] = json_encode($data['metadata']);
        }
        
        $product->update($data);
        
        return $product;
    }
    
    /**
     * Delete a product
     *
     * @param int $id
     * @return bool
     */
    public function deleteProduct(int $id): bool
    {
        $product = $this->getProductById($id);
        
        if (!$product) {
            return false;
        }
        
        return $product->delete();
    }
    
    /**
     * Get product with decoded JSON fields
     *
     * @param int $id
     * @return array|null
     */
    public function getProductWithDecodedFields(int $id): ?array
    {
        $product = $this->getProductById($id);
        
        if (!$product) {
            return null;
        }
        
        $productArray = $product->toArray();
        
        // Decode JSON fields
        if (isset($productArray['features']) && is_string($productArray['features'])) {
            $productArray['features'] = json_decode($productArray['features'], true);
        }
        
        if (isset($productArray['pricing_options']) && is_string($productArray['pricing_options'])) {
            $productArray['pricing_options'] = json_decode($productArray['pricing_options'], true);
        }
        
        if (isset($productArray['metadata']) && is_string($productArray['metadata'])) {
            $productArray['metadata'] = json_decode($productArray['metadata'], true);
        }
        
        return $productArray;
    }
    
    /**
     * Link a product to a course
     *
     * @param int $productId
     * @param int $courseId
     * @return bool
     */
    public function linkProductToCourse(int $productId, int $courseId): bool
    {
        $product = $this->getProductById($productId);
        $course = Course::find($courseId);
        
        if (!$product || !$course) {
            return false;
        }
        
        $product->update(['course_id' => $courseId]);
        
        return true;
    }
    
    /**
     * Unlink a product from a course
     *
     * @param int $productId
     * @return bool
     */
    public function unlinkProductFromCourse(int $productId): bool
    {
        $product = $this->getProductById($productId);
        
        if (!$product) {
            return false;
        }
        
        $product->update(['course_id' => null]);
        
        return true;
    }
    
    /**
     * Get products by course ID
     *
     * @param int $courseId
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getProductsByCourse(int $courseId)
    {
        return Product::where('course_id', $courseId)->get();
    }
    
    /**
     * Update product status
     *
     * @param int $id
     * @param string $status
     * @return Product|null
     */
    public function updateProductStatus(int $id, string $status): ?Product
    {
        $product = $this->getProductById($id);
        
        if (!$product) {
            return null;
        }
        
        $product->update(['status' => $status]);
        
        return $product;
    }
    
    /**
     * Add a feature to a product
     *
     * @param int $id
     * @param string $feature
     * @return Product|null
     */
    public function addFeature(int $id, string $feature): ?Product
    {
        $product = $this->getProductById($id);
        
        if (!$product) {
            return null;
        }
        
        $features = json_decode($product->features ?? '[]', true);
        $features[] = $feature;
        
        $product->update(['features' => json_encode($features)]);
        
        return $product;
    }
    
    /**
     * Remove a feature from a product
     *
     * @param int $id
     * @param string $feature
     * @return Product|null
     */
    public function removeFeature(int $id, string $feature): ?Product
    {
        $product = $this->getProductById($id);
        
        if (!$product) {
            return null;
        }
        
        $features = json_decode($product->features ?? '[]', true);
        $features = array_filter($features, function ($f) use ($feature) {
            return $f !== $feature;
        });
        
        $product->update(['features' => json_encode(array_values($features))]);
        
        return $product;
    }
    
    /**
     * Add a pricing option to a product
     *
     * @param int $id
     * @param array $pricingOption
     * @return Product|null
     */
    public function addPricingOption(int $id, array $pricingOption): ?Product
    {
        $product = $this->getProductById($id);
        
        if (!$product) {
            return null;
        }
        
        $pricingOptions = json_decode($product->pricing_options ?? '[]', true);
        $pricingOptions[] = $pricingOption;
        
        $product->update(['pricing_options' => json_encode($pricingOptions)]);
        
        return $product;
    }
    
    /**
     * Remove a pricing option from a product
     *
     * @param int $id
     * @param string $optionName
     * @return Product|null
     */
    public function removePricingOption(int $id, string $optionName): ?Product
    {
        $product = $this->getProductById($id);
        
        if (!$product) {
            return null;
        }
        
        $pricingOptions = json_decode($product->pricing_options ?? '[]', true);
        $pricingOptions = array_filter($pricingOptions, function ($option) use ($optionName) {
            return $option['name'] !== $optionName;
        });
        
        $product->update(['pricing_options' => json_encode(array_values($pricingOptions))]);
        
        return $product;
    }
    
    /**
     * Get featured products
     *
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getFeaturedProducts(int $limit = 5)
    {
        return Product::where('is_featured', true)
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }
    
    /**
     * Get products by type
     *
     * @param string $type
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getProductsByType(string $type)
    {
        return Product::where('type', $type)
            ->where('status', 'active')
            ->orderBy('created_at', 'desc')
            ->get();
    }
    
    /**
     * Search products
     *
     * @param string $query
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function searchProducts(string $query, array $filters = [])
    {
        $productQuery = Product::where(function ($q) use ($query) {
            $q->where('name', 'like', "%{$query}%")
              ->orWhere('description', 'like', "%{$query}%")
              ->orWhere('short_description', 'like', "%{$query}%");
        });
        
        // Apply filters
        if (isset($filters['status'])) {
            $productQuery->where('status', $filters['status']);
        }
        
        if (isset($filters['type'])) {
            $productQuery->where('type', $filters['type']);
        }
        
        if (isset($filters['price_min'])) {
            $productQuery->where('price', '>=', $filters['price_min']);
        }
        
        if (isset($filters['price_max'])) {
            $productQuery->where('price', '<=', $filters['price_max']);
        }
        
        return $productQuery->orderBy('created_at', 'desc')->get();
    }
    
    /**
     * Get related products
     *
     * @param int $productId
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRelatedProducts(int $productId, int $limit = 4)
    {
        $product = $this->getProductById($productId);
        
        if (!$product) {
            return collect([]);
        }
        
        return Product::where('id', '!=', $productId)
            ->where('type', $product->type)
            ->where('status', 'active')
            ->inRandomOrder()
            ->limit($limit)
            ->get();
    }
    
    /**
     * Check if product is available
     *
     * @param int $productId
     * @return bool
     */
    public function isProductAvailable(int $productId): bool
    {
        $product = $this->getProductById($productId);
        
        if (!$product) {
            return false;
        }
        
        return $product->status === 'active' && 
               ($product->stock_quantity === null || $product->stock_quantity > 0);
    }
    
    /**
     * Decrease product stock
     *
     * @param int $productId
     * @param int $quantity
     * @return bool
     */
    public function decreaseStock(int $productId, int $quantity = 1): bool
    {
        $product = $this->getProductById($productId);
        
        if (!$product || $product->stock_quantity === null) {
            return false;
        }
        
        if ($product->stock_quantity < $quantity) {
            return false;
        }
        
        $product->update(['stock_quantity' => $product->stock_quantity - $quantity]);
        
        return true;
    }
    
    /**
     * Increase product stock
     *
     * @param int $productId
     * @param int $quantity
     * @return bool
     */
    public function increaseStock(int $productId, int $quantity = 1): bool
    {
        $product = $this->getProductById($productId);
        
        if (!$product || $product->stock_quantity === null) {
            return false;
        }
        
        $product->update(['stock_quantity' => $product->stock_quantity + $quantity]);
        
        return true;
    }
    
    /**
     * Get product validation rules
     *
     * @return array
     */
    public function getValidationRules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'slug' => 'nullable|string|max:255|unique:products,slug',
            'description' => 'required|string',
            'short_description' => 'nullable|string|max:500',
            'price' => 'required|numeric|min:0',
            'sale_price' => 'nullable|numeric|min:0',
            'type' => 'required|string|in:course,subscription,physical,digital',
            'status' => 'required|string|in:draft,active,inactive',
            'is_featured' => 'boolean',
            'stock_quantity' => 'nullable|integer|min:0',
            'sku' => 'nullable|string|max:100|unique:products,sku',
            'course_id' => 'nullable|integer|exists:courses,id',
            'features' => 'nullable|array',
            'features.*' => 'string',
            'pricing_options' => 'nullable|array',
            'pricing_options.*.name' => 'required|string',
            'pricing_options.*.price' => 'required|numeric|min:0',
            'pricing_options.*.description' => 'nullable|string',
            'metadata' => 'nullable|array'
        ];
    }
}
