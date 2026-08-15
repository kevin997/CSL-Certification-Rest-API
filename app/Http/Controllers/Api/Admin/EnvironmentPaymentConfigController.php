<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\EnvironmentPaymentConfig;
use App\Models\PaymentGatewaySetting;
use App\Services\EnvironmentPaymentConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EnvironmentPaymentConfigController extends Controller
{
    /**
     * List all environment payment configs
     */
    public function index(Request $request): JsonResponse
    {
        // Ensure user is super admin
        if (! $request->user() || $request->user()->role->value !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $configs = EnvironmentPaymentConfig::with('environment')
            ->orderBy('environment_id')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $configs,
        ]);
    }

    /**
     * Get config for specific environment
     */
    public function show(Request $request, int $environmentId): JsonResponse
    {
        // Ensure user is super admin
        if (! $request->user() || $request->user()->role->value !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $environment = Environment::find($environmentId);

        if (! $environment) {
            return response()->json([
                'success' => false,
                'message' => 'Environment not found',
            ], 404);
        }

        $config = EnvironmentPaymentConfig::where('environment_id', $environmentId)->first();

        if (! $config) {
            return response()->json([
                'success' => false,
                'message' => 'Payment config not found for this environment',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $config,
            'environment' => $environment,
        ]);
    }

    /**
     * Update environment payment config
     */
    public function update(Request $request, int $environmentId): JsonResponse
    {
        // Ensure user is super admin
        if (! $request->user() || $request->user()->role->value !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $environment = Environment::find($environmentId);

        if (! $environment) {
            return response()->json([
                'success' => false,
                'message' => 'Environment not found',
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
                'errors' => $validator->errors(),
            ], 422);
        }

        $config = EnvironmentPaymentConfig::where('environment_id', $environmentId)->first();

        if (! $config) {
            return response()->json([
                'success' => false,
                'message' => 'Payment config not found for this environment',
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

        // This controller writes the row directly rather than going through
        // EnvironmentPaymentConfigService, so it must drop the service's cached
        // copy itself — otherwise the change takes up to an hour to reach
        // payment routing.
        app(EnvironmentPaymentConfigService::class)->invalidateCache($environmentId);

        return response()->json([
            'success' => true,
            'message' => 'Payment config updated successfully',
            'data' => $config,
        ]);
    }

    /**
     * Toggle centralized gateways for an environment
     */
    public function toggle(Request $request, int $environmentId): JsonResponse
    {
        // Ensure user is super admin
        if (! $request->user() || $request->user()->role->value !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $environment = Environment::find($environmentId);

        if (! $environment) {
            return response()->json([
                'success' => false,
                'message' => 'Environment not found',
            ], 404);
        }

        $config = EnvironmentPaymentConfig::where('environment_id', $environmentId)->first();

        if (! $config) {
            return response()->json([
                'success' => false,
                'message' => 'Payment config not found for this environment',
            ], 404);
        }

        $paymentConfigService = app(EnvironmentPaymentConfigService::class);

        // The provider owns the shared gateways; borrowing from itself would be
        // a no-op that reads as a working toggle.
        if (! $config->use_centralized_gateways && $paymentConfigService->isCentralizedEnvironment($environmentId)) {
            return response()->json([
                'success' => false,
                'message' => 'This environment provides the centralized payment gateways and cannot use them itself.',
            ], 422);
        }

        // Toggle the value
        $config->use_centralized_gateways = ! $config->use_centralized_gateways;
        $config->save();

        // Written directly, so the service's cached copy must be dropped here.
        $paymentConfigService->invalidateCache($environmentId);

        return response()->json([
            'success' => true,
            'message' => 'Centralized gateways '.($config->use_centralized_gateways ? 'enabled' : 'disabled'),
            'data' => $config,
        ]);
    }

    /**
     * Show which environment currently provides the centralized gateways,
     * alongside the environments eligible to replace it.
     */
    public function showCentralizedProvider(Request $request): JsonResponse
    {
        if (! $request->user() || $request->user()->role->value !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $paymentConfigService = app(EnvironmentPaymentConfigService::class);
        $currentId = $paymentConfigService->getCentralizedEnvironmentId();

        // Environment has no paymentGatewaySettings relation, so count in one
        // grouped query rather than per row.
        $gatewayCounts = PaymentGatewaySetting::withoutGlobalScopes()
            ->where('status', true)
            ->whereNotNull('environment_id')
            ->groupBy('environment_id')
            ->selectRaw('environment_id, COUNT(*) as total')
            ->pluck('total', 'environment_id');

        $environments = Environment::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'primary_domain', 'is_centralized_payment_provider']);

        $hasFlaggedProvider = $environments->contains('is_centralized_payment_provider', true);

        $candidates = $environments->map(function (Environment $environment) use ($gatewayCounts) {
            return [
                'id' => $environment->id,
                'name' => $environment->name,
                'primary_domain' => $environment->primary_domain,
                'is_centralized_payment_provider' => (bool) $environment->is_centralized_payment_provider,
                'active_gateway_count' => (int) ($gatewayCounts[$environment->id] ?? 0),
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => [
                'current_environment_id' => $currentId,
                // True while the id still comes from config because no
                // environment carries the flag — nobody has chosen one yet.
                'resolved_from_config' => $currentId !== null && ! $hasFlaggedProvider,
                'borrowing_environment_count' => EnvironmentPaymentConfig::where('use_centralized_gateways', true)->count(),
                'candidates' => $candidates,
            ],
        ]);
    }

    /**
     * Make an environment the centralized gateway provider.
     */
    public function setCentralizedProvider(Request $request): JsonResponse
    {
        if (! $request->user() || $request->user()->role->value !== 'super_admin') {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'environment_id' => 'required|integer|exists:environments,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $environmentId = (int) $request->input('environment_id');

        // Every borrowing tenant's checkout moves to this environment's
        // gateways, so refuse an environment that has none enabled.
        $activeGateways = PaymentGatewaySetting::withoutGlobalScopes()
            ->where('environment_id', $environmentId)
            ->where('status', true)
            ->count();

        if ($activeGateways === 0) {
            return response()->json([
                'success' => false,
                'message' => 'This environment has no active payment gateways, so centralized tenants would have no way to pay.',
            ], 422);
        }

        try {
            $environment = app(EnvironmentPaymentConfigService::class)
                ->setCentralizedEnvironment($environmentId);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => "{$environment->name} now provides the centralized payment gateways.",
            'data' => $environment->only(['id', 'name', 'primary_domain', 'is_centralized_payment_provider']),
        ]);
    }
}
