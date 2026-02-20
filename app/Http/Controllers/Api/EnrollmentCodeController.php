<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnrollmentCode;
use App\Events\UserCreatedDuringCheckout;
use App\Events\OrderCompleted;
use App\Models\Environment;
use App\Models\Product;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Carbon\Carbon;

class EnrollmentCodeController extends Controller
{
    /**
     * Generate new enrollment codes for a product.
     *
     * POST /api/enrollment-codes/generate
     */
    public function generate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id',
            'quantity' => 'required|integer|min:1|max:1000',
            'expires_at' => 'nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        // Check if user has permission to create codes for this product
        $product = Product::findOrFail($request->product_id);

        if ($user->id !== $product->created_by && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to create codes for this product',
            ], 403);
        }

        $codes = [];

        try {
            DB::beginTransaction();

            for ($i = 0; $i < $request->quantity; $i++) {
                $code = EnrollmentCode::create([
                    'product_id' => $request->product_id,
                    'code' => EnrollmentCode::generateUniqueCode(),
                    'status' => 'active',
                    'created_by' => $user->id,
                    'expires_at' => $request->expires_at ? Carbon::parse($request->expires_at) : null,
                ]);

                // Load relationships
                $code->load(['product', 'creator']);
                $codes[] = $code;
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => count($codes) . ' enrollment codes generated successfully',
                'data' => $codes,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate codes: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get enrollment codes with filters.
     *
     * GET /api/enrollment-codes
     */
    public function index(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'nullable|integer|exists:products,id',
            'status' => ['nullable', Rule::in(['active', 'used', 'expired', 'deactivated'])],
            'search' => 'nullable|string|max:4',
            'created_by' => 'nullable|integer|exists:users,id',
            'used_by' => 'nullable|integer|exists:users,id',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = EnrollmentCode::query();

        // Apply filters
        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('search')) {
            $query->where('code', 'like', '%' . strtoupper($request->search) . '%');
        }

        if ($request->has('created_by')) {
            $query->where('created_by', $request->created_by);
        }

        if ($request->has('used_by')) {
            $query->where('used_by', $request->used_by);
        }

        // Load relationships
        $query->with(['product', 'creator', 'user', 'deactivator']);

        // Order by most recent first
        $query->orderBy('created_at', 'desc');

        // Paginate
        $perPage = $request->get('per_page', 10);
        $codes = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $codes->items(),
            'meta' => [
                'current_page' => $codes->currentPage(),
                'from' => $codes->firstItem(),
                'last_page' => $codes->lastPage(),
                'per_page' => $codes->perPage(),
                'to' => $codes->lastItem(),
                'total' => $codes->total(),
            ],
        ]);
    }

    /**
     * Get statistics for a product's enrollment codes.
     *
     * GET /api/enrollment-codes/statistics/{productId}
     */
    public function statistics($productId)
    {
        $product = Product::findOrFail($productId);

        $totalCodes = EnrollmentCode::where('product_id', $productId)->count();
        $activeCodes = EnrollmentCode::where('product_id', $productId)->where('status', 'active')->count();
        $usedCodes = EnrollmentCode::where('product_id', $productId)->where('status', 'used')->count();
        $expiredCodes = EnrollmentCode::where('product_id', $productId)->where('status', 'expired')->count();
        $deactivatedCodes = EnrollmentCode::where('product_id', $productId)->where('status', 'deactivated')->count();

        $usageRate = $totalCodes > 0 ? ($usedCodes / $totalCodes) * 100 : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'product_id' => (int) $productId,
                'total_codes' => $totalCodes,
                'active_codes' => $activeCodes,
                'used_codes' => $usedCodes,
                'expired_codes' => $expiredCodes,
                'deactivated_codes' => $deactivatedCodes,
                'usage_rate' => round($usageRate, 2),
            ],
        ]);
    }

    /**
     * Redeem an enrollment code.
     *
     * POST /api/enrollment-codes/redeem
     */
    public function redeem(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|size:4',
            'product_id' => 'required|integer|exists:products,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();
        $codeString = strtoupper($request->code);

        // Find the code
        $enrollmentCode = EnrollmentCode::where('code', $codeString)->first();

        if (!$enrollmentCode) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid enrollment code. Please check and try again.',
            ], 404);
        }

        // Validate product
        if ($enrollmentCode->product_id != $request->product_id) {
            return response()->json([
                'success' => false,
                'message' => 'This code is not valid for this product.',
            ], 400);
        }

        // Check if code is already used
        if ($enrollmentCode->status === 'used') {
            return response()->json([
                'success' => false,
                'message' => 'This code has already been used.',
            ], 400);
        }

        // Check if code is deactivated
        if ($enrollmentCode->status === 'deactivated') {
            return response()->json([
                'success' => false,
                'message' => 'This code has been deactivated and is no longer valid.',
            ], 400);
        }

        // Check if code is expired
        if ($enrollmentCode->isExpired()) {
            $enrollmentCode->update(['status' => 'expired']);
            return response()->json([
                'success' => false,
                'message' => 'This code has expired.',
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Get product and its courses
            $product = Product::with(['category'])->find($request->product_id);

            // Get the courses associated with this product
            $productCourses = DB::table('product_courses')
                ->where('product_id', $request->product_id)
                ->get();

            if ($productCourses->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'This product has no courses associated with it.',
                ], 400);
            }

            $enrolledCourses = [];

            // Get environment_id (default to 1 if not set in request)
            $environmentId = session('current_environment_id');

            // Create enrollments for each course in the product
            foreach ($productCourses as $productCourse) {
                // Check if already enrolled in this course
                $existingEnrollment = DB::table('enrollments')
                    ->where('user_id', $user->id)
                    ->where('course_id', $productCourse->course_id)
                    ->where('environment_id', $environmentId)
                    ->whereNull('deleted_at')
                    ->first();

                if (!$existingEnrollment) {
                    $enrollment = Enrollment::create([
                        'user_id' => $user->id,
                        'course_id' => $productCourse->course_id,
                        'environment_id' => $environmentId,
                        'status' => Enrollment::STATUS_ENROLLED,
                        'progress_percentage' => 0,
                        'last_activity_at' => now(),
                    ]);

                    $enrolledCourses[] = [
                        'id' => $enrollment->id,
                        'course_id' => $enrollment->course_id,
                    ];
                }
            }

            // Create order for commission tracking (using actual product price)
            $productPrice = $product->price ?? 0;
            $order = Order::create([
                'user_id' => $user->id,
                'environment_id' => $environmentId,
                'order_number' => 'ORD-' . strtoupper(Str::random(8)),
                'status' => Order::STATUS_COMPLETED,
                'type' => Order::TYPE_ENROLLMENT_CODE,
                'total_amount' => $productPrice,
                'currency' => $product->currency ?? 'USD',
                'payment_method' => 'enrollment_code',
                'billing_name' => $user->name,
                'billing_email' => $user->email,
            ]);

            // Create order item for the product
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'price' => $productPrice,
                'discount' => 0,
                'total' => $productPrice,
                'is_subscription' => false,
            ]);

            // Create transaction for commission tracking
            $transaction = Transaction::create([
                'order_id' => $order->id,
                'environment_id' => $environmentId,
                'customer_id' => $user->id,
                'customer_email' => $user->email,
                'customer_name' => $user->name,
                'amount' => $productPrice,
                'fee_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => $productPrice,
                'currency' => $product->currency ?? 'USD',
                'status' => Transaction::STATUS_COMPLETED,
                'payment_method' => 'enrollment_code',
                'description' => 'Enrollment code redemption: ' . $enrollmentCode->code,
                'paid_at' => now(),
            ]);

            // Create commission record if product has value
            if ($productPrice > 0) {
                try {
                    $commissionService = app(\App\Services\InstructorCommissionService::class);
                    $commissionService->createCommissionRecord($transaction);

                    Log::info('Commission created for enrollment code redemption', [
                        'order_id' => $order->id,
                        'transaction_id' => $transaction->id,
                        'product_id' => $product->id,
                        'product_price' => $productPrice,
                        'code' => $enrollmentCode->code,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to create commission for enrollment code', [
                        'order_id' => $order->id,
                        'transaction_id' => $transaction->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Don't fail the redemption if commission creation fails
                }
            }

            // Mark code as used
            $enrollmentCode->markAsUsed($user->id);

            DB::commit();

            // Fire OrderCompleted event for additional processing
            event(new OrderCompleted($order));

            return response()->json([
                'success' => true,
                'message' => 'Successfully enrolled in the course!',
                'enrollment' => [
                    'id' => $enrolledCourses[0]['id'] ?? null,
                    'product_id' => $request->product_id,
                    'user_id' => $user->id,
                    'enrollment_date' => Carbon::now()->toISOString(),
                    'courses_enrolled' => count($enrolledCourses),
                ],
                'product' => [
                    'id' => $product->id,
                    'title' => $product->name,
                    'slug' => $product->slug ?? '',
                    'thumbnail' => $product->thumbnail_path,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to redeem code: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Redeem enrollment code with account creation (public endpoint).
     *
     * POST /api/enrollment-codes/redeem-with-registration
     */
    public function redeemWithRegistration(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|size:4',
            'product_id' => 'required|integer|exists:products,id',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $codeString = strtoupper($request->code);

        // Find the code
        $enrollmentCode = EnrollmentCode::where('code', $codeString)->first();

        if (!$enrollmentCode) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid enrollment code. Please check and try again.',
            ], 404);
        }

        // Validate product
        if ($enrollmentCode->product_id != $request->product_id) {
            return response()->json([
                'success' => false,
                'message' => 'This code is not valid for this product.',
            ], 400);
        }

        // Check if code is already used
        if ($enrollmentCode->status === 'used') {
            return response()->json([
                'success' => false,
                'message' => 'This code has already been used.',
            ], 400);
        }

        // Check if code is deactivated
        if ($enrollmentCode->status === 'deactivated') {
            return response()->json([
                'success' => false,
                'message' => 'This code has been deactivated and is no longer valid.',
            ], 400);
        }

        // Check if code is expired
        if ($enrollmentCode->isExpired()) {
            $enrollmentCode->update(['status' => 'expired']);
            return response()->json([
                'success' => false,
                'message' => 'This code has expired.',
            ], 400);
        }

        try {
            DB::beginTransaction();

            // Check if user account already exists
            $userExisted = false;
            $existingUser = User::where('email', $request->email)->first();

            if ($existingUser) {
                // User exists — verify the provided password
                if (!Hash::check($request->password, $existingUser->password)) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'An account with this email already exists. Please enter the correct password for your account.',
                    ], 422);
                }

                // Reject non-learner users (admins, teachers, sales agents, etc.)
                $userRole = $existingUser->role instanceof \App\Enums\UserRole
                    ? $existingUser->role->value
                    : $existingUser->role;

                if ($userRole !== \App\Enums\UserRole::LEARNER->value) {
                    DB::rollBack();
                    return response()->json([
                        'success' => false,
                        'message' => 'This account is not eligible for enrollment code redemption. Please contact support.',
                    ], 403);
                }

                $user = $existingUser;
                $userExisted = true;

                // Authenticate the existing user
                Auth::login($user);
                $request->session()->regenerate();

                Log::info('Enrollment code: Existing user authenticated during redemption', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                ]);
            } else {
                // Create new user account
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'role' => 'learner',
                ]);
            }

            // Get product and its courses
            $product = Product::with(['category'])->find($request->product_id);

            // Get the courses associated with this product
            $productCourses = DB::table('product_courses')
                ->where('product_id', $request->product_id)
                ->get();

            if ($productCourses->isEmpty()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'This product has no courses associated with it.',
                ], 400);
            }

            $enrolledCourses = [];

            // Get environment_id
            $environmentId = session('current_environment_id');

            $environment = Environment::find($environmentId);
            if (!$environment) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid environment.',
                ], 400);
            }

            // Only fire UserCreatedDuringCheckout for new users
            // (existing users already have environment membership)
            if (!$userExisted) {
                DB::afterCommit(function () use ($user, $environment) {
                    event(new UserCreatedDuringCheckout($user, $environment, true));
                });
            }

            // Create enrollments for each course in the product
            foreach ($productCourses as $productCourse) {
                // Check if already enrolled (relevant for existing users)
                $existingEnrollment = DB::table('enrollments')
                    ->where('user_id', $user->id)
                    ->where('course_id', $productCourse->course_id)
                    ->where('environment_id', $environmentId)
                    ->whereNull('deleted_at')
                    ->first();

                if (!$existingEnrollment) {
                    $enrollment = Enrollment::create([
                        'user_id' => $user->id,
                        'course_id' => $productCourse->course_id,
                        'environment_id' => $environmentId,
                        'status' => Enrollment::STATUS_ENROLLED,
                        'progress_percentage' => 0,
                        'last_activity_at' => now(),
                    ]);

                    $enrolledCourses[] = [
                        'id' => $enrollment->id,
                        'course_id' => $enrollment->course_id,
                    ];
                }
            }

            // Create order for commission tracking (using actual product price)
            $productPrice = $product->price ?? 0;
            $order = Order::create([
                'user_id' => $user->id,
                'environment_id' => $environmentId,
                'order_number' => 'ORD-' . strtoupper(Str::random(8)),
                'status' => Order::STATUS_COMPLETED,
                'type' => Order::TYPE_ENROLLMENT_CODE,
                'total_amount' => $productPrice,
                'currency' => $product->currency ?? 'USD',
                'payment_method' => 'enrollment_code',
                'billing_name' => $user->name,
                'billing_email' => $user->email,
            ]);

            // Create order item for the product
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => $product->id,
                'quantity' => 1,
                'price' => $productPrice,
                'discount' => 0,
                'total' => $productPrice,
                'is_subscription' => false,
            ]);

            // Create transaction for commission tracking
            $transaction = Transaction::create([
                'order_id' => $order->id,
                'environment_id' => $environmentId,
                'customer_id' => $user->id,
                'customer_email' => $user->email,
                'customer_name' => $user->name,
                'amount' => $productPrice,
                'fee_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => $productPrice,
                'currency' => $product->currency ?? 'USD',
                'status' => Transaction::STATUS_COMPLETED,
                'payment_method' => 'enrollment_code',
                'description' => 'Enrollment code redemption: ' . $enrollmentCode->code,
                'paid_at' => now(),
            ]);

            // Create commission record if product has value
            if ($productPrice > 0) {
                try {
                    $commissionService = app(\App\Services\InstructorCommissionService::class);
                    $commissionService->createCommissionRecord($transaction);

                    Log::info('Commission created for enrollment code redemption with registration', [
                        'order_id' => $order->id,
                        'transaction_id' => $transaction->id,
                        'product_id' => $product->id,
                        'product_price' => $productPrice,
                        'code' => $enrollmentCode->code,
                        'user_id' => $user->id,
                    ]);
                } catch (\Exception $e) {
                    Log::error('Failed to create commission for enrollment code with registration', [
                        'order_id' => $order->id,
                        'transaction_id' => $transaction->id,
                        'error' => $e->getMessage(),
                    ]);
                    // Don't fail the redemption if commission creation fails
                }
            }

            // Mark code as used
            $enrollmentCode->markAsUsed($user->id);

            DB::commit();

            // Fire OrderCompleted event for additional processing
            event(new OrderCompleted($order));

            return response()->json([
                'success' => true,
                'message' => $userExisted
                    ? 'Successfully enrolled! Redirecting to your course...'
                    : 'Account created and successfully enrolled! Please login with your credentials.',
                'redirect_to_login' => !$userExisted,
                'user_existed' => $userExisted,
                'enrollment' => [
                    'id' => $enrolledCourses[0]['id'] ?? null,
                    'product_id' => $request->product_id,
                    'user_id' => $user->id,
                    'enrollment_date' => Carbon::now()->toISOString(),
                    'courses_enrolled' => count($enrolledCourses),
                ],
                'product' => [
                    'id' => $product->id,
                    'title' => $product->name,
                    'slug' => $product->slug ?? '',
                    'thumbnail' => $product->thumbnail_path,
                ],
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to redeem code: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Deactivate a single enrollment code.
     *
     * POST /api/enrollment-codes/{id}/deactivate
     */
    public function deactivate($id)
    {
        $enrollmentCode = EnrollmentCode::findOrFail($id);

        $user = Auth::user();

        // Check permissions
        $product = Product::find($enrollmentCode->product_id);
        if ($user->id !== $product->created_by && !$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to deactivate this code',
            ], 403);
        }

        if ($enrollmentCode->status === 'deactivated') {
            return response()->json([
                'success' => false,
                'message' => 'This code is already deactivated',
            ], 400);
        }

        $enrollmentCode->deactivate($user->id);

        return response()->json([
            'success' => true,
            'message' => 'Code deactivated successfully',
            'data' => $enrollmentCode->fresh(['product', 'creator', 'user', 'deactivator']),
        ]);
    }

    /**
     * Bulk deactivate multiple codes.
     *
     * POST /api/enrollment-codes/bulk-deactivate
     */
    public function bulkDeactivate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code_ids' => 'required|array|min:1',
            'code_ids.*' => 'required|integer|exists:enrollment_codes,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = Auth::user();

        try {
            DB::beginTransaction();

            $deactivatedCount = 0;

            foreach ($request->code_ids as $codeId) {
                $enrollmentCode = EnrollmentCode::find($codeId);

                if (!$enrollmentCode) {
                    continue;
                }

                // Check permissions
                $product = Product::find($enrollmentCode->product_id);
                if ($user->id !== $product->created_by && !$user->isAdmin()) {
                    continue;
                }

                if ($enrollmentCode->status !== 'deactivated') {
                    $enrollmentCode->deactivate($user->id);
                    $deactivatedCount++;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $deactivatedCount . ' code(s) deactivated successfully',
                'deactivated_count' => $deactivatedCount,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to deactivate codes: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Export codes to CSV.
     *
     * POST /api/enrollment-codes/export
     */
    public function export(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id',
            'status' => ['nullable', Rule::in(['active', 'used', 'expired', 'deactivated'])],
            'format' => ['nullable', Rule::in(['csv', 'xlsx'])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $query = EnrollmentCode::where('product_id', $request->product_id)
            ->with(['user']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $codes = $query->orderBy('created_at', 'desc')->get();

        // Generate CSV
        $csv = "Code,Status,Created At,Used By,Used At\n";

        foreach ($codes as $code) {
            $csv .= sprintf(
                "%s,%s,%s,%s,%s\n",
                $code->code,
                $code->status,
                $code->created_at->format('Y-m-d H:i:s'),
                $code->user ? $code->user->email : 'N/A',
                $code->used_at ? $code->used_at->format('Y-m-d H:i:s') : 'N/A'
            );
        }

        $filename = 'enrollment-codes-' . $request->product_id . '-' . time() . '.csv';

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    /**
     * Get details of a specific code.
     *
     * GET /api/enrollment-codes/{id}
     */
    public function show($id)
    {
        $enrollmentCode = EnrollmentCode::with(['product', 'creator', 'user', 'deactivator'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $enrollmentCode,
        ]);
    }
}
