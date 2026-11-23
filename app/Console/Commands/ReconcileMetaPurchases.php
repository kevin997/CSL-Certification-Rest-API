<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Models\Transaction;
use App\Services\MetaConversionsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReconcileMetaPurchases extends Command
{
    protected $signature = 'meta:reconcile-purchases {--limit=50 : Maximum orders per run}';

    protected $description = 'Retry pending Meta purchases and recover confirmed payments missed since rollout';

    public function handle(MetaConversionsService $meta): int
    {
        $pixels = $meta->pixelIds();
        if ($pixels === [] || ! config('services.meta_conversions.access_token')) {
            $this->warn('Meta Purchase reporting is not configured.');

            return self::FAILURE;
        }
        $cutover = DB::table('meta_purchase_recovery_state')->where('id', 1)->value('started_at');
        $since = max((string) $cutover, now()->subHours(47)->toDateTimeString());
        $orders = Order::withoutGlobalScopes()->where('total_amount', '>', 0)
            ->whereHas('transactions', fn ($query) => $query->withoutGlobalScopes()
                ->where('status', Transaction::STATUS_COMPLETED)->where('total_amount', '>', 0)
                ->where('paid_at', '>=', now()->subHours(47)))
            ->where(function ($query) use ($since) {
                $query->whereHas('transactions', fn ($paid) => $paid->withoutGlobalScopes()
                    ->where('status', Transaction::STATUS_COMPLETED)->where('total_amount', '>', 0)
                    ->where('paid_at', '>=', $since))
                    ->orWhereExists(fn ($pending) => $pending->selectRaw('1')->from('meta_purchase_deliveries')
                        ->whereColumn('order_id', 'orders.id')->whereNull('delivered_at'));
            })
            ->where(function ($query) use ($pixels) {
                foreach ($pixels as $pixel) {
                    $query->orWhereNotExists(fn ($delivered) => $delivered->selectRaw('1')->from('meta_purchase_deliveries')
                        ->whereColumn('order_id', 'orders.id')->where('pixel_id', $pixel)
                        ->where(fn ($settled) => $settled->whereNotNull('delivered_at')
                            ->orWhere('available_at', '>', now())->orWhere('attempts', '>=', 20)));
                }
            })
            ->orderBy('id')->limit(max(1, min(200, (int) $this->option('limit'))))->get();

        $started = microtime(true);
        $processed = 0;
        foreach ($orders as $order) {
            if (microtime(true) - $started > 45) {
                break;
            }
            try {
                $meta->purchase($order);
            } catch (\Throwable) {
                // The delivery ledger records a safe status and schedules retry.
            }
            $processed++;
        }
        $this->info("Reconciled {$processed} confirmed paid orders.");

        return self::SUCCESS;
    }
}
