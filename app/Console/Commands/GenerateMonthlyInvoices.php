<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Environment;
use App\Services\InvoiceService;
use Carbon\Carbon;

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
    public function handle(InvoiceService $service)
    {
        $month = Carbon::now()->subMonth()->startOfMonth();
        $environments = Environment::all();

        foreach ($environments as $env) {
            $invoice = $service->generateMonthlyInvoiceForEnvironment($env->id, $month);
            if ($invoice) {
                $service->createPaymentLink($invoice);
                $service->sendInvoiceNotification($invoice);
                $this->info("Invoice generated for environment {$env->id}");
            }
        }
        $this->info('Monthly invoices generated.');
    }
}
