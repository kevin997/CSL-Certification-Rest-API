<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\EnvironmentPaymentConfig;
use App\Models\Environment;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class EnvironmentPaymentConfigController extends Controller
{
    /**
     * List all environment payment configs
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        // Ensure user is super admin
        if (!$request->user() || $request->user()->role->value !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $configs = EnvironmentPaymentConfig::with('environment')
            ->orderBy('environment_id')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $configs
        ]);
    }

    /**
     * Get config for specific environment
     *
     * @param Request $request
     * @param int $environmentId
     * @return JsonResponse
     */
    public function show(Request $request, int $environmentId): JsonResponse
    {
        // Ensure user is super admin
        if (!$request->user() || $request->user()->role->value !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $environment = Environment::find($environmentId);

        if (!$environment) {
            return response()->json([
                'success' => false,
                'message' => 'Environment not found'
            ], 404);
        }

        $config = EnvironmentPaymentConfig::where('environment_id', $environmentId)->first();

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Payment config not found for this environment'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $config,
            'environment' => $environment
        ]);
    }

    /**
     * Update environment payment config
     *
     * @param Request $request
     * @param int $environmentId
     * @return JsonResponse
     */
    public function update(Request $request, int $environmentId): JsonResponse
    {
        // Ensure user is super admin
        if (!$request->user() || $request->user()->role->value !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $environment = Environment::find($environmentId);

        if (!$environment) {
            return response()->json([
                'success' => false,
                'message' => 'Environment not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'use_centralized_gateways' => 'sometimes|boolean',
            'instructor_commission_rate' => 'sometimes|numeric|min:0|max:100',
            'minimum_withdrawal_amount' => 'sometimes|numeric|min:0',
            'withdrawal_processing_days' => 'sometimes|integer|min:1|max:365',
            'payment_terms' => 'sometimes|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $config = EnvironmentPaymentConfig::where('environment_id', $environmentId)->first();

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Payment config not found for this environment'
            ], 404);
        }

        // Update only the fields that are present in the request
        if ($request->has('use_centralized_gateways')) {
            $config->use_centralized_gateways = $request->use_centralized_gateways;
        }
        if ($request->has('instructor_commission_rate')) {
            $config->instructor_commission_rate = $request->instructor_commission_rate;
        }
        if ($request->has('minimum_withdrawal_amount')) {
            $config->minimum_withdrawal_amount = $request->minimum_withdrawal_amount;
        }
        if ($request->has('withdrawal_processing_days')) {
            $config->withdrawal_processing_days = $request->withdrawal_processing_days;
        }
        if ($request->has('payment_terms')) {
            $config->payment_terms = $request->payment_terms;
        }

        $config->save();

        return response()->json([
            'success' => true,
            'message' => 'Payment config updated successfully',
            'data' => $config
        ]);
    }

    /**
     * Toggle centralized gateways for an environment
     *
     * @param Request $request
     * @param int $environmentId
     * @return JsonResponse
     */
    public function toggle(Request $request, int $environmentId): JsonResponse
    {
        // Ensure user is super admin
        if (!$request->user() || $request->user()->role->value !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $environment = Environment::find($environmentId);

        if (!$environment) {
            return response()->json([
                'success' => false,
                'message' => 'Environment not found'
            ], 404);
        }

        $config = EnvironmentPaymentConfig::where('environment_id', $environmentId)->first();

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Payment config not found for this environment'
            ], 404);
        }

        // Toggle the value
        $config->use_centralized_gateways = !$config->use_centralized_gateways;
        $config->save();

        return response()->json([
            'success' => true,
            'message' => 'Centralized gateways ' . ($config->use_centralized_gateways ? 'enabled' : 'disabled'),
            'data' => $config
        ]);
    }
}
