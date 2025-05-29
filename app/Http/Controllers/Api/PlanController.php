<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class PlanController extends Controller
{
    /**
     * Display a listing of the plans.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $query = Plan::query();

        // Filter by active status (default to active plans)
        $isActive = $request->query('is_active', true);
        $query->where('is_active', $isActive);

        // Filter by plan type if provided
        if ($request->has('type')) {
            $query->where('type', $request->query('type'));
        }

        // Sort by sort_order or created_at
        $query->orderBy('sort_order', 'asc');

        $plans = $query->get();

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }

    /**
     * Display the specified plan.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        $plan = Plan::find($id);

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $plan,
        ]);
    }

    /**
     * Get plans by type.
     *
     * @param Request $request
     * @param string $type
     * @return JsonResponse
     */
    public function getByType(Request $request, string $type): JsonResponse
    {
        $query = Plan::where('type', $type);

        // Filter by active status (default to active plans)
        $isActive = $request->query('is_active', true);
        $query->where('is_active', $isActive);

        // Sort by sort_order or created_at
        $query->orderBy('sort_order', 'asc');

        $plans = $query->get();

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }

    /**
     * Compare plans.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function compare(Request $request): JsonResponse
    {
        $planIds = $request->input('plan_ids', []);
        
        if (empty($planIds)) {
            // If no plan IDs provided, return all active plans for comparison
            $plans = Plan::where('is_active', true)
                ->orderBy('sort_order', 'asc')
                ->get();
        } else {
            // Get the specified plans
            $plans = Plan::whereIn('id', $planIds)
                ->orderBy('sort_order', 'asc')
                ->get();
        }

        return response()->json([
            'success' => true,
            'data' => $plans,
        ]);
    }
}
