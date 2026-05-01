<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PaymentGatewaySetting;
use App\Services\PaymentGateways\PaymentGatewayFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Schema(
 *     schema="PaymentGateway",
 *     required={"gateway_code", "name", "status", "mode"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="environment_id", type="integer", format="int64", example=1, nullable=true),
 *     @OA\Property(property="gateway_code", type="string", example="stripe"),
 *     @OA\Property(property="name", type="string", example="Stripe Payment Gateway"),
 *     @OA\Property(property="description", type="string", example="Process payments with Stripe", nullable=true),
 *     @OA\Property(property="status", type="boolean", example=true),
 *     @OA\Property(property="mode", type="string", enum={"sandbox", "live"}, example="sandbox"),
 *     @OA\Property(property="is_default", type="boolean", example=false),
 *     @OA\Property(property="webhook_url", type="string", example="https://example.com/webhook/stripe", nullable=true),
 *     @OA\Property(property="settings", type="object", example={"api_key": "sk_test_123", "publishable_key": "pk_test_123"}),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */

class PaymentGatewayController extends Controller
{
    /**
     * Display a listing of payment gateways.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/api/payment-gateways",
     *     summary="Get all payment gateways",
     *     description="Returns a list of all payment gateways with optional filtering",
     *     operationId="getPaymentGateways",
     *     tags={"Payment Gateways"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="environment_id",
     *         in="query",
     *         description="Filter by environment ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="status",
     *         in="query",
     *         description="Filter by status (true/false)",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="mode",
     *         in="query",
     *         description="Filter by mode (sandbox/live)",
     *         required=false,
     *         @OA\Schema(type="string", enum={"sandbox", "live"})
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/PaymentGateway")
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function index(Request $request)
    {
        // Validate request parameters
        $validator = Validator::make($request->all(), [
            'environment_id' => 'nullable|integer|exists:environments,id',
            'scope' => 'nullable|in:platform',
            'status' => 'nullable|boolean',
            'mode' => 'nullable|in:sandbox,live',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Build query with filters
        $query = $request->input('scope') === 'platform'
            ? PaymentGatewaySetting::withoutGlobalScopes()->whereNull('environment_id')
            : PaymentGatewaySetting::query();
        $environmentId = $request->environment_id;
        $isDemoEnvironment = false;

        // Check if this is a demo environment
        if ($environmentId) {
            $environment = \App\Models\Environment::find($environmentId);
            $isDemoEnvironment = $environment && $environment->is_demo;
        }

        if ($request->has('environment_id')) {
            $query->where('environment_id', $request->environment_id);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('mode')) {
            $query->where('mode', $request->mode);
        }

        // Get payment gateways
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
     * Store a newly created payment gateway in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/api/payment-gateways",
     *     summary="Create a new payment gateway",
     *     description="Creates a new payment gateway setting",
     *     operationId="storePaymentGateway",
     *     tags={"Payment Gateways"},
     *     security={{"sanctum":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"gateway_code", "name", "status", "mode"},
     *             @OA\Property(property="environment_id", type="integer", example=1, nullable=true),
     *             @OA\Property(property="gateway_code", type="string", example="stripe"),
     *             @OA\Property(property="name", type="string", example="Stripe Payment Gateway"),
     *             @OA\Property(property="description", type="string", example="Process payments with Stripe", nullable=true),
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="mode", type="string", enum={"sandbox", "live"}, example="sandbox"),
     *             @OA\Property(property="is_default", type="boolean", example=false),
     *             @OA\Property(property="webhook_url", type="string", example="https://example.com/webhook/stripe", nullable=true),
     *             @OA\Property(property="settings", type="object", example={"api_key": "sk_test_123", "publishable_key": "pk_test_123"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Payment gateway created successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Payment gateway created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/PaymentGateway")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request)
    {
        // Validate request data
        $validator = Validator::make($request->all(), [
            'environment_id' => 'nullable|integer|exists:environments,id',
            'gateway_name' => 'required|string|in:' . implode(',', PaymentGatewayFactory::getAvailableGateways()),
            'code' => 'required|string|in:' . implode(',', PaymentGatewayFactory::getAvailableGateways()),
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|boolean',
            'mode' => 'required|in:sandbox,live',
            'is_default' => 'boolean',
            'webhook_url' => 'nullable|url',
            'success_url' => 'nullable|url',
            'failure_url' => 'nullable|url',
            'settings' => 'required|array',
            // Stripe validation
            'settings.api_key' => 'required_if:gateway_name,stripe,lygos,taramoney|string',
            'settings.publishable_key' => 'required_if:gateway_name,stripe|string',
            // PayPal validation
            'settings.client_id' => 'required_if:gateway_name,paypal|string',
            'settings.client_secret' => 'required_if:gateway_name,paypal|string',
            // TaraMoney validation
            'settings.business_id' => 'required_if:gateway_name,taramoney|string',
            'settings.webhook_secret' => 'nullable|string',
            'settings.test_api_key' => 'nullable|string',
            'settings.test_business_id' => 'nullable|string',
            'settings.test_mode' => 'nullable|boolean',
            // Moneroo validation
            'settings.public_key' => 'required_if:gateway_name,moneroo|string',
            'settings.secret_key' => 'required_if:gateway_name,moneroo|string',
            'settings.test_public_key' => 'nullable|string',
            'settings.test_secret_key' => 'nullable|string',
            // MonetBill validation
            'settings.service_key' => 'required_if:gateway_name,monetbill|string',
            'settings.service_secret' => 'required_if:gateway_name,monetbill|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // We'll handle the default flag separately after creation

        $environmentId = $request->input('environment_id', null);
        $callbackEnvironmentId = $environmentId ?: 'platform';

        // Generate callback URLs using route helpers
        $webhookUrl = $request->input('webhook_url') ?: route('api.transactions.webhook', [
            'gateway' => $request->input('code'),
            'environment_id' => $callbackEnvironmentId
        ]);

        $successUrl = $request->input('success_url') ?: route('api.transactions.callback.success', [
            'environment_id' => $callbackEnvironmentId
        ]);

        $failureUrl = $request->input('failure_url') ?: route('api.transactions.callback.failure', [
            'environment_id' => $callbackEnvironmentId
        ]);

        $gatewayPayload = [
            'environment_id' => $environmentId,
            'gateway_name' => $request->input('gateway_name'),
            'code' => $request->input('code'),
            'display_name' => $request->input('name'),
            'description' => $request->input('description'),
            'status' => $request->input('status'),
            'mode' => $request->input('mode'),
            'is_default' => false,
            'webhook_url' => $webhookUrl,
            'success_url' => $successUrl,
            'failure_url' => $failureUrl,
            'settings' => json_encode($request->input('settings')),
            'created_at' => now(),
            'updated_at' => now(),
        ];

        // Create payment gateway
        try {
            if ($environmentId === null) {
                $existingPlatformGateway = PaymentGatewaySetting::withoutGlobalScopes()
                    ->whereNull('environment_id')
                    ->where('code', $request->input('code'))
                    ->first();

                if ($existingPlatformGateway) {
                    throw ValidationException::withMessages([
                        'code' => ["A platform payment gateway with code '{$request->input('code')}' already exists."]
                    ]);
                }

                $paymentGatewayId = DB::table('payment_gateway_settings')->insertGetId($gatewayPayload);
                $paymentGateway = PaymentGatewaySetting::withoutGlobalScopes()->findOrFail($paymentGatewayId);
            } else {
                $paymentGateway = PaymentGatewaySetting::create($gatewayPayload);
            }
        } catch (ValidationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        } catch (UniqueConstraintViolationException $e) {
            return response()->json([
                'status' => 'error',
                'message' => "A payment gateway with code '{$request->input('code')}' already exists in this environment.",
                'errors' => [
                    'code' => ["A payment gateway with this code already exists in this environment."]
                ],
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Handle default flag separately if needed
        if ($request->input('is_default', false)) {
            DB::statement('SET @disable_triggers = 1');
            try {
                // Reset all other gateways
                DB::table('payment_gateway_settings')
                    ->where('environment_id', $request->input('environment_id'))
                    ->where('id', '!=', $paymentGateway->id)
                    ->update(['is_default' => false]);

                // Set this gateway as default
                DB::table('payment_gateway_settings')
                    ->where('id', $paymentGateway->id)
                    ->update(['is_default' => true]);
            } finally {
                DB::statement('SET @disable_triggers = NULL');
            }
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
            'message' => 'Payment gateway created successfully',
            'data' => $responseData,
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified payment gateway.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/api/payment-gateways/{id}",
     *     summary="Get a specific payment gateway",
     *     description="Returns details of a specific payment gateway",
     *     operationId="getPaymentGateway",
     *     tags={"Payment Gateways"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Payment gateway ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/PaymentGateway")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Payment gateway not found"
     *     )
     * )
     */
    public function show($id)
    {
        $paymentGateway = PaymentGatewaySetting::findOrFail($id);

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
     * Update the specified payment gateway in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * 
     * @OA\Put(
     *     path="/api/payment-gateways/{id}",
     *     summary="Update a payment gateway",
     *     description="Updates an existing payment gateway",
     *     operationId="updatePaymentGateway",
     *     tags={"Payment Gateways"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Payment gateway ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="environment_id", type="integer", example=1, nullable=true),
     *             @OA\Property(property="name", type="string", example="Updated Stripe Gateway"),
     *             @OA\Property(property="description", type="string", example="Updated description", nullable=true),
     *             @OA\Property(property="status", type="boolean", example=true),
     *             @OA\Property(property="mode", type="string", enum={"sandbox", "live"}, example="live"),
     *             @OA\Property(property="is_default", type="boolean", example=true),
     *             @OA\Property(property="settings", type="object", example={"api_key": "sk_live_123", "publishable_key": "pk_live_123"})
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment gateway updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Payment gateway updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/PaymentGateway")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Payment gateway not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $paymentGateway = PaymentGatewaySetting::findOrFail($id);

        // Validate request data
        $validator = Validator::make($request->all(), [
            'environment_id' => 'nullable|integer|exists:environments,id',
            'gateway_name' => 'string|max:255',
            'code' => 'string|max:255',
            'name' => 'string|max:255',
            'description' => 'nullable|string',
            'status' => 'boolean',
            'mode' => 'in:sandbox,live',
            'is_default' => 'boolean',
            'webhook_url' => 'nullable|url',
            'success_url' => 'nullable|url',
            'failure_url' => 'nullable|url',
            'settings' => 'array',
            // Stripe validation
            'settings.api_key' => 'string|required_if:gateway_name,stripe,lygos,taramoney',
            'settings.publishable_key' => 'string|required_if:gateway_name,stripe',
            // PayPal validation
            'settings.client_id' => 'string|required_if:gateway_name,paypal',
            'settings.client_secret' => 'string|required_if:gateway_name,paypal',
            // TaraMoney validation
            'settings.business_id' => 'string|required_if:gateway_name,taramoney',
            'settings.webhook_secret' => 'nullable|string',
            'settings.test_api_key' => 'nullable|string',
            'settings.test_business_id' => 'nullable|string',
            'settings.test_mode' => 'nullable|boolean',
            // Moneroo validation
            'settings.public_key' => 'string|required_if:gateway_name,moneroo',
            'settings.secret_key' => 'string|required_if:gateway_name,moneroo',
            'settings.test_public_key' => 'nullable|string',
            'settings.test_secret_key' => 'nullable|string',
            // MonetBill validation
            'settings.service_key' => 'string|required_if:gateway_name,monetbill',
            'settings.service_secret' => 'string|required_if:gateway_name,monetbill',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // We'll handle the default flag separately after the main update

        // Update basic fields
        $updateData = $request->only([
            'gateway_name',
            'code',
            'description',
            'status',
            'mode',
            'is_default',
        ]);

        if ($request->has('name')) {
            $updateData['display_name'] = $request->input('name');
        }

        $callbackEnvironmentId = $paymentGateway->environment_id ?: 'platform';

        // Generate callback URLs using route helpers
        $updateData['webhook_url'] = $request->input('webhook_url') ?: route('api.transactions.webhook', [
            'gateway' => $request->input('code') ?: $paymentGateway->code,
            'environment_id' => $callbackEnvironmentId
        ]);

        $updateData['success_url'] = $request->input('success_url') ?: route('api.transactions.callback.success', [
            'environment_id' => $callbackEnvironmentId
        ]);

        $updateData['failure_url'] = $request->input('failure_url') ?: route('api.transactions.callback.failure', [
            'environment_id' => $callbackEnvironmentId
        ]);

        // Handle settings update
        if ($request->has('settings')) {
            // Get current settings
            $currentSettings = json_decode((string)$paymentGateway->settings, true) ?: [];

            $incomingSettings = collect($request->input('settings'))
                ->reject(fn ($value) => $value === null || $value === '' || (is_string($value) && str_starts_with($value, '••••')))
                ->all();

            // Merge with new settings without overwriting existing secrets with
            // blank or masked values from admin forms.
            $newSettings = array_merge($currentSettings, $incomingSettings);

            $updateData['settings'] = json_encode($newSettings);
        }

        // Handle is_default flag separately to avoid trigger issues
        $isDefault = $request->has('is_default') ? (bool)$request->input('is_default') : false;
        unset($updateData['is_default']);

        // First update all other fields except is_default
        $paymentGateway->update($updateData);

        // Only update is_default if it's changing (not already the default)
        if ($isDefault && !$paymentGateway->is_default) {
            // First set all other gateways to non-default using a direct query
            DB::table('payment_gateway_settings')
                ->where('environment_id', $paymentGateway->environment_id)
                ->where('id', '!=', $id)
                ->update(['is_default' => false]);

            // Then set this one as default without triggering the update trigger
            DB::statement("UPDATE payment_gateway_settings SET is_default = 1 WHERE id = {$id}");

            // Refresh the model
            $paymentGateway = $paymentGateway->fresh();
        }

        // Prepare response data
        $responseData = $paymentGateway->fresh()->toArray();

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
            'message' => 'Payment gateway updated successfully',
            'data' => $responseData,
        ]);
    }

    /**
     * Remove the specified payment gateway from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * 
     * @OA\Delete(
     *     path="/api/payment-gateways/{id}",
     *     summary="Delete a payment gateway",
     *     description="Deletes an existing payment gateway",
     *     operationId="deletePaymentGateway",
     *     tags={"Payment Gateways"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Payment gateway ID",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Payment gateway deleted successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Payment gateway deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Payment gateway not found"
     *     ),
     *     @OA\Response(
     *         response=409,
     *         description="Payment gateway is in use and cannot be deleted"
     *     )
     * )
     */
    public function destroy($id)
    {
        $paymentGateway = PaymentGatewaySetting::findOrFail($id);

        // Check if payment gateway is in use
        $transactionCount = $paymentGateway->transactions()->count();

        if ($transactionCount > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payment gateway is in use and cannot be deleted. It has ' . $transactionCount . ' transactions associated with it.',
            ], Response::HTTP_CONFLICT);
        }

        // Delete payment gateway
        $paymentGateway->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Payment gateway deleted successfully',
        ]);
    }

    /**
     * Get available payment gateway types.
     *
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/api/payment-gateways/types",
     *     summary="Get available payment gateway types",
     *     description="Returns a list of all available payment gateway types",
     *     operationId="getPaymentGatewayTypes",
     *     tags={"Payment Gateways"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="code", type="string", example="stripe"),
     *                     @OA\Property(property="name", type="string", example="Stripe"),
     *                     @OA\Property(property="description", type="string", example="Process payments with Stripe")
     *                 )
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function getTypes()
    {
        $gatewayTypes = [
            [
                'code' => 'stripe',
                'name' => 'Stripe',
                'description' => 'Process payments with Stripe',
                'required_settings' => [
                    'api_key' => 'API Key',
                    'publishable_key' => 'Publishable Key',
                    'webhook_secret' => 'Webhook Secret (optional)'
                ]
            ],
            [
                'code' => 'paypal',
                'name' => 'PayPal',
                'description' => 'Process payments with PayPal',
                'required_settings' => [
                    'client_id' => 'Client ID',
                    'client_secret' => 'Client Secret'
                ]
            ],
            [
                'code' => 'lygos',
                'name' => 'Lygos',
                'description' => 'Process payments with Lygos',
                'required_settings' => [
                    'api_key' => 'API Key',
                    'merchant_id' => 'Merchant ID (optional)',
                    'api_url' => 'API URL (optional)'
                ]
            ],
            [
                'code' => 'monetbill',
                'name' => 'MonetBill',
                'description' => 'Process mobile money payments with MonetBill',
                'required_settings' => [
                    'service_key' => 'Service Key',
                    'service_secret' => 'Service Secret',
                    'test_service_key' => 'Test Service Key (optional)',
                    'test_service_secret' => 'Test Service Secret (optional)',
                    'widget_version' => 'Widget Version',
                    'logo_url' => 'Logo URL (optional)',
                    'supported_currencies' => 'Supported Currencies (optional)'
                ]
            ],
            [
                'code' => 'moneroo',
                'name' => 'Moneroo',
                'description' => 'Process payments with Moneroo payment orchestration',
                'website_url' => 'https://moneroo.io/en',
                'docs_url' => 'https://docs.moneroo.io/',
                'required_settings' => [
                    'public_key' => 'Public Key',
                    'secret_key' => 'Secret Key',
                    'webhook_secret' => 'Webhook Secret (optional)',
                    'test_public_key' => 'Test Public Key (optional)',
                    'test_secret_key' => 'Test Secret Key (optional)',
                    'test_mode' => 'Test Mode (optional)',
                    'api_url' => 'API URL (optional)'
                ]
            ],
            [
                'code' => 'taramoney',
                'name' => 'TaraMoney',
                'description' => 'Pay with TaraMoney - WhatsApp, Telegram, PayPal or Mobile Money',
                'required_settings' => [
                    'api_key' => 'API Key',
                    'business_id' => 'Business ID',
                    'webhook_secret' => 'Webhook Secret (optional)',
                    'test_api_key' => 'Test API Key (optional)',
                    'test_business_id' => 'Test Business ID (optional)',
                    'logo_url' => 'Logo URL (optional)',
                    'supported_currencies' => 'Supported Currencies (optional)'
                ]
            ],
            [
                'code' => 'campay',
                'name' => 'CamPay',
                'description' => 'Process mobile money payments with CamPay',
                'coming_soon' => true,
                'required_settings' => [
                    'api_key' => 'API Key',
                    'api_secret' => 'API Secret',
                    'webhook_secret' => 'Webhook Secret (optional)'
                ]
            ],
            [
                'code' => 'cinetpay',
                'name' => 'CinetPay',
                'description' => 'Process payments with CinetPay',
                'coming_soon' => true,
                'required_settings' => [
                    'api_key' => 'API Key',
                    'site_id' => 'Site ID',
                    'secret_key' => 'Secret Key (optional)'
                ]
            ],
            [
                'code' => 'dohone',
                'name' => 'Dohone',
                'description' => 'Process payments with Dohone',
                'coming_soon' => true,
                'required_settings' => [
                    'api_key' => 'API Key',
                    'merchant_id' => 'Merchant ID',
                    'webhook_secret' => 'Webhook Secret (optional)'
                ]
            ],
            [
                'code' => 'flutterwave',
                'name' => 'Flutterwave',
                'description' => 'Process card, bank, and mobile money payments with Flutterwave',
                'coming_soon' => true,
                'required_settings' => [
                    'public_key' => 'Public Key',
                    'secret_key' => 'Secret Key',
                    'encryption_key' => 'Encryption Key (optional)',
                    'webhook_secret' => 'Webhook Secret (optional)'
                ]
            ],
            [
                'code' => 'mastercard',
                'name' => 'Mastercard',
                'description' => 'Process Mastercard payments',
                'coming_soon' => true,
                'required_settings' => [
                    'merchant_id' => 'Merchant ID',
                    'api_key' => 'API Key',
                    'api_secret' => 'API Secret'
                ]
            ],
            [
                'code' => 'mycoolpay',
                'name' => 'MyCoolPay',
                'description' => 'Process payments with MyCoolPay',
                'coming_soon' => true,
                'required_settings' => [
                    'public_key' => 'Public Key',
                    'private_key' => 'Private Key',
                    'webhook_secret' => 'Webhook Secret (optional)'
                ]
            ],
            [
                'code' => 'olkupay',
                'name' => 'OlkuPay',
                'description' => 'Process payments with OlkuPay',
                'coming_soon' => true,
                'required_settings' => [
                    'api_key' => 'API Key',
                    'merchant_id' => 'Merchant ID',
                    'webhook_secret' => 'Webhook Secret (optional)'
                ]
            ],
            [
                'code' => 'revolut',
                'name' => 'Revolut',
                'description' => 'Process payments with Revolut Business',
                'coming_soon' => true,
                'required_settings' => [
                    'api_key' => 'API Key',
                    'webhook_secret' => 'Webhook Secret (optional)'
                ]
            ],
            [
                'code' => 'tranzak',
                'name' => 'Tranzak',
                'description' => 'Process mobile money and card payments with Tranzak',
                'coming_soon' => true,
                'required_settings' => [
                    'app_id' => 'App ID',
                    'app_key' => 'App Key',
                    'webhook_secret' => 'Webhook Secret (optional)'
                ]
            ],
            [
                'code' => 'visa',
                'name' => 'Visa',
                'description' => 'Process Visa card payments',
                'coming_soon' => true,
                'required_settings' => [
                    'merchant_id' => 'Merchant ID',
                    'api_key' => 'API Key',
                    'api_secret' => 'API Secret'
                ]
            ]
        ];

        return response()->json([
            'status' => 'success',
            'data' => $gatewayTypes,
        ]);
    }
}
