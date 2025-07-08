<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use App\Models\PaymentGatewaySetting;
use App\Services\PaymentGateways\MonetbillGateway;
use App\Services\PaymentService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CheckMonetBilPaymentsStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:check-monet-bil-payments-status 
                            {--environment= : Environment ID to check (optional)}
                            {--hours=24 : Check transactions from last N hours}
                            {--limit=50 : Maximum number of transactions to check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Monetbil payments status for pending transactions and update their status';

    /**
     * PaymentService instance
     *
     * @var PaymentService
     */
    protected $paymentService;

    /**
     * Create a new command instance.
     *
     * @param PaymentService $paymentService
     */
    public function __construct(PaymentService $paymentService)
    {
        parent::__construct();
        $this->paymentService = $paymentService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting Monetbil payment status check...');
        
        $hours = (int) $this->option('hours');
        $limit = (int) $this->option('limit');
        
        // Log the start of the process
        Log::info('CheckMonetBilPaymentsStatus command started', [
            'hours' => $hours,
            'limit' => $limit
        ]);
        
        try {
            // Get pending Monetbil transactions
            $transactions = $this->getPendingMonetbilTransactions($hours, $limit);
            
            if ($transactions->isEmpty()) {
                $this->info('No pending Monetbil transactions found.');
                return;
            }
            
            $this->info("Found {$transactions->count()} pending Monetbil transactions to check.");
            
            $successCount = 0;
            $failedCount = 0;
            $unchangedCount = 0;
            
            // Process each transaction
            foreach ($transactions as $transaction) {
                $result = $this->checkTransactionStatus($transaction);
                
                switch ($result) {
                    case 'success':
                        $successCount++;
                        break;
                    case 'failed':
                        $failedCount++;
                        break;
                    default:
                        $unchangedCount++;
                        break;
                }
                
                // Add a small delay to avoid overwhelming the API
                usleep(500000); // 0.5 seconds
            }
            
            // Display results
            $this->info("\nPayment status check completed:");
            $this->info("- Successful payments: {$successCount}");
            $this->info("- Failed payments: {$failedCount}");
            $this->info("- Unchanged: {$unchangedCount}");
            
            Log::info('CheckMonetBilPaymentsStatus command completed', [
                'total_checked' => $transactions->count(),
                'successful' => $successCount,
                'failed' => $failedCount,
                'unchanged' => $unchangedCount
            ]);
            
        } catch (\Exception $e) {
            $this->error('Error checking payment statuses: ' . $e->getMessage());
            Log::error('CheckMonetBilPaymentsStatus command error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }
    
    /**
     * Get pending Monetbil transactions
     *
     * @param int|null $environmentId
     * @param int $hours
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    protected function getPendingMonetbilTransactions($hours, $limit)
    {
        $query = Transaction::where('status', Transaction::STATUS_PENDING)
            ->whereHas('paymentGatewaySetting', function ($query) {
                $query->where('code', 'monetbill');
            })->orderBy('created_at', 'desc')->limit($limit);
        
        return $query->get();
    }
    
    /**
     * Check the status of a specific transaction
     *
     * @param Transaction $transaction
     * @return string 'success', 'failed', or 'unchanged'
     */
    protected function checkTransactionStatus(Transaction $transaction)
    {
        try {
            $this->info("Checking transaction: {$transaction->transaction_id}");
            
            // Get the payment gateway settings for this transaction
            $gatewaySettings = $transaction->paymentGatewaySetting;
            
            if (!$gatewaySettings) {
                $this->warn("No gateway settings found for transaction: {$transaction->transaction_id}");
                return 'unchanged';
            }
            
            // Initialize the Monetbil gateway
            $gateway = new MonetbillGateway();
            $gateway->initialize($gatewaySettings);
            
            // Check payment status using the gateway
            $statusResult = $gateway->verifyPayment($transaction->transaction_id);
            
            $this->line("Status check result: " . json_encode($statusResult));
            
            Log::info('Payment status check result', [
                'transaction_id' => $transaction->transaction_id,
                'result' => $statusResult
            ]);
            
            // Process the result based on status
            if (isset($statusResult['success']) && $statusResult['success']) {
                if (isset($statusResult['status']) && $statusResult['status'] === 'succeeded') {
                    // Process successful payment
                    $this->info("Transaction {$transaction->transaction_id} status: succeeded");
                    return $this->processSuccessfulPayment($transaction, $statusResult);
                } elseif (isset($statusResult['status']) && in_array($statusResult['status'], ['failed', 'cancelled'])) {
                    // Process failed payment
                    $this->info("Transaction {$transaction->transaction_id} status: {$statusResult['status']}");
                    return $this->processFailedPayment($transaction, $statusResult);
                } else {
                    $this->info("Transaction {$transaction->transaction_id} status: " . (isset($statusResult['status']) ? $statusResult['status'] : 'unknown'));
                }
            } else {
                $this->warn("Could not verify payment status: " . (isset($statusResult['message']) ? $statusResult['message'] : 'Unknown error'));
            }
            
            return 'unchanged';
            
        } catch (\Exception $e) {
            $this->error("Error checking transaction {$transaction->transaction_id}: {$e->getMessage()}");
            Log::error('Transaction status check error', [
                'transaction_id' => $transaction->transaction_id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 'unchanged';
        }
    }
    
    /**
     * Process a successful payment
     *
     * @param Transaction $transaction
     * @param array $statusResult
     * @return string
     */
    protected function processSuccessfulPayment(Transaction $transaction, array $statusResult)
    {
        try {
            // Create callback data similar to what would come from Monetbil
            $callbackData = [
                'status' => 'success',
                'payment_ref' => $transaction->transaction_id,
                'transaction_id' => $statusResult['gateway_transaction_id'] ?? $transaction->gateway_transaction_id,
                'amount' => $statusResult['amount'] ?? $transaction->total_amount,
                'currency' => $statusResult['currency'] ?? $transaction->currency
            ];
            
            // Process through PaymentService like the callback does
            $result = $this->paymentService->processSuccessCallback(
                'monetbill',
                $transaction->transaction_id,
                $transaction->environment_id,
                $callbackData
            );
            
            if ($result) {
                $this->info("✓ Transaction {$transaction->transaction_id} marked as successful");
                return 'success';
            } else {
                $this->warn("⚠ Failed to process successful payment for {$transaction->transaction_id}");
                return 'unchanged';
            }
            
        } catch (\Exception $e) {
            $this->error("Error processing successful payment {$transaction->transaction_id}: {$e->getMessage()}");
            Log::error('Error processing successful payment', [
                'transaction_id' => $transaction->transaction_id,
                'error' => $e->getMessage()
            ]);
            return 'unchanged';
        }
    }
    
    /**
     * Process a failed payment
     *
     * @param Transaction $transaction
     * @param array $statusResult
     * @return string
     */
    protected function processFailedPayment(Transaction $transaction, array $statusResult)
    {
        try {
            // Create callback data for failed payment
            $callbackData = [
                'status' => $statusResult['status'] === 'cancelled' ? 'cancelled' : 'failed',
                'payment_ref' => $transaction->transaction_id,
                'transaction_id' => $statusResult['gateway_transaction_id'] ?? $transaction->gateway_transaction_id,
                'amount' => $statusResult['amount'] ?? $transaction->total_amount,
                'currency' => $statusResult['currency'] ?? $transaction->currency
            ];
            
            // Process through PaymentService
            if ($statusResult['status'] === 'cancelled') {
                $result = $this->paymentService->processCancelledCallback(
                    'monetbill',
                    $transaction->transaction_id,
                    $transaction->environment_id,
                    $callbackData
                );
            } else {
                $result = $this->paymentService->processFailureCallback(
                    'monetbill',
                    $transaction->transaction_id,
                    $transaction->environment_id,
                    $callbackData
                );
            }
            
            if ($result) {
                $this->info("✗ Transaction {$transaction->transaction_id} marked as {$statusResult['status']}");
                return 'failed';
            } else {
                $this->warn("⚠ Failed to process failed payment for {$transaction->transaction_id}");
                return 'unchanged';
            }
            
        } catch (\Exception $e) {
            $this->error("Error processing failed payment {$transaction->transaction_id}: {$e->getMessage()}");
            Log::error('Error processing failed payment', [
                'transaction_id' => $transaction->transaction_id,
                'error' => $e->getMessage()
            ]);
            return 'unchanged';
        }
    }
}
