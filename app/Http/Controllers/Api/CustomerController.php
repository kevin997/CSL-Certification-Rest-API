<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CustomerController extends Controller
{
    /**
     * Get all customers for admin subscription management
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function index(Request $request)
    {
        try {
            $query = User::query()
                ->whereNotIn('role', ['admin', 'super_admin']);

            // Apply search filter
            if ($request->has('search') && $request->search !== '') {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%");
                });
            }

            // Get customers with their subscription count and environment count
            $customers = $query->select('id', 'name', 'email', 'role', 'company_name', 'created_at')
                ->withCount(['subscriptions', 'ownedEnvironments', 'environments'])
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'status' => 'success',
                'data' => $customers
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching customers: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to fetch customers'
            ], 500);
        }
    }

    /**
     * Get customer details with comprehensive environment information
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        try {
            $customer = User::with([
                'subscriptions.plan',
                'ownedEnvironments' => function ($query) {
                    $query->with([
                        'subscription.plan',
                        'users' => function ($userQuery) {
                            $userQuery->select('users.id', 'users.name', 'users.email')
                                ->withPivot('role', 'permissions');
                        }
                    ])
                        ->withCount(['courses', 'products', 'teams', 'orders', 'issuedCertificates', 'users'])
                        ->select(
                            'id',
                            'name',
                            'primary_domain',
                            'additional_domains',
                            'theme_color',
                            'logo_url',
                            'is_active',
                            'is_demo',
                            'owner_id',
                            'description',
                            'country_code',
                            'state_code',
                            'created_at',
                            'updated_at'
                        );
                },
                'environments' => function ($query) {
                    $query->with([
                        'owner:id,name,email',
                        'subscription.plan'
                    ])
                        ->withCount(['courses', 'products', 'teams', 'orders', 'issuedCertificates', 'users'])
                        ->select(
                            'environments.id',
                            'environments.name',
                            'environments.primary_domain',
                            'environments.additional_domains',
                            'environments.theme_color',
                            'environments.logo_url',
                            'environments.is_active',
                            'environments.is_demo',
                            'environments.owner_id',
                            'environments.description',
                            'environments.country_code',
                            'environments.state_code',
                            'environments.created_at',
                            'environments.updated_at'
                        )
                        ->withPivot('role', 'permissions', 'environment_email', 'use_environment_credentials');
                }
            ])
                ->findOrFail($id);

            // Add computed fields to owned environments
            $customer->ownedEnvironments->each(function ($environment) {
                $environment->environment_type = $environment->getEnvironmentType();
                $environment->has_active_subscription = $environment->hasActiveSubscription();
                $environment->is_demo_environment = $environment->isDemoEnvironment();
                $environment->all_domains = $environment->getAllDomains();
            });

            // Add computed fields to member environments
            $customer->environments->each(function ($environment) {
                $environment->environment_type = $environment->getEnvironmentType();
                $environment->has_active_subscription = $environment->hasActiveSubscription();
                $environment->is_demo_environment = $environment->isDemoEnvironment();
                $environment->all_domains = $environment->getAllDomains();
            });

            // Add summary statistics
            $environmentStats = [
                'owned_environments_count' => $customer->ownedEnvironments->count(),
                'member_environments_count' => $customer->environments->count(),
                'total_environments_count' => $customer->ownedEnvironments->count() + $customer->environments->count(),
                'active_owned_environments' => $customer->ownedEnvironments->where('is_active', true)->count(),
                'demo_owned_environments' => $customer->ownedEnvironments->where('is_demo', true)->count(),
                'environments_with_active_subscriptions' => $customer->ownedEnvironments->filter(function ($env) {
                    return $env->hasActiveSubscription();
                })->count()
            ];

            $customer->environment_stats = $environmentStats;

            return response()->json([
                'status' => 'success',
                'data' => $customer
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error fetching customer: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Customer not found'
            ], 404);
        }
    }

    /**
     * Update customer information
     *
     * @param Request $request
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request, $id)
    {
        try {
            $customer = User::findOrFail($id);

            // Validate request data
            $request->validate([
                'name' => 'sometimes|string|max:255',
                'email' => 'sometimes|email|unique:users,email,' . $id,
                'role' => 'sometimes|in:learner,individual_teacher,company_teacher',
                'company_name' => 'sometimes|nullable|string|max:255',
            ]);

            // Update customer data
            $customer->update($request->only(['name', 'email', 'role', 'company_name']));

            return response()->json([
                'status' => 'success',
                'data' => $customer->fresh()
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Customer not found: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Customer not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error updating customer: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update customer'
            ], 500);
        }
    }

    /**
     * Create a new customer
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        try {
            // Validate request data
            $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:users,email',
                'password' => 'required|string|min:8',
                'role' => 'required|in:learner,individual_teacher,company_teacher',
                'company_name' => 'nullable|string|max:255',
            ]);

            // Create customer
            $customer = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => bcrypt($request->password),
                'role' => $request->role,
                'company_name' => $request->company_name,
            ]);

            return response()->json([
                'status' => 'success',
                'data' => $customer
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error creating customer: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to create customer'
            ], 500);
        }
    }

    /**
     * Delete a customer
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy($id)
    {
        try {
            $customer = User::findOrFail($id);
            $customer->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Customer deleted successfully'
            ], 200);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            Log::error('Customer not found: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Customer not found'
            ], 404);
        } catch (\Exception $e) {
            Log::error('Error deleting customer: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to delete customer'
            ], 500);
        }
    }
}
