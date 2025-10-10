<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Models\EnvironmentPaymentConfig;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class PaymentConfigController extends Controller
{
    /**
     * Get current payment configuration
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Get instructor's environment
        $environment = $user->ownedEnvironments()->first();

        if (!$environment) {
            return response()->json([
                'success' => false,
                'message' => 'No environment found for this instructor'
            ], 404);
        }

        // Get payment settings from environment metadata or settings column
        // For now, we'll use a settings JSON column (you may need to add this via migration)
        $paymentSettings = $environment->payment_settings ?? [
            'withdrawal_method' => null,
            'withdrawal_details' => []
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'withdrawal_method' => $paymentSettings['withdrawal_method'] ?? null,
                'withdrawal_details' => $paymentSettings['withdrawal_details'] ?? [],
                'available_methods' => [
                    'bank_transfer' => [
                        'name' => 'Bank Transfer',
                        'fields' => [
                            'account_name' => 'Account Holder Name',
                            'account_number' => 'Account Number',
                            'bank_name' => 'Bank Name',
                            'bank_code' => 'Bank Code (Optional)',
                            'swift_code' => 'SWIFT Code (Optional)'
                        ]
                    ],
                    'paypal' => [
                        'name' => 'PayPal',
                        'fields' => [
                            'paypal_email' => 'PayPal Email Address'
                        ]
                    ],
                    'mobile_money' => [
                        'name' => 'Mobile Money',
                        'fields' => [
                            'phone_number' => 'Phone Number',
                            'provider' => 'Provider (orange_money or mtn_mobile_money)',
                            'account_name' => 'Account Name'
                        ]
                    ]
                ]
            ]
        ]);
    }

    /**
     * Update withdrawal method and details
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!$user) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        // Get instructor's environment
        $environment = $user->ownedEnvironments()->first();

        if (!$environment) {
            return response()->json([
                'success' => false,
                'message' => 'No environment found for this instructor'
            ], 404);
        }

        // Validate basic structure
        $validator = Validator::make($request->all(), [
            'withdrawal_method' => 'required|in:bank_transfer,paypal,mobile_money',
            'withdrawal_details' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Validate withdrawal details based on method
        $detailsValidator = $this->validateWithdrawalDetails(
            $request->withdrawal_method,
            $request->withdrawal_details
        );

        if ($detailsValidator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid withdrawal details',
                'errors' => $detailsValidator->errors()
            ], 422);
        }

        // Update environment payment settings
        $paymentSettings = [
            'withdrawal_method' => $request->withdrawal_method,
            'withdrawal_details' => $request->withdrawal_details,
            'updated_at' => now()->toDateTimeString()
        ];

        // Store in environment settings (assuming payment_settings column exists)
        // If the column doesn't exist, you may need to create it via migration
        $environment->payment_settings = $paymentSettings;
        $environment->save();

        return response()->json([
            'success' => true,
            'message' => 'Payment configuration updated successfully',
            'data' => $paymentSettings
        ]);
    }

    /**
     * Validate withdrawal details based on method
     *
     * @param string $method
     * @param array $details
     * @return \Illuminate\Validation\Validator
     */
    private function validateWithdrawalDetails(string $method, array $details)
    {
        $rules = [];

        switch ($method) {
            case 'bank_transfer':
                $rules = [
                    'account_name' => 'required|string|max:255',
                    'account_number' => 'required|string|max:50',
                    'bank_name' => 'required|string|max:255',
                    'bank_code' => 'nullable|string|max:20',
                    'swift_code' => 'nullable|string|max:20',
                ];
                break;

            case 'paypal':
                $rules = [
                    'paypal_email' => 'required|email|max:255',
                ];
                break;

            case 'mobile_money':
                $rules = [
                    'phone_number' => 'required|string|max:20',
                    'provider' => 'required|string|in:orange_money,mtn_mobile_money',
                    'account_name' => 'required|string|max:255',
                ];
                break;
        }

        return Validator::make($details, $rules);
    }

    /**
     * Get centralized payment gateway configuration
     *
     * @return JsonResponse
     */
    public function getCentralizedConfig(): JsonResponse
    {
        $environmentId = session('current_environment_id');

        if (!$environmentId) {
            return response()->json([
                'success' => false,
                'message' => 'Environment ID is required'
            ], 400);
        }

        $config = EnvironmentPaymentConfig::where('environment_id', $environmentId)->first();

        if (!$config) {
            return response()->json([
                'success' => true,
                'data' => [
                    'use_centralized_gateways' => false,
                    'platform_fee_rate' => 0.17, // 17% platform fee (instructor receives 83%)
                    'instructor_payout_rate' => 0.83, // Instructor receives 83%
                    'minimum_withdrawal_amount' => 82.00, // $82 USD (≈50,000 XAF)
                    'payment_terms' => 'NET_30',
                ]
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'use_centralized_gateways' => $config->use_centralized_gateways,
                'platform_fee_rate' => $config->platform_fee_rate,
                'instructor_payout_rate' => 1 - $config->platform_fee_rate, // Calculate instructor's share
                'minimum_withdrawal_amount' => $config->minimum_withdrawal_amount,
                'payment_terms' => $config->payment_terms,
            ]
        ]);
    }

    /**
     * Toggle centralized payment gateways for instructor's environment
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function toggleCentralized(Request $request): JsonResponse
    {
        $environmentId = session('current_environment_id');

        if (!$environmentId) {
            return response()->json([
                'success' => false,
                'message' => 'Environment ID is required'
            ], 400);
        } 

        // Ensure user is instructor/admin of this environment
        $user = $request->user();
        if (!$user || !in_array($user->role->value, ['instructor', 'admin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Only instructors can manage payment settings.'
            ], 403);
        }

        $config = EnvironmentPaymentConfig::where('environment_id', $environmentId)->first();

        if (!$config) {
            // Create default config if it doesn't exist
            $config = EnvironmentPaymentConfig::create([
                'environment_id' => $environmentId,
                'use_centralized_gateways' => true, // Enable on first toggle
                'platform_fee_rate' => 0.17, // Platform takes 17% (instructor receives 83%)
                'payment_terms' => 'NET_30', // Default payment terms
                'minimum_withdrawal_amount' => 82.00, // Minimum withdrawal: $82 USD (≈50,000 XAF)
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Centralized payment gateways enabled successfully',
                'data' => [
                    'use_centralized_gateways' => $config->use_centralized_gateways,
                    'platform_fee_rate' => $config->platform_fee_rate,
                    'instructor_payout_rate' => 1 - $config->platform_fee_rate,
                    'minimum_withdrawal_amount' => $config->minimum_withdrawal_amount,
                    'payment_terms' => $config->payment_terms,
                ]
            ]);
        }

        // Toggle the centralized gateways setting
        $config->use_centralized_gateways = !$config->use_centralized_gateways;
        $config->save();

        return response()->json([
            'success' => true,
            'message' => 'Centralized payment gateways ' . ($config->use_centralized_gateways ? 'enabled' : 'disabled') . ' successfully',
            'data' => [
                'use_centralized_gateways' => $config->use_centralized_gateways,
                'platform_fee_rate' => $config->platform_fee_rate,
                'instructor_payout_rate' => 1 - $config->platform_fee_rate,
                'minimum_withdrawal_amount' => $config->minimum_withdrawal_amount,
                'payment_terms' => $config->payment_terms,
            ]
        ]);
    }
}
