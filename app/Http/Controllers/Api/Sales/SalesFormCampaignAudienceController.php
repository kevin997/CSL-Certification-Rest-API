<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Marketing\PreviewSalesFormCampaignAudienceRequest;
use App\Models\SalesForm;
use App\Services\Marketing\SalesFormCampaignAudience;
use Illuminate\Http\JsonResponse;

class SalesFormCampaignAudienceController extends Controller
{
    public function preview(
        PreviewSalesFormCampaignAudienceRequest $request,
        SalesForm $form,
        SalesFormCampaignAudience $audience,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $audience->preview($form, $request->selection(), $request->validated('channels'))->toArray(),
        ]);
    }
}
