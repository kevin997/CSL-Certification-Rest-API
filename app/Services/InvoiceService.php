<?php

// app/Services/InvoiceService.php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\Environment;
use App\Models\Branding;
use App\Models\InstructorCommission;
use App\Models\EnvironmentPaymentConfig;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Mail\InvoiceMail;
use Spatie\LaravelPdf\Facades\Pdf;

class InvoiceService
{
    /**
     * Generate monthly invoice for an environment using hybrid logic:
     * - Centralized gateway environments: invoice from InstructorCommission records
     * - Legacy environments: invoice from Transaction.fee_amount
     *
     * @param int $environmentId
     * @param Carbon|string $month
     * @return Invoice|null Returns null if nothing to invoice (not an error)
     */
    public function generateMonthlyInvoiceForEnvironment($environmentId, $month)
    {
        $start = Carbon::parse($month)->startOfMonth();
        $end = Carbon::parse($month)->endOfMonth();

        // Determine if this environment uses centralized gateways
        $paymentConfig = EnvironmentPaymentConfig::where('environment_id', $environmentId)->first();
        $usesCentralized = $paymentConfig && $paymentConfig->use_centralized_gateways;

        if ($usesCentralized) {
            return $this->generateFromCommissions($environmentId, $start, $end);
        }

        return $this->generateFromTransactions($environmentId, $start, $end);
    }

    /**
     * Generate invoice from uninvoiced InstructorCommission records.
     * Grabs ALL uninvoiced commissions (not just current month) to catch past-due items.
     */
    private function generateFromCommissions(int $environmentId, Carbon $start, Carbon $end): ?Invoice
    {
        // Get all uninvoiced commissions for this environment
        $commissions = InstructorCommission::where('environment_id', $environmentId)
            ->whereNull('invoice_id')
            ->where('created_at', '<=', $end) // Don't invoice future commissions
            ->get();

        if ($commissions->isEmpty()) {
            Log::info('No uninvoiced commissions for environment', [
                'environment_id' => $environmentId,
                'period_end' => $end->toDateString(),
            ]);
            return null;
        }

        $totalFee = $commissions->sum('platform_fee_amount');
        $commissionIds = $commissions->pluck('id')->toArray();
        $transactionIds = $commissions->pluck('transaction_id')->filter()->toArray();
        $currency = $commissions->first()->currency ?? 'XAF';

        $invoiceNumber = sprintf(
            'INV-%s-ENV%s-%04d',
            $start->format('Y-m'),
            $environmentId,
            Invoice::where('environment_id', $environmentId)->count() + 1
        );

        $invoice = Invoice::create([
            'environment_id' => $environmentId,
            'invoice_number' => $invoiceNumber,
            'month' => $start->toDateString(),
            'total_fee_amount' => $totalFee,
            'currency' => $currency,
            'status' => 'draft',
            'due_date' => $end->copy()->addDays(30),
            'transaction_count' => count($transactionIds),
            'metadata' => [
                'transaction_ids' => $transactionIds,
                'commission_ids' => $commissionIds,
                'source' => 'commissions',
            ],
        ]);

        // Link commissions to this invoice
        InstructorCommission::whereIn('id', $commissionIds)
            ->update(['invoice_id' => $invoice->id]);

        Log::info('Invoice generated from commissions', [
            'invoice_id' => $invoice->id,
            'environment_id' => $environmentId,
            'commission_count' => count($commissionIds),
            'total_fee' => $totalFee,
        ]);

        return $invoice;
    }

    /**
     * Generate invoice from Transaction.fee_amount (legacy path).
     * Uses month-bounded date range and excludes already-invoiced transactions.
     */
    private function generateFromTransactions(int $environmentId, Carbon $start, Carbon $end): ?Invoice
    {
        // Get existing invoiced transaction IDs to avoid double-invoicing
        $alreadyInvoicedIds = Invoice::where('environment_id', $environmentId)
            ->whereNotNull('metadata')
            ->get()
            ->flatMap(function ($inv) {
                $meta = $inv->metadata ?? [];
                return $meta['transaction_ids'] ?? [];
            })
            ->unique()
            ->toArray();

        $transactions = Transaction::where('environment_id', $environmentId)
            ->where('status', 'completed')
            ->where('fee_amount', '>', 0)
            ->where('created_at', '<=', $end) // Don't invoice future transactions
            ->whereNotIn('id', $alreadyInvoicedIds)
            ->get();

        if ($transactions->isEmpty()) {
            Log::info('No uninvoiced transactions for environment', [
                'environment_id' => $environmentId,
                'period' => $start->format('Y-m'),
            ]);
            return null;
        }

        $totalFee = $transactions->sum('fee_amount');
        $transactionIds = $transactions->pluck('id')->toArray();
        $currency = $transactions->first()->currency ?? 'USD';

        $invoiceNumber = sprintf(
            'INV-%s-ENV%s-%04d',
            $start->format('Y-m'),
            $environmentId,
            Invoice::where('environment_id', $environmentId)->count() + 1
        );

        $invoice = Invoice::create([
            'environment_id' => $environmentId,
            'invoice_number' => $invoiceNumber,
            'month' => $start->toDateString(),
            'total_fee_amount' => $totalFee,
            'currency' => $currency,
            'status' => 'draft',
            'due_date' => $end->copy()->addDays(30),
            'transaction_count' => count($transactionIds),
            'metadata' => [
                'transaction_ids' => $transactionIds,
                'source' => 'transactions',
            ],
        ]);

        Log::info('Invoice generated from transactions', [
            'invoice_id' => $invoice->id,
            'environment_id' => $environmentId,
            'transaction_count' => count($transactionIds),
            'total_fee' => $totalFee,
        ]);

        return $invoice;
    }

