<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Environment;
use App\Services\InvoiceService;
use Carbon\Carbon;
use App\Notifications\InvoiceNotification;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Notification;

class GenerateMonthlyInvoices extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:generate-monthly-invoices';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate monthly invoices for all environments';

    /**
     * Execute the console command.
     */
    public function handle(InvoiceService $service, TelegramService $telegramService)
    {
        $month = Carbon::now()->subMonth()->startOfMonth();
        $environments = Environment::all();

        $successCount = 0;
        $failCount = 0;
        $failedEnvironments = [];

        foreach ($environments as $env) {
            try {
                $invoice = $service->generateMonthlyInvoiceForEnvironment($env->id, $month);
                if ($invoice) {
                    $service->createPaymentLink($invoice);
                    $service->sendInvoiceNotification($invoice);
                    $this->info("Invoice generated for environment {$env->id}");
                    $successCount++;
                } else {
                    $failCount++;
                    $failedEnvironments[] = $env->name ?? $env->id;
                }
            } catch (\Throwable $e) {
                $failCount++;
                $failedEnvironments[] = $env->name ?? $env->id;
                $this->error("Failed for environment {$env->id}: {$e->getMessage()}");
            }
        }


        // Send batch summary notification to Telegram
        $notification = new InvoiceNotification($successCount, $failCount, $failedEnvironments, $telegramService);
        $notification->toTelegram($notification);

        $this->info("Monthly invoices generated. Success: $successCount, Failed: $failCount");
    }
}
