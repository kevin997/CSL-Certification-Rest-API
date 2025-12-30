<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\PaymentGatewaySetting;
use App\Models\Product;
use App\Models\ProductSubscription;
use App\Models\User;
use App\Services\Commission\CommissionService;
use App\Services\EnvironmentPaymentConfigService;
use App\Services\OrderService;
use App\Services\PaymentGateways\PaymentGatewayFactory;
use App\Services\PaymentService;
use App\Services\Tax\TaxZoneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SubscriptionProductController extends Controller
{
    private function makePaymentService(): PaymentService
    {
        $orderService = app()->make(OrderService::class);
        $gatewayFactory = app()->make(PaymentGatewayFactory::class);
        $commissionService = app()->make(CommissionService::class);
        $taxZoneService = app()->make(TaxZoneService::class);
        $environmentPaymentConfigService = app()->make(EnvironmentPaymentConfigService::class);

        return new PaymentService($orderService, $gatewayFactory, $commissionService, $taxZoneService, $environmentPaymentConfigService);
    }

    private function buildPaymentResponseData(array $result): array
    {
        $data = [];

        $type = $result['type'] ?? null;
        $value = $result['value'] ?? null;

        if ($type === 'client_secret') {
            $data['payment_type'] = 'stripe';
            $data['client_secret'] = $value;
            $data['publishable_key'] = $result['publishable_key'] ?? null;
            return $data;
        }

        if ($type === 'checkout_url') {
            $data['payment_type'] = 'paypal';
            $data['redirect_url'] = $value;
            return $data;
        }

        if ($type === 'payment_url') {
            $data['payment_type'] = 'lygos';
            $data['redirect_url'] = $value;
            return $data;
        }

        if ($type === 'redirect_url') {
            $data['payment_type'] = 'taramoney';
            $data['redirect_url'] = $result['redirect_url'] ?? $result['general_link'] ?? $value;
            $data['general_link'] = $result['general_link'] ?? null;
            return $data;
        }

        if ($type === 'payment_links') {
            $data['payment_type'] = 'taramoney';
            if (!empty($result['general_link'])) {
                $data['redirect_url'] = $result['general_link'];
                $data['general_link'] = $result['general_link'];
            } else {
                $data['payment_links'] = $result['payment_links'] ?? [];
                $data['whatsapp_link'] = $result['whatsapp_link'] ?? null;
                $data['telegram_link'] = $result['telegram_link'] ?? null;
                $data['dikalo_link'] = $result['dikalo_link'] ?? null;
                $data['sms_link'] = $result['sms_link'] ?? null;
            }
            return $data;
        }

        $data['payment_type'] = 'standard';
        return $data;
    }

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $environmentId = session('current_environment_id');

        $query = ProductSubscription::with(['product', 'environment'])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at');

        if ($environmentId) {
            $query->where('environment_id', $environmentId);
        }

        $subscriptions = $query->paginate((int) $request->input('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => [
                'subscriptions' => $subscriptions,
            ],
        ]);
    }

    public function userSubscriptions(Request $request, int $userId): JsonResponse
    {
        $actor = $request->user();
        if (!$actor) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        if (!$actor->isTeacher() && !$actor->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $environmentId = session('current_environment_id');

        $query = ProductSubscription::with(['product', 'environment'])
            ->where('user_id', $userId)
            ->orderByDesc('created_at');

        if ($environmentId) {
            $query->where('environment_id', $environmentId);
        }

        $subscriptions = $query->paginate((int) $request->input('per_page', 10));

        return response()->json([
            'success' => true,
            'data' => [
                'subscriptions' => $subscriptions,
            ],
        ]);
    }

    public function show(Request $request, int $subscriptionId): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $environmentId = session('current_environment_id');

        $query = ProductSubscription::with(['product', 'environment'])
            ->where('id', $subscriptionId)
            ->where('user_id', $user->id);

        if ($environmentId) {
            $query->where('environment_id', $environmentId);
        }

        $subscription = $query->first();

        if (!$subscription) {
            return response()->json(['message' => 'Subscription not found'], 404);
        }

        $latestRenewalOrderQuery = Order::query()
            ->where('user_id', $user->id)
            ->where('type', Order::TYPE_SUBSCRIPTION_PRODUCT)
            ->whereHas('items', function ($q) use ($subscriptionId) {
                $q->where('subscription_id', $subscriptionId);
            })
            ->orderByDesc('created_at');

        if ($environmentId) {
            $latestRenewalOrderQuery->where('environment_id', $environmentId);
        }

        $latestRenewalOrder = $latestRenewalOrderQuery->first();
        if ($latestRenewalOrder) {
            $subscription->setAttribute('latest_renewal_order', [
                'id' => $latestRenewalOrder->id,
                'status' => $latestRenewalOrder->status,
                'payment_method' => $latestRenewalOrder->payment_method,
                'created_at' => $latestRenewalOrder->created_at,
            ]);
        } else {
            $subscription->setAttribute('latest_renewal_order', null);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'subscription' => $subscription,
            ],
        ]);
    }

    public function subscribe(Request $request, string $environmentId): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $environment = Environment::find($environmentId);

        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone_number' => 'nullable|string|max:20',
            'products' => 'required|array|min:1',
            'products.*.id' => 'required|exists:products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|exists:payment_gateway_settings,id',
            'billing_address' => 'required|string|max:255',
            'billing_city' => 'required|string|max:255',
            'billing_state' => 'required|string|max:255',
            'billing_zip' => 'nullable|string|max:20',
            'billing_country' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        if (strtolower($request->input('email')) !== strtolower($user->email)) {
            return response()->json([
                'message' => 'Email must match authenticated user',
            ], 403);
        }

        $productsInput = $request->input('products');
        $productIds = collect($productsInput)->pluck('id')->unique()->values();
        $products = Product::whereIn('id', $productIds)->get()->keyBy('id');

        foreach ($productsInput as $item) {
            $product = $products->get($item['id']);

            if (!$product || (string) $product->environment_id !== (string) $environment->id) {
                return response()->json(['message' => 'Product not found in this environment'], 404);
            }

            if (!$product->is_subscription) {
                return response()->json([
                    'message' => 'Subscription-only cart enforced: non-subscription product in cart',
                    'product_id' => $product->id,
                ], 422);
            }
        }

        try {
            DB::beginTransaction();

            event(new \App\Events\UserCreatedDuringCheckout($user, $environment, false));

            $totalAmount = 0;
            $orderItems = [];
            foreach ($productsInput as $item) {
                $product = $products->get($item['id']);
                $price = $product->discount_price ?? $product->price;
                $quantity = $item['quantity'];
                $total = $price * $quantity;
                $orderItems[] = ['product' => $product, 'quantity' => $quantity, 'price' => $price, 'total' => $total];
                $totalAmount += $total;
            }

            $firstProductInput = $productsInput[0];
            $firstProduct = $products->get($firstProductInput['id']);

            $order = new Order();
            $order->user_id = $user->id;
            $order->environment_id = $environment->id;
            $order->order_number = 'ORD-' . strtoupper(Str::random(8));
            $order->status = Order::STATUS_PENDING;
            $order->type = Order::TYPE_SUBSCRIPTION_PRODUCT;
            $order->payment_method = $request->input('payment_method');
            $order->billing_name = $request->input('name');
            $order->billing_email = $request->input('email');
            $order->phone_number = $request->input('phone_number');
            $order->billing_address = $request->input('billing_address');
            $order->billing_city = $request->input('billing_city');
            $order->billing_state = $request->input('billing_state');
            $order->billing_zip = $request->input('billing_zip') ?? '00000';
            $order->billing_country = $request->input('billing_country');
            $order->notes = $request->input('notes');
            $order->referral_id = $request->input('referral_id');
            $order->total_amount = $totalAmount;
            $order->currency = $firstProduct->currency;
            $order->save();

            foreach ($orderItems as $item) {
                $orderItem = new OrderItem();
                $orderItem->order_id = $order->id;
                $orderItem->product_id = $item['product']->id;
                $orderItem->quantity = $item['quantity'];
                $orderItem->price = $item['price'];
                $orderItem->total = $item['total'];
                $orderItem->is_subscription = true;
                $orderItem->save();
            }

            $gatewaySettings = PaymentGatewaySetting::find($request->input('payment_method'));
            if (!$gatewaySettings) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Payment method not available',
                ], 400);
            }

            $paymentService = $this->makePaymentService();
            $paymentResult = $paymentService->createPayment(
                $order->id,
                $gatewaySettings->code,
                [],
                $environment->name
            );

            $responseData = [
                'user' => $user,
                'transaction' => $paymentResult['transaction'] ?? null,
                'order' => $order,
            ];
            $responseData = array_merge($responseData, $this->buildPaymentResponseData($paymentResult));

            DB::commit();

            // Dispatch OrderCreated notification
            try {
                $order->load(['user']);
                if ($order->user) {
                    $order->user->notify(new \App\Notifications\OrderCreated($order, app(\App\Services\TelegramService::class)));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send OrderCreated notification: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment processing initiated',
                'data' => $responseData,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Subscription product subscribe error: ' . $e->getMessage(), [
                'environment_id' => $environmentId,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Checkout failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function renew(Request $request, int $subscriptionId): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $subscription = ProductSubscription::with(['product', 'environment'])->find($subscriptionId);

        if (!$subscription) {
            return response()->json(['message' => 'Subscription not found'], 404);
        }

        if ((int) $subscription->user_id !== (int) $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|exists:payment_gateway_settings,id',
            'payment_data' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $product = $subscription->product;
        $environment = $subscription->environment;

        if (!$product || !$environment) {
            return response()->json(['message' => 'Invalid subscription record'], 400);
        }

        if (!$product->is_subscription) {
            return response()->json(['message' => 'Product is not a subscription product'], 400);
        }

        try {
            DB::beginTransaction();

            event(new \App\Events\UserCreatedDuringCheckout($user, $environment, false));

            $price = $product->discount_price ?? $product->price;
            $totalAmount = $price;

            $order = new Order();
            $order->user_id = $user->id;
            $order->environment_id = $environment->id;
            $order->order_number = 'ORD-' . strtoupper(Str::random(8));
            $order->status = Order::STATUS_PENDING;
            $order->type = Order::TYPE_SUBSCRIPTION_PRODUCT;
            $order->payment_method = $request->input('payment_method');
            $order->billing_name = $user->name;
            $order->billing_email = $user->email;
            $order->total_amount = $totalAmount;
            $order->currency = $product->currency;
            $order->save();

            $orderItem = new OrderItem();
            $orderItem->order_id = $order->id;
            $orderItem->product_id = $product->id;
            $orderItem->quantity = 1;
            $orderItem->price = $price;
            $orderItem->total = $totalAmount;
            $orderItem->is_subscription = true;
            $orderItem->subscription_id = $subscription->id;
            $orderItem->subscription_status = $subscription->status;
            $orderItem->save();

            $gatewaySettings = PaymentGatewaySetting::find($request->input('payment_method'));
            if (!$gatewaySettings) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Payment method not available',
                ], 400);
            }

            $paymentService = $this->makePaymentService();
            $paymentResult = $paymentService->createPayment(
                $order->id,
                $gatewaySettings->code,
                $request->input('payment_data', []),
                $environment->name
            );

            $responseData = [
                'user' => $user,
                'transaction' => $paymentResult['transaction'] ?? null,
                'order' => $order,
                'subscription' => $subscription,
            ];
            $responseData = array_merge($responseData, $this->buildPaymentResponseData($paymentResult));

            DB::commit();

            // Dispatch OrderCreated notification
            try {
                $order->load(['user']);
                if ($order->user) {
                    $order->user->notify(new \App\Notifications\OrderCreated($order, app(\App\Services\TelegramService::class)));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send OrderCreated notification: ' . $e->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Payment processing initiated',
                'data' => $responseData,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Subscription product renewal error: ' . $e->getMessage(), [
                'subscription_id' => $subscriptionId,
                'user_id' => $user->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Renewal failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function cancel(Request $request, int $subscriptionId): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $subscription = ProductSubscription::find($subscriptionId);
        if (!$subscription) return response()->json(['message' => 'Subscription not found'], 404);
        if ((int) $subscription->user_id !== (int) $user->id) return response()->json(['message' => 'Unauthorized'], 403);

        $validator = Validator::make($request->all(), ['cancel_at_period_end' => 'sometimes|boolean']);
        if ($validator->fails()) return response()->json(['errors' => $validator->errors()], 422);

        $cancelAtPeriodEnd = (bool) $request->input('cancel_at_period_end', true);
        if ($subscription->status === 'canceled') {
            return response()->json(['success' => false, 'message' => 'Subscription already canceled'], 400);
        }

        DB::transaction(function () use ($subscription, $cancelAtPeriodEnd) {
            if ($cancelAtPeriodEnd) {
                $subscription->status = 'cancel_pending';
                $subscription->canceled_at = $subscription->canceled_at ?? now();
            } else {
                $subscription->status = 'canceled';
                $subscription->canceled_at = now();
                $subscription->ends_at = now();
            }
            $subscription->save();
        });

        return response()->json([
            'success' => true,
            'message' => $cancelAtPeriodEnd ? 'Subscription will cancel at period end' : 'Subscription canceled',
            'data' => ['subscription' => $subscription->fresh()],
        ]);
    }

    public function pause(Request $request, int $subscriptionId): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $subscription = ProductSubscription::find($subscriptionId);
        if (!$subscription) return response()->json(['message' => 'Subscription not found'], 404);
        if ((int) $subscription->user_id !== (int) $user->id) return response()->json(['message' => 'Unauthorized'], 403);

        if ($subscription->status === 'canceled') return response()->json(['success' => false, 'message' => 'Cannot pause a canceled subscription'], 400);
        if ($subscription->paused_at) return response()->json(['success' => false, 'message' => 'Subscription already paused'], 400);

        DB::transaction(function () use ($subscription) {
            $subscription->status = 'paused';
            $subscription->paused_at = now();
            $subscription->save();
        });

        return response()->json(['success' => true, 'message' => 'Subscription paused', 'data' => ['subscription' => $subscription->fresh()]]);
    }

    public function resume(Request $request, int $subscriptionId): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthenticated'], 401);

        $subscription = ProductSubscription::find($subscriptionId);
        if (!$subscription) return response()->json(['message' => 'Subscription not found'], 404);
        if ((int) $subscription->user_id !== (int) $user->id) return response()->json(['message' => 'Unauthorized'], 403);

        if ($subscription->status === 'canceled') return response()->json(['success' => false, 'message' => 'Cannot resume a canceled subscription'], 400);
        if (!$subscription->paused_at) return response()->json(['success' => false, 'message' => 'Subscription is not paused'], 400);

        $pausedAt = $subscription->paused_at;

        DB::transaction(function () use ($subscription, $pausedAt) {
            $pausedSeconds = now()->diffInSeconds($pausedAt);

            if ($subscription->ends_at) {
                $subscription->ends_at = $subscription->ends_at->copy()->addSeconds($pausedSeconds);
            }

            $subscription->paused_at = null;
            $subscription->status = $subscription->canceled_at ? 'cancel_pending' : 'active';
            $subscription->save();
        });

        return response()->json(['success' => true, 'message' => 'Subscription resumed', 'data' => ['subscription' => $subscription->fresh()]]);
    }

    public function continuePayment(Request $request, int $orderId): JsonResponse
    {
        $user = $request->user();

        $order = Order::find($orderId);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        if ($user->id !== $order->user_id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this order'
            ], 403);
        }

        if ($order->type !== Order::TYPE_SUBSCRIPTION_PRODUCT) {
            return response()->json([
                'success' => false,
                'message' => 'This order is not a subscription product order'
            ], 400);
        }

        if ($order->status !== Order::STATUS_PENDING) {
            return response()->json([
                'success' => false,
                'message' => 'Only pending orders can continue payment',
                'order_status' => $order->status
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'payment_method' => 'required|string',
            'payment_data' => 'sometimes|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $order->load(['items.product', 'user']);

        $paymentMethod = $request->input('payment_method');
        $paymentData = $request->input('payment_data', []);

        try {
            $paymentGatewaySetting = PaymentGatewaySetting::where('environment_id', $order->environment_id)
                ->where(function ($query) use ($paymentMethod) {
                    $query->where('code', $paymentMethod)
                        ->orWhere('id', $paymentMethod)
                        ->orWhere('gateway_name', $paymentMethod);
                })
                ->first();

            if (!$paymentGatewaySetting) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment method not available'
                ], 400);
            }

            $paymentService = $this->makePaymentService();

            $result = $paymentService->processPayment($order->id, [
                'payment_method' => $paymentGatewaySetting->code,
                'environment_id' => $order->environment_id,
                ...$paymentData
            ]);

            $order->payment_method = $paymentGatewaySetting->id;
            $order->save();

            $responseData = [];
            $responseData['user'] = $user;
            $responseData['transaction'] = $result['transaction'] ?? null;
            $responseData['order'] = $order;
            $responseData = array_merge($responseData, $this->buildPaymentResponseData($result));

            return response()->json([
                'success' => true,
                'message' => 'Payment processing initiated',
                'data' => $responseData
            ]);
        } catch (\Exception $e) {
            Log::error('Subscription product payment continuation error: ' . $e->getMessage(), [
                'order_id' => $order->id,
                'payment_method' => $paymentMethod,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Payment processing failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
