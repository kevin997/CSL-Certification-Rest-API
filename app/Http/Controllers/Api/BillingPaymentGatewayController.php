<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentGatewaySetting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class BillingPaymentGatewayController extends Controller
{
    /**
     * Get payment gateways for billing/subscription purposes.
     * Always uses the default environment (ID 1) payment gateways.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        // Validate request parameters
        $validator = Validator::make($request->all(), [
            'status' => 'nullable|boolean',
            'mode' => 'nullable|in:sandbox,live',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Build query with filters - always use default environment (ID 1) for billing
        // Use withoutGlobalScopes to bypass any environment-based global scopes
        $query = PaymentGatewaySetting::withoutGlobalScopes()
            ->where('environment_id', 1)
            ->where('code', '!=', 'lygos'); // Exclude lygos from billing

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('mode')) {
            $query->where('mode', $request->mode);
        }

        // Get payment gateways from default environment
        $paymentGateways = $query->get();

        // Prepare response data
        $responseData = $paymentGateways->map(function ($gateway) {
            $data = $gateway->toArray();

            // Mask sensitive information
            if (isset($data['settings'])) {
                $settings = json_decode($data['settings'], true) ?: [];

                foreach ($settings as $key => $value) {
                    if (in_array($key, ['api_key', 'client_secret', 'secret_key', 'webhook_secret'])) {
                        $settings[$key] = '••••••••' . substr($value, -4);
                    }
                }

                $data['settings'] = $settings;
            }

            return $data;
        });

        return response()->json([
            'status' => 'success',
            'data' => $responseData,
        ]);
    }

    /**
     * Get a specific payment gateway for billing purposes.
     * Always uses the default environment (ID 1).
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        // Find payment gateway in default environment only
        // Use withoutGlobalScopes to bypass any environment-based global scopes
        $paymentGateway = PaymentGatewaySetting::withoutGlobalScopes()
            ->where('environment_id', 1)
            ->where('code', '!=', 'lygos') // Exclude lygos from billing
            ->where('id', $id)
            ->first();

        if (!$paymentGateway) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payment gateway not found in default environment',
            ], Response::HTTP_NOT_FOUND);
        }

        // Prepare response data
        $responseData = $paymentGateway->toArray();

        // Mask sensitive information
        if (isset($responseData['settings'])) {
            $settings = json_decode($responseData['settings'], true) ?: [];

            foreach ($settings as $key => $value) {
                if (in_array($key, ['api_key', 'client_secret', 'secret_key', 'webhook_secret'])) {
                    $settings[$key] = '••••••••' . substr($value, -4);
                }
            }

            $responseData['settings'] = $settings;
        }

        return response()->json([
            'status' => 'success',
            'data' => $responseData,
        ]);
    }

    /**
     * Get available payment gateway types for billing.
     * Returns the supported gateway types from the default environment.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailableTypes()
    {
        // Get unique gateway types from default environment
        // Use withoutGlobalScopes to bypass any environment-based global scopes
        $availableTypes = PaymentGatewaySetting::withoutGlobalScopes()
            ->where('environment_id', 1)
            ->where('code', '!=', 'lygos') // Exclude lygos from billing
            ->where('status', true)
            ->distinct()
            ->pluck('code')
            ->toArray();

        return response()->json([
            'status' => 'success',
            'data' => $availableTypes,
        ]);
    }

    /**
     * Get the default payment gateway for billing.
     * Always uses the default environment (ID 1).
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDefault()
    {
        // Use withoutGlobalScopes to bypass any environment-based global scopes
        $defaultGateway = PaymentGatewaySetting::withoutGlobalScopes()
            ->where('environment_id', 1)
            ->where('code', '!=', 'lygos') // Exclude lygos from billing
            ->where('is_default', true)
            ->where('status', true)
            ->first();

        if (!$defaultGateway) {
            return response()->json([
                'status' => 'error',
                'message' => 'No default payment gateway found',
            ], Response::HTTP_NOT_FOUND);
        }

        // Prepare response data
        $responseData = $defaultGateway->toArray();

        // Mask sensitive information
        if (isset($responseData['settings'])) {
            $settings = json_decode($responseData['settings'], true) ?: [];

            foreach ($settings as $key => $value) {
                if (in_array($key, ['api_key', 'client_secret', 'secret_key', 'webhook_secret'])) {
                    $settings[$key] = '••••••••' . substr($value, -4);
                }
            }

            $responseData['settings'] = $settings;
        }

        return response()->json([
            'status' => 'success',
            'data' => $responseData,
        ]);
    }
}
