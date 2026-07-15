<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\EnvironmentAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EnvironmentAnalyticsController extends Controller
{
    public function __construct(private EnvironmentAnalyticsService $analytics)
    {
    }

    /**
     * Unified environment-scoped analytics across every domain.
     *
     * GET /api/analytics/environment/overview?start_date=&end_date=
     */
    public function overview(Request $request): JsonResponse
    {
        $environmentId = session('current_environment_id');
        if (! $environmentId) {
            return response()->json([
                'success' => false,
                'message' => 'No current environment in session.',
            ], 400);
        }

        $range = [];
        if ($request->filled('start_date')) {
            $range['start'] = $request->input('start_date');
        }
        if ($request->filled('end_date')) {
            $range['end'] = $request->input('end_date');
        }

        return response()->json([
            'success' => true,
            'data'    => $this->analytics->overview((int) $environmentId, $range),
        ]);
    }
}
