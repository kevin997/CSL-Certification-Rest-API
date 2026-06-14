<?php

namespace App\Http\Controllers\Api\Sales;

use App\Events\OrderCompleted;
use App\Events\UserCreatedDuringCheckout;
use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\SalesForm;
use App\Models\SalesFormSubmission;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SalesFormSubmissionController extends Controller
{
    /**
     * Public: render a published sales form by slug.
     *
     * GET /api/sales-forms/public/{slug}
     */
    public function publicShow($slug)
    {
        $form = SalesForm::withoutGlobalScopes()
            ->with(['fields', 'products:id,name,slug,price,currency,thumbnail_path'])
            ->where('slug', $slug)
            ->where('status', SalesForm::STATUS_PUBLISHED)
            ->first();

        if (!$form) {
            return response()->json([
                'success' => false,
                'message' => 'Form not found or not published.',
            ], 404);
        }

        // Track a view (non-blocking)
        $form->increment('views_count');

        return response()->json([
            'success' => true,
            'data' => $form,
        ]);
    }

    /**
     * Public: submit a sales form. Runs the pre-enrollment workflow.
     *
     * POST /api/sales-forms/public/{slug}/submit
     */
    public function submit(Request $request, $slug)
    {
        $form = SalesForm::withoutGlobalScopes()
            ->with(['fields', 'products', 'accessBlocks'])
            ->where('slug', $slug)
            ->where('status', SalesForm::STATUS_PUBLISHED)
            ->first();

        if (!$form) {
            return response()->json([
                'success' => false,
                'message' => 'Form not found or not published.',
            ], 404);
        }

        // Build dynamic validation rules from the form fields.
        $rules = ['answers' => 'required|array'];
        foreach ($form->fields as $field) {
            $key = 'answers.' . $field->field_key;
            $fieldRules = $field->is_required ? ['required'] : ['nullable'];
            if ($field->type === 'email') {
                $fieldRules[] = 'email';
            }
            $rules[$key] = $fieldRules;
        }
        // Email + name + password are required for learner identity / account creation,
        // regardless of field config.
        $rules['email'] = 'required|email|max:255';
        $rules['name'] = 'required|string|max:255';
        $rules['password'] = 'required|string|min:8';

        $validator = Validator::make($request->all(), $rules);
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        // Resolve which products to enroll into. A product_select field may carry
        // selected ids; otherwise default to all products attached to the form.
        $selectedProductIds = $this->resolveSelectedProductIds($form, $request->input('answers', []));
        $products = $form->products->whereIn('id', $selectedProductIds)->values();
        if ($products->isEmpty()) {
            $products = $form->products;
        }

        if ($products->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'This form has no products attached.',
            ], 400);
        }

        $environmentId = $form->environment_id;
        $environment = Environment::find($environmentId);
        if (!$environment) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid environment.',
            ], 400);
        }

        try {
            DB::beginTransaction();

            // 1. Find-or-create the learner by email, using the password they set.
            $userExisted = false;
            $user = User::where('email', $request->email)->first();
            if (!$user) {
                $user = User::create([
                    'name' => $request->name,
                    'email' => $request->email,
                    'password' => Hash::make($request->password),
                    'role' => 'learner',
                ]);
            } else {
                $userExisted = true;
                $this->ensureEnvironmentMembership($user, $environment);
            }

            // 2. Generate access code + persist submission.
            $phone = $this->extractFieldValue($form, $request->input('answers', []), 'phone');
            $submission = SalesFormSubmission::create([
                'sales_form_id' => $form->id,
                'environment_id' => $environmentId,
                'user_id' => $user->id,
                'access_code' => SalesFormSubmission::generateUniqueAccessCode(),
                'answers' => $request->input('answers', []),
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $phone,
                'status' => SalesFormSubmission::STATUS_PENDING,
            ]);

            $form->increment('submissions_count');

            // 3. Provisional enrollments for each course in each selected product.
            $enrolledCourseIds = [];
            foreach ($products as $product) {
                foreach ($product->courses as $course) {
                    if (in_array($course->id, $enrolledCourseIds)) {
                        continue;
                    }
                    $existing = Enrollment::withoutGlobalScopes()
                        ->where('user_id', $user->id)
                        ->where('course_id', $course->id)
                        ->where('environment_id', $environmentId)
                        ->whereNull('deleted_at')
                        ->first();

                    if (!$existing) {
                        Enrollment::create([
                            'user_id' => $user->id,
                            'course_id' => $course->id,
                            'environment_id' => $environmentId,
                            'status' => Enrollment::STATUS_ENROLLED,
                            'progress_percentage' => 0,
                            'last_activity_at' => now(),
                            'is_provisional' => true,
                            'sales_form_id' => $form->id,
                        ]);
                    }
                    $enrolledCourseIds[] = $course->id;
                }
            }

            // 4. One PENDING order per product, with a payment link.
            $orders = [];
            foreach ($products as $product) {
                $price = $product->discount_price ?? $product->price ?? 0;
                $order = Order::create([
                    'user_id' => $user->id,
                    'environment_id' => $environmentId,
                    'order_number' => 'ORD-' . strtoupper(Str::random(8)),
                    'status' => Order::STATUS_PENDING,
                    'type' => Order::TYPE_SALES_FORM,
                    'total_amount' => $price,
                    'currency' => $product->currency ?? 'USD',
                    'payment_method' => 'sales_form',
                    'billing_name' => $user->name,
                    'billing_email' => $user->email,
                    'sales_form_submission_id' => $submission->id,
                ]);

                OrderItem::create([
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'price' => $price,
                    'discount' => 0,
                    'total' => $price,
                    'is_subscription' => false,
                ]);

                $order->setRelation('environment', $environment);
                $orders[] = [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'product' => ['id' => $product->id, 'name' => $product->name],
                    'amount' => $price,
                    'currency' => $order->currency,
                    'status' => $order->status,
                    'payment_url' => $order->continue_payment_url,
                ];
            }

            DB::commit();

            if (!$userExisted) {
                event(new UserCreatedDuringCheckout($user, $environment, true));
            }

            return response()->json([
                'success' => true,
                'message' => $userExisted
                    ? 'An account with this email already exists. We have added this space to your account. Log in with your existing KURSA password to continue.'
                    : 'Your account has been created. Log in to access your course.',
                'user_existed' => $userExisted,
                'email' => $user->email,
                'login_url' => 'https://' . $environment->primary_domain . '/auth/login',
                'access_code' => $submission->access_code,
                'submission_id' => $submission->id,
                'orders' => $orders,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Sales form submission failed', [
                'slug' => $slug,
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit form: ' . $e->getMessage(),
            ], 500);
        }
    }

    private function ensureEnvironmentMembership(User $user, Environment $environment): void
    {
        EnvironmentUser::firstOrCreate(
            [
                'environment_id' => $environment->id,
                'user_id' => $user->id,
            ],
            [
                'role' => 'learner',
                'permissions' => [],
                'joined_at' => now(),
                'use_environment_credentials' => false,
                'is_account_setup' => false,
            ]
        );
    }

    /**
     * Trainer: manually complete an order (offline payment received).
     * Completes the order, records a transaction + commission, and lifts
     * provisional access on the related enrollments.
     *
     * POST /api/sales-forms/orders/{orderId}/complete
     */
    public function completeOrder($orderId)
    {
        $order = Order::with(['items.product.courses', 'salesFormSubmission'])
            ->where('type', Order::TYPE_SALES_FORM)
            ->findOrFail($orderId);

        if ($order->status === Order::STATUS_COMPLETED) {
            return response()->json([
                'success' => false,
                'message' => 'Order is already completed.',
            ], 400);
        }

        try {
            DB::beginTransaction();

            $order->update(['status' => Order::STATUS_COMPLETED]);

            // Record a transaction for commission tracking.
            $transaction = Transaction::create([
                'order_id' => $order->id,
                'environment_id' => $order->environment_id,
                'customer_id' => $order->user_id,
                'customer_email' => $order->billing_email,
                'customer_name' => $order->billing_name,
                'amount' => $order->total_amount,
                'fee_amount' => 0,
                'tax_amount' => 0,
                'total_amount' => $order->total_amount,
                'currency' => $order->currency,
                'status' => Transaction::STATUS_COMPLETED,
                'payment_method' => 'sales_form_manual',
                'description' => 'Sales form order manual completion: ' . $order->order_number,
                'paid_at' => now(),
            ]);

            if ($order->total_amount > 0) {
                try {
                    app(\App\Services\InstructorCommissionService::class)->createCommissionRecord($transaction);
                } catch (\Exception $e) {
                    Log::error('Failed to create commission for sales form order', [
                        'order_id' => $order->id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // Lift provisional access on enrollments for the courses in this order.
            $courseIds = $order->items
                ->flatMap(fn ($item) => optional($item->product)->courses ?? collect())
                ->pluck('id')
                ->unique()
                ->all();

            if (!empty($courseIds)) {
                Enrollment::withoutGlobalScopes()
                    ->where('user_id', $order->user_id)
                    ->where('environment_id', $order->environment_id)
                    ->whereIn('course_id', $courseIds)
                    ->where('is_provisional', true)
                    ->update(['is_provisional' => false]);
            }

            // If all orders on the submission are completed, mark it completed.
            if ($order->salesFormSubmission) {
                $pendingCount = Order::withoutGlobalScopes()
                    ->where('sales_form_submission_id', $order->sales_form_submission_id)
                    ->where('status', '!=', Order::STATUS_COMPLETED)
                    ->count();
                if ($pendingCount === 0) {
                    $order->salesFormSubmission->update(['status' => SalesFormSubmission::STATUS_COMPLETED]);
                }
            }

            DB::commit();

            event(new OrderCompleted($order));

            return response()->json([
                'success' => true,
                'message' => 'Order completed and access unlocked.',
                'data' => $order->fresh(),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete order: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Resolve selected product ids from a product_select field answer.
     */
    private function resolveSelectedProductIds(SalesForm $form, array $answers): array
    {
        foreach ($form->fields as $field) {
            if ($field->type === 'product_select') {
                $value = $answers[$field->field_key] ?? null;
                if (is_array($value)) {
                    return array_map('intval', $value);
                }
                if (!empty($value)) {
                    return [(int) $value];
                }
            }
        }
        return [];
    }

    /**
     * Extract the first value for a given field type from answers.
     */
    private function extractFieldValue(SalesForm $form, array $answers, string $type): ?string
    {
        foreach ($form->fields as $field) {
            if ($field->type === $type) {
                $value = $answers[$field->field_key] ?? null;
                return is_scalar($value) ? (string) $value : null;
            }
        }
        return null;
    }
}
