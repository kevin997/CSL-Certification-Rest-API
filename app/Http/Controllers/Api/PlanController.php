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
    
    /**
     * Get plans specifically for the onboarding process.
     * Returns the Standalone, Supported, and Demo plans with formatted data for the onboarding UI.
     *
     * @return JsonResponse
     */
    public function getOnboardingPlans(): JsonResponse
    {
        // Get the Standalone, Supported, and Demo plans that are active
        $plans = Plan::whereIn('type', ['standalone', 'supported', 'demo', 'personalized'])
            ->where('is_active', true)
            ->orderBy('sort_order', 'asc')
            ->get();
            
        // Format the plans for the onboarding UI with additional metadata
        $formattedPlans = $plans->map(function ($plan) {
            $isDemo = $plan->type === 'demo';
            $isStandalone = $plan->type === 'standalone';
            $isSupported = $plan->type === 'supported';
            
            return [
                'id' => $plan->id,
                'name' => $plan->name,
                'type' => $plan->type,
                'description' => $plan->description,
                'features' => $plan->features,
                'limits' => $plan->limits,
                'pricing' => $plan->pricing,
                'is_free' => $isStandalone || $isDemo,
                'setup_fee' => $plan->setup_fee ?? 0,
                'monthly_fee' => $plan->price_monthly ?? 0,
                'recommended' => $isSupported,
                'is_demo' => $isDemo,
                'support_level' => $isSupported ? 'Full Support' : ($isDemo ? 'Demo Support' : 'Self-Service'),
                'expires_after_days' => $isDemo ? ($plan->features['expires_after_days'] ?? 14) : null,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedPlans,
        ]);
    }
    /**
     * Store a newly created plan.
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        $validatedData = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'type' => 'required|string|in:standalone,supported,demo,personalized',
            'price_monthly' => 'nullable|numeric|min:0',
            'price_annual' => 'nullable|numeric|min:0',
            'setup_fee' => 'nullable|numeric|min:0',
            'features' => 'nullable', // Can be JSON string or array
            'limits' => 'nullable',   // Can be JSON string or array
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);

        // Handle JSON fields if they are passed as strings
        if (isset($validatedData['features']) && is_string($validatedData['features'])) {
            $validatedData['features'] = json_decode($validatedData['features'], true);
        }

        if (isset($validatedData['limits']) && is_string($validatedData['limits'])) {
            $validatedData['limits'] = json_decode($validatedData['limits'], true);
        }

        $plan = Plan::create($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Plan created successfully',
            'data' => $plan,
        ], 201);
    }

    /**
     * Update the specified plan.
     *
     * @param Request $request
     * @param string $id
     * @return JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $plan = Plan::find($id);

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found',
            ], 404);
        }

        $validatedData = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'type' => 'sometimes|string|in:standalone,supported,demo,personalized',
            'price_monthly' => 'nullable|numeric|min:0',
            'price_annual' => 'nullable|numeric|min:0',
            'setup_fee' => 'nullable|numeric|min:0',
            'features' => 'nullable', // Can be JSON string or array
            'limits' => 'nullable',   // Can be JSON string or array
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'sometimes|integer',
        ]);

        // Handle JSON fields if they are passed as strings
        if (isset($validatedData['features']) && is_string($validatedData['features'])) {
            $validatedData['features'] = json_decode($validatedData['features'], true);
        }

        if (isset($validatedData['limits']) && is_string($validatedData['limits'])) {
            $validatedData['limits'] = json_decode($validatedData['limits'], true);
        }

        $plan->update($validatedData);

        return response()->json([
            'success' => true,
            'message' => 'Plan updated successfully',
            'data' => $plan,
        ]);
    }

    /**
     * Remove the specified plan from storage.
     *
     * @param string $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        $plan = Plan::find($id);

        if (!$plan) {
            return response()->json([
                'success' => false,
                'message' => 'Plan not found',
            ], 404);
        }

        try {
            $plan->delete();

            return response()->json([
                'success' => true,
                'message' => 'Plan deleted successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete plan: ' . $e->getMessage(),
            ], 500);
        }
    }
}
