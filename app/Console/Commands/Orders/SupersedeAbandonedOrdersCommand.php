<?php

namespace App\Console\Commands\Orders;

use App\Models\Order;
use App\Services\Payments\AbandonedOrderSupersession;
use Illuminate\Console\Command;

/**
 * Backfills the supersession rule for orders completed before it was deployed.
 *
 * Sales-form retries create a new submission and order, so an abandoned first
 * attempt can still be pending long after the retry was paid. Cancelling it
 * (and failing its pending transaction) keeps a late provider settlement from
 * charging the learner twice and double-reporting the Meta Purchase.
 */
class SupersedeAbandonedOrdersCommand extends Command
{
    protected $signature = 'orders:supersede-abandoned
        {--order= : Completed order id or order number to process}
        {--days=7 : Scan completed sales-form orders updated in the last N days}';

    protected $description = 'Cancel abandoned earlier payment attempts whose retry for the same cart was paid';

    public function handle(AbandonedOrderSupersession $supersession): int
    {
        $orderOption = $this->option('order');

        if ($orderOption !== null && $orderOption !== '') {
            $completed = Order::withoutGlobalScopes()
                ->where(function ($query) use ($orderOption): void {
                    $query->where('id', $orderOption)
                        ->orWhere('order_number', $orderOption);
                })
                ->first();

            if (! $completed || $completed->status !== Order::STATUS_COMPLETED) {
                $this->error('No completed order matches "'.$orderOption.'".');

                return self::FAILURE;
            }

            $completedOrders = collect([$completed]);
        } else {
            $days = max(1, (int) $this->option('days'));

            $completedOrders = Order::withoutGlobalScopes()
                ->where('type', Order::TYPE_SALES_FORM)
                ->where('status', Order::STATUS_COMPLETED)
                ->where('updated_at', '>=', now()->subDays($days))
                ->orderBy('id')
                ->get();
        }

        $cancelled = 0;

        foreach ($completedOrders as $completed) {
            $orderNumbers = $supersession->supersedeFor($completed);

            if ($orderNumbers === []) {
                continue;
            }

            $cancelled += count($orderNumbers);

            $this->line(sprintf(
                '%s superseded: %s',
                $completed->order_number,
                implode(', ', $orderNumbers),
            ));
        }

        $this->info(sprintf(
            'Completed orders reviewed: %d; abandoned attempts cancelled: %d.',
            $completedOrders->count(),
            $cancelled,
        ));

        return self::SUCCESS;
    }
}
