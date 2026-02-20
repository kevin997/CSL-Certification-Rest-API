<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class InvoiceController extends Controller
{
    protected $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    /**
     * Regenerate invoices for a specific environment and month.
     * Use with caution: Force deletes existing invoices.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function regenerate(Request $request)
    {
        $request->validate([
            'environment_id' => 'required|integer|exists:environments,id',
            'month' => 'nullable|date_format:Y-m',
        ]);

        $user = $request->user();
        if (!$user || !$user->isAdmin()) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $environmentId = $request->input('environment_id');
        $month = $request->input('month', Carbon::now()->format('Y-m'));
        $start = Carbon::parse($month)->startOfMonth();
        $end = Carbon::parse($month)->endOfMonth();

        // 1. Force delete existing invoices for this environment and month
        Invoice::where('environment_id', $environmentId)
            ->whereBetween('month', [$start->startOfDay(), $end->endOfDay()])
            ->withTrashed() // Include soft deleted ones
            ->forceDelete();

        // 2. Generate new invoice
        try {
            $invoice = $this->invoiceService->generateMonthlyInvoiceForEnvironment($environmentId, $month);

            if (!$invoice) {
                return response()->json([
                    'success' => true,
                    'message' => 'No items to invoice for this period.',
                    'invoice' => null
                ]);
            }

            // 3. Attempt payment link & notification (swallowing errors as per requirement)
            $paymentLinkError = null;
            try {
                $this->invoiceService->createPaymentLink($invoice);
            } catch (\Throwable $e) {
                $paymentLinkError = $e->getMessage();
                Log::warning("Admin regenerate: Could not create payment link for invoice {$invoice->id}: {$e->getMessage()}");
            }

            $notificationError = null;
            try {
                $this->invoiceService->sendInvoiceNotification($invoice);
            } catch (\Throwable $e) {
                $notificationError = $e->getMessage();
                Log::warning("Admin regenerate: Could not send notification for invoice {$invoice->id}: {$e->getMessage()}");
            }

            return response()->json([
                'success' => true,
                'message' => 'Invoice regenerated successfully.',
                'invoice' => $invoice,
                'warnings' => array_filter([
                    'payment_link' => $paymentLinkError,
                    'notification' => $notificationError,
                ]),
            ]);

        } catch (\Throwable $e) {
            Log::error("Invoice regeneration failed for env {$environmentId}: " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Invoice generation failed: ' . $e->getMessage(),
            ], 500);
        }
    }
}
