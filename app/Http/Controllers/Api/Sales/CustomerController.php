<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Enums\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class CustomerController extends Controller
{
    /**
     * Display a listing of customers.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // Validate admin access
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $query = User::where('role', '!=', UserRole::SALES_AGENT)
                    ->where('is_admin', false);

        // Apply search filter if provided
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('whatsapp_number', 'like', "%{$search}%")
                  ->orWhere('company_name', 'like', "%{$search}%");
            });
        }

        // Apply status filter if provided
        if ($request->has('status')) {
            if ($request->status === 'verified') {
                $query->whereNotNull('email_verified_at');
            } elseif ($request->status === 'unverified') {
                $query->whereNull('email_verified_at');
            }
        }

        // Apply pagination
        $perPage = $request->get('per_page', 15);
        $customers = $query->orderBy('created_at', 'desc')->paginate($perPage);

        // Format the response
        $formattedCustomers = $customers->getCollection()->map(function ($customer) {
            return [
                'id' => $customer->id,
                'name' => $customer->name,
                'email' => $customer->email,
                'whatsapp_number' => $customer->whatsapp_number,
                'company_name' => $customer->company_name,
                'role' => $customer->role,
                'status' => $customer->email_verified_at ? 'Verified' : 'Unverified',
                'email_verified_at' => $customer->email_verified_at,
                'created_at' => $customer->created_at,
                'updated_at' => $customer->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedCustomers,
            'pagination' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'per_page' => $customers->perPage(),
                'total' => $customers->total(),
            ]
        ]);
    }

    /**
     * Display the specified customer.
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function show($id, Request $request)
    {
        // Validate admin access
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Find the customer (exclude sales agents and admins)
        $customer = User::where('id', $id)
            ->where('role', '!=', UserRole::SALES_AGENT)
            ->where('is_admin', false)
            ->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        }

        // Format the response
        $formattedCustomer = [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'whatsapp_number' => $customer->whatsapp_number,
            'company_name' => $customer->company_name,
            'role' => $customer->role,
            'status' => $customer->email_verified_at ? 'Verified' : 'Unverified',
            'email_verified_at' => $customer->email_verified_at,
            'created_at' => $customer->created_at,
            'updated_at' => $customer->updated_at,
        ];

        return response()->json([
            'success' => true,
            'data' => $formattedCustomer
        ]);
    }

    /**
     * Update the specified customer.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        // Validate admin access
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Find the customer
        $customer = User::where('id', $id)
            ->where('role', '!=', UserRole::SALES_AGENT)
            ->where('is_admin', false)
            ->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
            'whatsapp_number' => 'sometimes|string|max:20',
            'company_name' => 'sometimes|string|max:255',
            'password' => 'sometimes|string|min:8',
            'status' => 'sometimes|string|in:Verified,Unverified',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update the customer
        if ($request->has('name')) {
            $customer->name = $request->name;
        }

        if ($request->has('email')) {
            $customer->email = $request->email;
        }

        if ($request->has('whatsapp_number')) {
            $customer->whatsapp_number = $request->whatsapp_number;
        }

        if ($request->has('company_name')) {
            $customer->company_name = $request->company_name;
        }

        if ($request->has('password')) {
            $customer->password = Hash::make($request->password);
        }

        if ($request->has('status')) {
            if ($request->status === 'Verified') {
                $customer->email_verified_at = now();
            } else {
                $customer->email_verified_at = null;
            }
        }

        $customer->save();

        // Format the response
        $formattedCustomer = [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'whatsapp_number' => $customer->whatsapp_number,
            'company_name' => $customer->company_name,
            'role' => $customer->role,
            'status' => $customer->email_verified_at ? 'Verified' : 'Unverified',
            'email_verified_at' => $customer->email_verified_at,
            'created_at' => $customer->created_at,
            'updated_at' => $customer->updated_at,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Customer updated successfully',
            'data' => $formattedCustomer
        ]);
    }

    /**
     * Remove the specified customer.
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function destroy($id, Request $request)
    {
        // Validate admin access
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Find the customer
        $customer = User::where('id', $id)
            ->where('role', '!=', UserRole::SALES_AGENT)
            ->where('is_admin', false)
            ->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found'
            ], 404);
        }

        // Check if the customer has any related data that would prevent deletion
        // You might want to add additional checks here based on your business logic

        // Delete the customer
        $customer->delete();

        return response()->json([
            'success' => true,
            'message' => 'Customer deleted successfully'
        ]);
    }

    /**
     * Get customer statistics for admin dashboard.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getStats(Request $request)
    {
        // Validate admin access
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Get customer statistics
        $totalCustomers = User::where('role', '!=', UserRole::SALES_AGENT)
            ->where('is_admin', false)
            ->count();

        $verifiedCustomers = User::where('role', '!=', UserRole::SALES_AGENT)
            ->where('is_admin', false)
            ->whereNotNull('email_verified_at')
            ->count();

        $unverifiedCustomers = $totalCustomers - $verifiedCustomers;

        $customersWithPhone = User::where('role', '!=', UserRole::SALES_AGENT)
            ->where('is_admin', false)
            ->whereNotNull('whatsapp_number')
            ->where('whatsapp_number', '!=', '')
            ->count();

        // Get monthly registration data
        $monthlyData = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthName = $month->format('M');

            $registrationsCount = User::where('role', '!=', UserRole::SALES_AGENT)
                ->where('is_admin', false)
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();

            $monthlyData[] = [
                'month' => $monthName,
                'registrations' => $registrationsCount,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'total_customers' => $totalCustomers,
                    'verified_customers' => $verifiedCustomers,
                    'unverified_customers' => $unverifiedCustomers,
                    'customers_with_phone' => $customersWithPhone,
                    'verification_rate' => $totalCustomers > 0 ? round(($verifiedCustomers / $totalCustomers) * 100, 2) : 0,
                    'phone_completion_rate' => $totalCustomers > 0 ? round(($customersWithPhone / $totalCustomers) * 100, 2) : 0,
                ],
                'monthly_registrations' => $monthlyData,
            ]
        ]);
    }
}