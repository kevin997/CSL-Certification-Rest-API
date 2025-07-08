<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Models\Order;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RegularizeCompletedOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:regularize-completed-orders 
                            {--limit=50 : Maximum number of orders to process per run}
                            {--sleep=2 : Sleep time in seconds between processing orders}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Regularize orders that have completed transactions but are still pending';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting order regularization process...');
        
        $limit = (int) $this->option('limit');
        $sleepTime = (int) $this->option('sleep');
        
        Log::info('RegularizeCompletedOrders command started', [
            'limit' => $limit,
            'sleep_time' => $sleepTime
        ]);
        
        try {
            // Find orders that are still pending but have completed transactions
            $ordersToRegularize = $this->getOrdersWithCompletedTransactions($limit);
            
            if ($ordersToRegularize->isEmpty()) {
                $this->info('No orders found that need regularization.');
                return;
            }
            
            $this->info("Found {$ordersToRegularize->count()} orders that need regularization.");
            
            $processedCount = 0;
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($ordersToRegularize as $order) {
                try {
                    $this->info("Processing order: {$order->order_number} (ID: {$order->id})");
                    
                    // Get the completed transaction for this order
                    $completedTransaction = Transaction::where('order_id', $order->id)
                        ->where('status', Transaction::STATUS_COMPLETED)
                        ->first();
                    
                    if ($completedTransaction) {
                        // Trigger the OrderCompleted event
                        event(new \App\Events\OrderCompleted($order));
                        
                        // Update order status to completed
                        $order->status = Order::STATUS_COMPLETED;
                        $order->save();
                        
                        $this->info("✓ Order {$order->order_number} regularized successfully");
                        $successCount++;
                        
                        Log::info('Order regularized successfully', [
                            'order_id' => $order->id,
                            'order_number' => $order->order_number,
                            'transaction_id' => $completedTransaction->id,
                            'transaction_reference' => $completedTransaction->transaction_id
                        ]);
                    } else {
                        $this->warn("⚠ No completed transaction found for order {$order->order_number}");
                        $errorCount++;
                    }
                    
                    $processedCount++;
                    
                    // Sleep to avoid overwhelming the system
                    if ($sleepTime > 0) {
                        sleep($sleepTime);
                    }
                    
                } catch (\Exception $e) {
                    $this->error("✗ Error processing order {$order->order_number}: {$e->getMessage()}");
                    $errorCount++;
                    
                    Log::error('Error regularizing order', [
                        'order_id' => $order->id,
                        'order_number' => $order->order_number,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                }
            }
            
            // Display results summary
            $this->info("\nOrder regularization completed:");
            $this->info("- Total processed: {$processedCount}");
            $this->info("- Successfully regularized: {$successCount}");
            $this->info("- Errors: {$errorCount}");
            
            Log::info('RegularizeCompletedOrders command completed', [
                'total_processed' => $processedCount,
                'successful' => $successCount,
                'errors' => $errorCount
            ]);
            
        } catch (\Exception $e) {
            $this->error('Error in order regularization process: ' . $e->getMessage());
            Log::error('RegularizeCompletedOrders command error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Get orders that have completed transactions but are still pending
     *
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getOrdersWithCompletedTransactions($limit)
    {
        return Order::where('status', Order::STATUS_PENDING)
            ->whereHas('transactions', function ($query) {
                $query->where('status', Transaction::STATUS_COMPLETED);
            })
            ->with(['transactions' => function ($query) {
                $query->where('status', Transaction::STATUS_COMPLETED);
            }])
            ->orderBy('created_at', 'asc')
            ->limit($limit)
            ->get();
    }
}