    /**
     * Generate a professional PDF for the given invoice using spatie/laravel-pdf.
     *
     * @param Invoice $invoice
     * @return string|null The storage path of the generated PDF
     */
    public function generatePdf(Invoice $invoice): ?string
    {
        $environment = Environment::with('owner')->find($invoice->environment_id);
        $branding = Branding::where('environment_id', $invoice->environment_id)
            ->where('is_active', true)
            ->first();
        $owner = $environment?->owner;

        // Get transactions for this invoice period
        $transactions = collect();
        if ($invoice->metadata && isset($invoice->metadata['transaction_ids'])) {
            $transactions = Transaction::whereIn('id', $invoice->metadata['transaction_ids'])->get();
        }

        $primaryColor = $branding->primary_color ?? '#4F46E5';

        // Build the storage path
        $directory = 'invoices/' . ($environment->id ?? 'unknown');
        $filename = 'invoice-' . $invoice->invoice_number . '.pdf';
        $storagePath = $directory . '/' . $filename;
        $fullPath = storage_path('app/' . $storagePath);

        // Ensure directory exists
        if (!is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0775, true);
        }

        Log::info('Generating PDF for invoice ' . $invoice->id);
        try {
            Pdf::view('invoices.spatie-pdf', [
                'invoice' => $invoice,
                'environment' => $environment,
                'branding' => $branding,
                'owner' => $owner,
                'transactions' => $transactions,
                'primaryColor' => $primaryColor,
            ])
            ->format('A4')
            ->save($fullPath);

            // Store the path on the invoice
            $invoice->update(['pdf_path' => $storagePath]);

            Log::info('Invoice PDF generated', [
                'invoice_id' => $invoice->id,
                'path' => $storagePath,
            ]);

            return $storagePath;
        } catch (\Throwable $e) {
            Log::error('Failed to generate invoice PDF', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    public function calculateFeesForPeriod($environmentId, $startDate, $endDate)
    {
        return Transaction::where('environment_id', $environmentId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('fee_amount');
    }

    public function createPaymentLink(Invoice $invoice, $gateway = 'stripe')
    {
        $settings = \App\Models\PaymentGatewaySetting::where('environment_id', 15)
            ->where('code', $gateway)
            ->firstOrFail();
        $gatewayInstance = \App\Services\PaymentGateways\PaymentGatewayFactory::create($gateway, $settings);
        if (method_exists($gatewayInstance, 'createInvoicePaymentLink')) {
            return $gatewayInstance->createInvoicePaymentLink($invoice);
        }
        throw new \BadMethodCallException("Payment gateway does not support invoice payment links.");
    }

    public function getInvoice($invoiceId)
    {
        return Invoice::with('environment')->findOrFail($invoiceId);
    }

    public function getInvoices($environmentId, $filters = [])
    {
        $query = Invoice::where('environment_id', $environmentId);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['from'])) {
            $query->where('month', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->where('month', '<=', $filters['to']);
        }

        return $query->orderBy('month', 'desc')->paginate(20);
    }

    /**
     * Send invoice notification to the environment owner (per-invoice, not batch summary)
     *
     * @param Invoice $invoice
     * @return void
     */
    public function sendInvoiceNotification(Invoice $invoice)
    {
        // Send invoice email to environment owner
        $environment = $invoice->environment;
        $owner = (is_object($environment) && isset($environment->owner) && is_object($environment->owner)) ? $environment->owner : null;
        $email = $owner && isset($owner->email) ? $owner->email : null;
        if ($email) {
            Mail::to($email)->send(new InvoiceMail($invoice));
        }
    }
}
