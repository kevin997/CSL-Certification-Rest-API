<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentGatewaySetting;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductReview;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\CourseSectionItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class StorefrontController extends Controller
{
    /**
     * Get the environment by ID
     *
     * @param string $environmentId
     * @return Environment|null
     */
    protected function getEnvironmentById(string $environmentId)
    {
        return Environment::find($environmentId);
    }

    /**
     * Get featured products for an environment
     *
     * @param Request $request
     * @param string $environmentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getFeaturedProducts(Request $request, string $environmentId)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        $products = Product::where('environment_id', $environment->id)
            ->where('is_featured', true)
            ->where('status', 'active')
            ->with('category')
            ->limit(6)
            ->get();
        
        return response()->json(['data' => $products]);
    }

    /**
     * Get all products for an environment with pagination and filtering
     *
     * @param Request $request
     * @param string $environmentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAllProducts(Request $request, string $environmentId)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        $query = Product::where('environment_id', $environment->id)
            ->where('status', 'active')
            ->with('category');
        
        // Apply filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }
        
        if ($request->has('category')) {
            $categoryIds = explode(',', $request->input('category'));
            $query->whereIn('category_id', $categoryIds);
        }
        
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->input('min_price'));
        }
        
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->input('max_price'));
        }
        
        // Apply sorting
        $sortField = 'created_at';
        $sortDirection = 'desc';
        
        if ($request->has('sort')) {
            switch ($request->input('sort')) {
                case 'price_low':
                    $sortField = 'price';
                    $sortDirection = 'asc';
                    break;
                case 'price_high':
                    $sortField = 'price';
                    $sortDirection = 'desc';
                    break;
                case 'name_asc':
                    $sortField = 'name';
                    $sortDirection = 'asc';
                    break;
                case 'name_desc':
                    $sortField = 'name';
                    $sortDirection = 'desc';
                    break;
                case 'oldest':
                    $sortField = 'created_at';
                    $sortDirection = 'asc';
                    break;
            }
        }
        
        $query->orderBy($sortField, $sortDirection);
        
        // Get pagination parameters
        $perPage = $request->input('per_page', 12);
        
        // Get categories for filtering
        $categories = ProductCategory::where('environment_id', $environment->id)
            ->get(['id', 'name', 'slug']);
        
        $products = $query->paginate($perPage);
        
        return response()->json([
            'data' => $products->items(),
            'meta' => [
                'current_page' => $products->currentPage(),
                'from' => $products->firstItem(),
                'last_page' => $products->lastPage(),
                'path' => $request->url(),
                'per_page' => $products->perPage(),
                'to' => $products->lastItem(),
                'total' => $products->total(),
            ],
            'categories' => $categories,
        ]);
    }

    /**
     * Get a product by slug
     *
     * @param Request $request
     * @param string $environmentId
     * @param string $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProductBySlug(Request $request, string $environmentId, string $slug)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        $product = Product::where('environment_id', $environment->id)
            ->where('slug', $slug)
            ->with(['category', 'courses'])
            ->first();
        
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        
        // Get related products
        $relatedProducts = Product::where('environment_id', $environment->id)
            ->where('id', '!=', $product->id)
            ->where('status', 'active')
            ->where(function($query) use ($product) {
                $query->where('category_id', $product->category_id)
                      ->orWhere('is_featured', true);
            })
            ->limit(4)
            ->get();
        
        $product->related_products = $relatedProducts;
        
        return response()->json(['data' => $product]);
    }
    
    /**
     * Get a product by ID
     *
     * @param Request $request
     * @param string $environmentId
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProductById(Request $request, string $environmentId, int $id)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        $product = Product::where('environment_id', $environment->id)
            ->where('id', $id)
            ->with(['category', 'courses'])
            ->first();
        
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        
        // Get related products
        $relatedProducts = Product::where('environment_id', $environment->id)
            ->where('id', '!=', $product->id)
            ->where('status', 'active')
            ->where(function($query) use ($product) {
                $query->where('category_id', $product->category_id)
                      ->orWhere('is_featured', true);
            })
            ->limit(4)
            ->get();
        
        $product->related_products = $relatedProducts;
        
        return response()->json(['data' => $product]);
    }

    /**
     * Get product categories
     *
     * @param Request $request
     * @param string $environmentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCategories(Request $request, string $environmentId)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        $categories = ProductCategory::where('environment_id', $environment->id)
            ->get();
        
        return response()->json(['data' => $categories]);
    }

    /**
     * Get available payment methods
     *
     * @param Request $request
     * @param string $environmentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentMethods(Request $request, string $environmentId)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        $paymentMethods = PaymentGatewaySetting::where('environment_id', $environment->id)
            ->where('is_active', true)
            ->with('paymentGateway')
            ->get()
            ->map(function($method) {
                return [
                    'id' => $method->id,
                    'name' => $method->paymentGateway->name,
                    'type' => $method->paymentGateway->type,
                    'logo' => $method->paymentGateway->logo_url,
                    'description' => $method->paymentGateway->description,
                ];
            });
        
        return response()->json(['data' => $paymentMethods]);
    }
    
    /**
     * Get payment gateways
     *
     * @param Request $request
     * @param string $environmentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPaymentGateways(Request $request, string $environmentId)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        // Get active payment gateways for this environment
        $gateways = PaymentGatewaySetting::where('environment_id', $environment->id)
            ->where('status', true)
            ->orderBy('sort_order')
            ->get();
        
        return response()->json(['data' => $gateways]);
    }

    /**
     * Process checkout
     *
     * @param Request $request
     * @param string $environmentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkout(Request $request, string $environmentId)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        // Validate the request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|exists:payment_gateway_settings,id',
            'billing_address' => 'required|string|max:255',
            'billing_city' => 'required|string|max:255',
            'billing_state' => 'required|string|max:255',
            'billing_zip' => 'required|string|max:20',
            'billing_country' => 'required|string|max:255',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }
        
        try {
            DB::beginTransaction();
            
            // Find or create user by email
            $user = User::firstOrCreate(
                ['email' => $request->input('email')],
                [
                    'name' => $request->input('name'),
                    'password' => bcrypt(Str::random(16)),
                ]
            );
            
            // Create order
            $order = new Order();
            $order->user_id = $user->id;
            $order->environment_id = $environment->id;
            $order->order_number = 'ORD-' . strtoupper(Str::random(8));
            $order->status = 'pending';
            $order->payment_method = $request->input('payment_method');
            $order->billing_name = $request->input('name');
            $order->billing_email = $request->input('email');
            $order->billing_address = $request->input('billing_address');
            $order->billing_city = $request->input('billing_city');
            $order->billing_state = $request->input('billing_state');
            $order->billing_zip = $request->input('billing_zip');
            $order->billing_country = $request->input('billing_country');
            $order->notes = $request->input('notes');
            $order->referral_id = $request->input('referral_id');
            $order->save();
            
            // Calculate total amount
            $totalAmount = 0;
            
            // Add order items
            foreach ($request->input('products') as $item) {
                $product = Product::findOrFail($item['id']);
                
                // Skip if product doesn't belong to this environment
                if ($product->environment_id !== $environment->id) {
                    continue;
                }
                
                $price = $product->discount_price ?? $product->price;
                $quantity = $item['quantity'];
                $total = $price * $quantity;
                
                $orderItem = new OrderItem();
                $orderItem->order_id = $order->id;
                $orderItem->product_id = $product->id;
                $orderItem->quantity = $quantity;
                $orderItem->price = $price;
                $orderItem->total = $total;
                $orderItem->is_subscription = $product->is_subscription;
                $orderItem->save();
                
                $totalAmount += $total;
            }
            
            // Update order total
            $order->total_amount = $totalAmount;
            $order->currency = 'USD'; // Default currency
            $order->save();
            
            // Create transaction
            $transaction = new Transaction();
            $transaction->transaction_id = (string) Str::uuid();
            $transaction->environment_id = $environment->id;
            $transaction->payment_gateway_setting_id = $request->input('payment_method');
            $transaction->order_id = $order->id;
            $transaction->customer_id = $user->id;
            $transaction->customer_email = $user->email;
            $transaction->customer_name = $user->name;
            $transaction->amount = $totalAmount;
            $transaction->total_amount = $totalAmount;
            $transaction->currency = 'USD';
            $transaction->status = Transaction::STATUS_PENDING;
            $transaction->payment_method = 'credit_card'; // Default payment method
            $transaction->description = "Payment for order {$order->order_number}";
            $transaction->ip_address = $request->ip();
            $transaction->user_agent = $request->userAgent();
            $transaction->save();
            
            DB::commit();
            
            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'transaction_id' => $transaction->transaction_id,
                    'total_amount' => $totalAmount,
                    'status' => 'pending',
                    'payment_url' => route('api.transactions.process', ['id' => $transaction->id]),
                ]
            ]);
            
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to process checkout',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get product reviews
     *
     * @param Request $request
     * @param string $environmentId
     * @param int $productId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProductReviews(Request $request, string $environmentId, int $productId)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        $product = Product::where('environment_id', $environment->id)
            ->where('id', $productId)
            ->first();
            
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        
        // Get approved reviews for this product
        $reviews = ProductReview::where('product_id', $product->id)
            ->where('environment_id', $environment->id)
            ->where('status', 'approved')
            ->orderBy('created_at', 'desc')
            ->get();
            
        // Get average rating
        $averageRating = $reviews->avg('rating') ?: 0;
        
        return response()->json([
            'data' => $reviews,
            'average_rating' => round($averageRating, 1),
            'total_reviews' => $reviews->count()
        ]);
    }
    
    /**
     * Submit a product review
     *
     * @param Request $request
     * @param string $environmentId
     * @param int $productId
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitProductReview(Request $request, string $environmentId, int $productId)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        $product = Product::where('environment_id', $environment->id)
            ->where('id', $productId)
            ->first();
            
        if (!$product) {
            return response()->json(['message' => 'Product not found'], 404);
        }
        
        // Validate the request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['message' => 'Validation failed', 'errors' => $validator->errors()], 422);
        }
        
        // Check if user is authenticated
        $userId = null;
        if ($request->user()) {
            $userId = $request->user()->id;
        }
        
        // Create the review
        $review = new ProductReview();
        $review->product_id = $product->id;
        $review->environment_id = $environment->id;
        $review->user_id = $userId;
        $review->name = $request->name;
        $review->email = $request->email;
        $review->rating = $request->rating;
        $review->comment = $request->comment;
        $review->status = 'pending'; // Reviews are pending by default
        $review->save();
        
        return response()->json(['message' => 'Review submitted successfully', 'data' => $review]);
    }
    
    /**
     * Get all courses for an environment
     *
     * @param Request $request
     * @param string $environmentId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCourses(Request $request, string $environmentId)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        $query = Course::where('environment_id', $environment->id);
        
        // Filter by status (default to published)
        $status = $request->get('status', 'published');
        if ($status !== 'all') {
            $query->where('status', $status);
        }
        
        // Filter by featured
        if ($request->has('featured')) {
            $query->where('is_featured', true);
        }
        
        // Search by title
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where('title', 'like', "%{$search}%");
        }
        
        // Pagination
        $perPage = $request->get('per_page', 12);
        $courses = $query->orderBy('created_at', 'desc')
            ->paginate($perPage);
        
        return response()->json(['data' => $courses]);
    }
    
    /**
     * Get a course by slug
     *
     * @param Request $request
     * @param string $environmentId
     * @param string $slug
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCourseBySlug(Request $request, string $environmentId, string $slug)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        $course = Course::where('environment_id', $environment->id)
            ->where('slug', $slug)
            ->with([
                'sections' => function($query) {
                    $query->orderBy('order');
                },
                'sections.items' => function($query) {
                    $query->orderBy('order');
                },
                'sections.items.activity'
            ])
            ->first();
        
        if (!$course) {
            return response()->json(['message' => 'Course not found'], 404);
        }
        
        // Get related courses
        $relatedCourses = Course::where('environment_id', $environment->id)
            ->where('id', '!=', $course->id)
            ->where('status', 'published')
            ->where(function($query) use ($course) {
                $query->where('difficulty_level', $course->difficulty_level)
                      ->orWhere('is_featured', true);
            })
            ->limit(4)
            ->get();
        
        $course->related_courses = $relatedCourses;
        
        return response()->json(['data' => $course]);
    }
    
    /**
     * Get a course by ID
     *
     * @param Request $request
     * @param string $environmentId
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCourseById(Request $request, string $environmentId, int $id)
    {
        $environment = $this->getEnvironmentById($environmentId);
        
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }
        
        $course = Course::where('environment_id', $environment->id)
            ->where('id', $id)
            ->with([
                'sections' => function($query) {
                    $query->orderBy('order');
                },
                'sections.items' => function($query) {
                    $query->orderBy('order');
                },
                'sections.items.activity'
            ])
            ->first();
        
        if (!$course) {
            return response()->json(['message' => 'Course not found'], 404);
        }
        
        // Get related courses
        $relatedCourses = Course::where('environment_id', $environment->id)
            ->where('id', '!=', $course->id)
            ->where('status', 'published')
            ->where(function($query) use ($course) {
                $query->where('difficulty_level', $course->difficulty_level)
                      ->orWhere('is_featured', true);
            })
            ->limit(4)
            ->get();
        
        $course->related_courses = $relatedCourses;
        
        return response()->json(['data' => $course]);
    }
}
