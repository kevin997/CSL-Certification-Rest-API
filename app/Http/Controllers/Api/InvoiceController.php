<?php

// app/Http/Controllers/Api/InvoiceController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\InvoiceService;
use App\Services\PlatformPaymentService;
use Illuminate\Http\Request;
use function Spatie\LaravelPdf\Support\pdf;

class InvoiceController extends Controller
{
    protected $service;
    protected $platformPaymentService;

    public function __construct(InvoiceService $service, PlatformPaymentService $platformPaymentService)
    {
        $this->service = $service;
        $this->platformPaymentService = $platformPaymentService;
    }

    public function index(Request $request)
    {
        $environmentId = session("current_environment_id");
        $filters = $request->only(['status', 'from', 'to']);
        $invoices = $this->service->getInvoices($environmentId, $filters);

        return response()->json(['success' => true, 'data' => $invoices]);
    }

    public function show($id)
    {
        $invoice = $this->service->getInvoice($id);
        return response()->json(['success' => true, 'data' => $invoice]);
    }

    public function generateMonthlyInvoices(Request $request)
    {
        $month = $request->input('month', now()->format('Y-m-01'));
        $environments = \App\Models\Environment::all();

        $created = [];
        foreach ($environments as $env) {
            $invoice = $this->service->generateMonthlyInvoiceForEnvironment($env->id, $month);
            if ($invoice) {
                $created[] = $invoice;
            }
        }
        return response()->json(['success' => true, 'created' => $created]);
    }

    public function markAsPaid($id)
    {
        $invoice = Invoice::findOrFail($id);
        $invoice->status = 'paid';
        $invoice->paid_at = now();
        $invoice->save();

        return response()->json(['success' => true, 'data' => $invoice]);
    }

    public function initiatePayment(Request $request, $id)
    {
        $request->validate([
            'payment_method' => 'nullable|string',
            'success_url' => 'nullable|url',
            'cancel_url' => 'nullable|url',
        ]);

        $invoice = Invoice::with('environment.owner')->findOrFail($id);

        if ($invoice->status === 'paid') {
            return response()->json([
                'success' => true,
                'requires_payment' => false,
                'data' => $invoice,
                'message' => 'Invoice is already paid',
            ]);
        }

        $owner = optional($invoice->environment)->owner;
        $paymentResult = $this->platformPaymentService->initiate([
            'gateway' => $request->input('payment_method', 'taramoney'),
            'environment_id' => $invoice->environment_id,
            'invoice_id' => $invoice->id,
            'customer_id' => $owner?->id,
            'customer_email' => $owner?->email,
            'customer_name' => $owner?->name,
            'amount' => $invoice->total_fee_amount,
            'total_amount' => $invoice->total_fee_amount,
            'currency' => $invoice->currency ?? 'USD',
            'description' => "Invoice {$invoice->invoice_number} payment",
            'source_type' => 'invoice',
            'source_id' => (string) $invoice->id,
            'created_by' => $owner?->id,
            'metadata' => [
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->invoice_number,
            ],
            'success_url' => $request->input('success_url'),
            'cancel_url' => $request->input('cancel_url'),
        ]);

        if (!($paymentResult['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $paymentResult['message'] ?? 'Failed to initiate invoice payment',
            ], 422);
        }

        $paymentLink = $paymentResult['payment_data']['redirect_url']
            ?? $paymentResult['payment_data']['general_link']
            ?? $paymentResult['payment_data']['checkout_url']
            ?? null;

        $invoice->update([
            'status' => 'sent',
            'payment_gateway' => $request->input('payment_method', 'taramoney'),
            'payment_link' => $paymentLink,
            'metadata' => array_merge($invoice->metadata ?? [], [
                'platform_payment_transaction_id' => $paymentResult['transaction']->transaction_id,
            ]),
        ]);

        return response()->json([
            'success' => true,
            'requires_payment' => true,
            'data' => $invoice->fresh(),
            'payment_data' => $paymentResult['payment_data'],
            'message' => 'Invoice payment initiated',
        ]);
    }

    public function downloadPDF($id)
    {
        $invoice = $this->service->getInvoice($id);

        // Always generate on-the-fly to avoid storing PDFs on disk
        $environment = \App\Models\Environment::with('owner')->findOrFail($invoice->environment_id);
        $branding = \App\Models\Branding::where('environment_id', $invoice->environment_id)
            ->where('is_active', true)
            ->first();
        $owner = $environment->owner;

        $transactions = collect();
        if ($invoice->metadata && isset($invoice->metadata['transaction_ids'])) {
            $transactions = \App\Models\Transaction::whereIn('id', $invoice->metadata['transaction_ids'])->get();
        }

        $primaryColor = $branding->primary_color ?? '#4F46E5';

        return pdf()
            ->view('invoices.spatie-pdf', [
                'invoice' => $invoice,
                'environment' => $environment,
                'branding' => $branding,
                'owner' => $owner,
                'transactions' => $transactions,
                'primaryColor' => $primaryColor,
            ])
            ->format('A4')
            ->name("invoice-{$invoice->invoice_number}.pdf")
            ->download();
    }

    /**
     * Regenerate the PDF for an existing invoice.
     */
    public function regeneratePDF($id)
    {
        $invoice = $this->service->getInvoice($id);
        $path = $this->service->generatePdf($invoice);

        if ($path) {
            return response()->json([
                'success' => true,
                'message' => 'Invoice PDF regenerated successfully.',
                'pdf_path' => $path,
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to regenerate invoice PDF.',
        ], 500);
    }
}
