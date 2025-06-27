<?php

namespace App\Listeners;

use App\Events\OrderCompleted;
use App\Models\Product;
use App\Models\Enrollment;
use App\Models\Order;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessOrderItems implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(OrderCompleted $event): void
    {
        $order = $event->order;

        //set order status as completed
        $order->status = Order::STATUS_COMPLETED;
        $order->save();
        
        // Get the order items
        $orderItems = DB::table('order_items')->where('order_id', $order->id)->get();
        
        foreach ($orderItems as $item) {
            $product = Product::find($item->product_id);
            
            if (!$product) {
                Log::warning("Product not found for order item {$item->id}");
                continue;
            }
            
            // Handle course enrollments if the product contains courses
            // Ensure we're passing a single Product model, not a collection
            if ($product instanceof \App\Models\Product) {
                $this->processProductCourses($product, $order);
            } else {
                Log::error("Expected single Product model for order item {$item->id}, got " . get_class($product));
            }
            
            // Handle subscriptions if the product is a subscription
            if ($product->is_subscription) {
                //$this->processSubscription($product, $order);
            }
        }
    }

    /**
     * Process courses associated with a product.
     *
     * @param \App\Models\Product $product
     * @param \App\Models\Order $order
     * @return void
     */
    private function processProductCourses($product, $order): void
    {
        $productCourses = DB::table('product_courses')
            ->where('product_id', $product->id)
            ->get();
            
        foreach ($productCourses as $productCourse) {
            // Create enrollment if it doesn't exist
            $enrollment = DB::table('enrollments')
                ->where('user_id', $order->user_id)
                ->where('course_id', $productCourse->course_id)
                ->where('environment_id', $order->environment_id)
                ->first();
                
            if (!$enrollment) {
                Enrollment::create([
                    'user_id' => $order->user_id,
                    'course_id' => $productCourse->course_id,
                    'environment_id' => $order->environment_id,
                    'status' => Enrollment::STATUS_ENROLLED,
                    'progress_percentage' => 0,
                    'last_activity_at' => now(),
                ]);
                
                Log::info("Created enrollment for user {$order->user_id} in course {$productCourse->course_id}");
            }
        }
    }

    /**
     * Process subscription for a product.
     *
     * @param \App\Models\Product $product
     * @param \App\Models\Order $order
     * @return void
     */
    private function processSubscription($product, $order): void
    {
        // Create or update subscription
        $subscription = DB::table('subscriptions')
            ->where('user_id', $order->user_id)
            ->where('product_id', $product->id)
            ->where('environment_id', $order->environment_id)
            ->first();
            
        if (!$subscription) {
            // Create new subscription
            DB::table('subscriptions')->insert([
                'user_id' => $order->user_id,
                'product_id' => $product->id,
                'environment_id' => $order->environment_id,
                'status' => 'active',
                'start_date' => now(),
                'end_date' => now()->addDays($product->subscription_duration),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            
            Log::info("Created subscription for user {$order->user_id} with product {$product->id}");
        } else {
            // Update existing subscription
            DB::table('subscriptions')
                ->where('id', $subscription->id)
                ->update([
                    'status' => 'active',
                    'end_date' => now()->addDays($product->subscription_duration),
                    'updated_at' => now(),
                ]);
                
            Log::info("Updated subscription {$subscription->id} for user {$order->user_id}");
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(OrderCompleted $event, \Throwable $exception): void
    {
        Log::error("Failed to process order items for order {$event->order->id}: {$exception->getMessage()}");
    }
}
