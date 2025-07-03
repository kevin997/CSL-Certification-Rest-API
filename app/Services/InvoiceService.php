<?php

// app/Services/InvoiceService.php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Transaction;
use App\Models\Environment;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function generateMonthlyInvoiceForEnvironment($environmentId, $month)
    {
        $start = Carbon::parse($month)->startOfMonth();
        $end = Carbon::parse($month)->endOfMonth();

        $transactions = Transaction::where('environment_id', $environmentId)
            ->where('status', 'completed')
            ->whereBetween('created_at', [$start, $end])
            ->get();

        if ($transactions->isEmpty()) {
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
            'metadata' => ['transaction_ids' => $transactionIds],
        ]);

        return $invoice;
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
        $settings = \App\Models\PaymentGatewaySetting::where('environment_id', $invoice->environment_id)
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

    public function sendInvoiceNotification(Invoice $invoice)
    {
        // Implement notification logic (email, in-app, etc.)
        // Example: Notification::send($invoice->environment->owner, new InvoiceCreated($invoice));
    }
}
