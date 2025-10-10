<?php

namespace App\Http\Controllers\Api\Instructor;

use App\Http\Controllers\Controller;
use App\Models\WithdrawalRequest;
use App\Models\InstructorCommission;
use App\Models\EnvironmentPaymentConfig;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class WithdrawalController extends Controller
{
    /**
     * List instructor's withdrawal requests
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
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

        $query = WithdrawalRequest::where('environment_id', $environment->id)
            ->with('commissions');

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Sort by created_at descending
        $query->orderBy('created_at', 'desc');

        $withdrawals = $query->paginate($request->get('per_page', 15));

        return response()->json([
            'success' => true,
            'data' => $withdrawals
        ]);
    }

    /**
     * Create new withdrawal request
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
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

        // Get payment config for validation
        $paymentConfig = EnvironmentPaymentConfig::where('environment_id', $environment->id)->first();

        if (!$paymentConfig) {
            return response()->json([
                'success' => false,
                'message' => 'Payment configuration not found for this environment'
            ], 404);
        }

        // Get available balance
        $availableBalance = InstructorCommission::where('environment_id', $environment->id)
            ->where('status', 'approved')
            ->whereNull('withdrawal_request_id')
            ->sum('amount');

        // Validate request
        $validator = Validator::make($request->all(), [
            'amount' => [
                'required',
                'numeric',
                'min:' . $paymentConfig->minimum_withdrawal_amount,
                'max:' . $availableBalance
            ],
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

        DB::beginTransaction();
        try {
            // Create withdrawal request
            $withdrawal = WithdrawalRequest::create([
                'environment_id' => $environment->id,
                'amount' => $request->amount,
                'currency' => 'USD',
                'withdrawal_method' => $request->withdrawal_method,
                'withdrawal_details' => json_encode($request->withdrawal_details),
                'status' => 'pending',
            ]);

            // Get approved commissions to attach to this withdrawal
            $commissions = InstructorCommission::where('environment_id', $environment->id)
                ->where('status', 'approved')
                ->whereNull('withdrawal_request_id')
                ->orderBy('created_at', 'asc')
                ->get();

            $totalAttached = 0;
            foreach ($commissions as $commission) {
                if ($totalAttached + $commission->amount <= $request->amount) {
                    $commission->withdrawal_request_id = $withdrawal->id;
                    $commission->save();
                    $totalAttached += $commission->amount;
                }

                if ($totalAttached >= $request->amount) {
                    break;
                }
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Withdrawal request created successfully',
                'data' => $withdrawal->load('commissions')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create withdrawal request: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get withdrawal request details
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, int $id): JsonResponse
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

        $withdrawal = WithdrawalRequest::where('id', $id)
            ->where('environment_id', $environment->id)
            ->with('commissions.transaction')
            ->first();

        if (!$withdrawal) {
            return response()->json([
                'success' => false,
                'message' => 'Withdrawal request not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $withdrawal
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
}
