<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PlatformPaymentService;
use Illuminate\Http\Request;

class PlatformPaymentController extends Controller
{
    public function __construct(private PlatformPaymentService $platformPaymentService)
    {
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'gateway' => 'nullable|string',
            'environment_id' => 'required|integer|exists:environments,id',
            'amount' => 'required|numeric|min:0.01',
            'total_amount' => 'nullable|numeric|min:0.01',
            'currency' => 'nullable|string|max:10',
            'description' => 'nullable|string|max:255',
            'source_type' => 'required|string|max:100',
            'source_id' => 'required|string|max:255',
            'customer_id' => 'nullable',
            'customer_email' => 'nullable|email',
            'customer_name' => 'nullable|string|max:255',
            'success_url' => 'nullable|url',
            'cancel_url' => 'nullable|url',
            'metadata' => 'nullable|array',
        ]);

        $data['created_by'] = $request->user()?->id;

        $paymentResult = $this->platformPaymentService->initiate($data);

        if (!($paymentResult['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'message' => $paymentResult['message'] ?? 'Failed to initiate platform payment',
            ], 422);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'transaction_id' => $paymentResult['transaction']->transaction_id,
                'gateway_transaction_id' => $paymentResult['transaction']->gateway_transaction_id,
            ],
            'payment_data' => $paymentResult['payment_data'],
        ]);
    }
}
