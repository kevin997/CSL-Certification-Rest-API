<?php

// app/Http/Controllers/Api/InvoiceController.php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Barryvdh\DomPDF\PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class InvoiceController extends Controller
{
    protected $service;

    public function __construct(InvoiceService $service)
    {
        $this->service = $service;
    }

    public function index(Request $request)
    {
        $environmentId = $request->user()->environment_id; // Or get from route
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

    public function downloadPDF($id)
    {
        $invoice = $this->service->getInvoice($id);
        $environmentId = $invoice->environment_id;
        $environment = \App\Models\Environment::findOrFail($environmentId);
        $branding = \App\Models\Branding::where('environment_id', $environmentId)->first();
        // Use a PDF library like barryvdh/laravel-dompdf
        $pdf = app('dompdf.wrapper');
        $pdf->loadView('invoices.pdf', ['invoice' => $invoice, 'environment' => $environment]);
        return $pdf->download("invoice-{$invoice->invoice_number}.pdf");
    }
}