<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CampaignFunder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignFunderController extends Controller
{
    /**
     * Store a newly created campaign funder.
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'whatsapp_number' => 'required|string|max:32',
            'locale' => 'required|string|max:8',
            'tier_id' => 'nullable|string|max:100',
            'tier_name' => 'nullable|string|max:255',
            'amount_xaf' => 'required|integer|min:1',
            'currency' => 'nullable|string|size:3',
            'note' => 'nullable|string',
            'terms_accepted' => 'required|boolean|accepted',
            'source' => 'nullable|string|max:50',
            'payment_provider' => 'nullable|string|max:50',
            'payment_links' => 'nullable|array',
            'payment_links.whatsapp' => 'nullable|url',
            'payment_links.telegram' => 'nullable|url',
            'payment_links.dikalo' => 'nullable|url',
            'payment_links.sms' => 'nullable|string',
            'meta' => 'nullable|array',
            'tara_payment_id' => 'nullable|string|max:255',
            'tara_collection_id' => 'nullable|string|max:255',
        ]);

        $campaignFunder = CampaignFunder::create([
            'full_name' => $validatedData['full_name'],
            'email' => $validatedData['email'],
            'whatsapp_number' => $validatedData['whatsapp_number'],
            'locale' => $validatedData['locale'],
            'tier_id' => $validatedData['tier_id'] ?? null,
            'tier_name' => $validatedData['tier_name'] ?? null,
            'amount_xaf' => $validatedData['amount_xaf'],
            'currency' => $validatedData['currency'] ?? 'XAF',
            'note' => $validatedData['note'] ?? null,
            'terms_accepted_at' => now(),
            'source' => $validatedData['source'] ?? 'website',
            'payment_provider' => $validatedData['payment_provider'] ?? 'taramoney',
            'payment_status' => CampaignFunder::PAYMENT_STATUS_PENDING,
            'payment_links' => $validatedData['payment_links'] ?? null,
            'meta' => $validatedData['meta'] ?? null,
            'tara_payment_id' => $validatedData['tara_payment_id'] ?? null,
            'tara_collection_id' => $validatedData['tara_collection_id'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Campaign funder stored successfully',
            'data' => $campaignFunder,
        ], 201);
    }

    /**
     * Process Tara webhook updates for a campaign funder payment.
     */
    public function handleTaraWebhook(Request $request): JsonResponse
    {
        $expectedBusinessId = env('TARA_BUSINESS_ID');
        $expectedSecret = env('TARA_WEBHOOK_SECRET');
        $providedSecret = $request->query('token');

        if (!empty($expectedSecret) && $providedSecret !== $expectedSecret) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized webhook',
            ], 401);
        }

        $validatedData = $request->validate([
            'businessId' => 'required|string',
            'status' => 'required|string|in:SUCCESS,FAILURE',
            'paymentId' => 'required|string|max:255',
            'collectionId' => 'nullable|string|max:255',
            'creationDate' => 'required|string',
            'changeDate' => 'required|string',
            'amount' => 'nullable|string',
            'mobileOperator' => 'nullable|string|max:255',
            'customerName' => 'nullable|string|max:255',
            'transactionCode' => 'nullable|string|max:255',
            'customerId' => 'nullable|string|max:255',
            'phoneNumber' => 'nullable|string|max:32',
            'type' => 'nullable|string|in:DEPOSIT,TRANSFER',
        ]);

        if (!empty($expectedBusinessId) && $validatedData['businessId'] !== $expectedBusinessId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid business ID',
            ], 401);
        }

        $campaignFunderQuery = CampaignFunder::where('tara_payment_id', $validatedData['paymentId']);

        if (!empty($validatedData['collectionId'])) {
            $campaignFunderQuery->orWhere('tara_collection_id', $validatedData['collectionId']);
        }

        $campaignFunder = $campaignFunderQuery->latest('id')->first();

        if (!$campaignFunder) {
            return response()->json([
                'success' => false,
                'message' => 'Campaign funder not found for webhook',
            ], 404);
        }

        $isSuccess = $validatedData['status'] === 'SUCCESS';

        $campaignFunder->update([
            'payment_status' => $isSuccess ? CampaignFunder::PAYMENT_STATUS_SUCCESS : CampaignFunder::PAYMENT_STATUS_FAILURE,
            'tara_payment_id' => $validatedData['paymentId'],
            'tara_collection_id' => $validatedData['collectionId'] ?? $campaignFunder->tara_collection_id,
            'tara_transaction_code' => $validatedData['transactionCode'] ?? null,
            'tara_mobile_operator' => $validatedData['mobileOperator'] ?? null,
            'tara_phone_number' => $validatedData['phoneNumber'] ?? null,
            'tara_customer_name' => $validatedData['customerName'] ?? null,
            'paid_at' => $isSuccess ? now() : $campaignFunder->paid_at,
            'failed_at' => $isSuccess ? null : now(),
            'last_webhook_payload' => $validatedData,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Webhook processed successfully',
            'data' => $campaignFunder->fresh(),
        ]);
    }
}
