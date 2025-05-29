<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Referral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class SalesAgentController extends Controller
{
    /**
     * Display a listing of sales agents.
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

        $query = User::where('role', 'sales_agent');

        // Apply search filter if provided
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Apply status filter if provided
        if ($request->has('status')) {
            $status = $request->status === 'active' ? true : false;
            $query->where('is_active', $status);
        }

        // Get sales agents with referral counts
        $salesAgents = $query->withCount(['referrals', 'referrals as conversions_count' => function ($query) {
            $query->where('uses_count', '>', 0);
        }])->get();

        // Format the response
        $formattedAgents = $salesAgents->map(function ($agent) {
            return [
                'id' => $agent->id,
                'name' => $agent->name,
                'email' => $agent->email,
                'status' => $agent->is_active ? 'Active' : 'Inactive',
                'referrals_count' => $agent->referrals_count,
                'conversions_count' => $agent->conversions_count,
                'created_at' => $agent->created_at,
                'updated_at' => $agent->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedAgents
        ]);
    }

    /**
     * Store a newly created sales agent.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Validate admin access
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Create the sales agent
        $salesAgent = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'sales_agent',
            'is_active' => true,
        ]);

        // Format the response
        $formattedAgent = [
            'id' => $salesAgent->id,
            'name' => $salesAgent->name,
            'email' => $salesAgent->email,
            'status' => 'Active',
            'referrals_count' => 0,
            'conversions_count' => 0,
            'created_at' => $salesAgent->created_at,
            'updated_at' => $salesAgent->updated_at,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Sales agent created successfully',
            'data' => $formattedAgent
        ], 201);
    }

    /**
     * Display the specified sales agent.
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

        // Find the sales agent
        $salesAgent = User::where('id', $id)
            ->where('role', 'sales_agent')
            ->withCount(['referrals', 'referrals as conversions_count' => function ($query) {
                $query->where('uses_count', '>', 0);
            }])
            ->first();

        if (!$salesAgent) {
            return response()->json([
                'success' => false,
                'message' => 'Sales agent not found'
            ], 404);
        }

        // Format the response
        $formattedAgent = [
            'id' => $salesAgent->id,
            'name' => $salesAgent->name,
            'email' => $salesAgent->email,
            'status' => $salesAgent->is_active ? 'Active' : 'Inactive',
            'referrals_count' => $salesAgent->referrals_count,
            'conversions_count' => $salesAgent->conversions_count,
            'created_at' => $salesAgent->created_at,
            'updated_at' => $salesAgent->updated_at,
        ];

        return response()->json([
            'success' => true,
            'data' => $formattedAgent
        ]);
    }

    /**
     * Update the specified sales agent.
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

        // Find the sales agent
        $salesAgent = User::where('id', $id)
            ->where('role', 'sales_agent')
            ->first();

        if (!$salesAgent) {
            return response()->json([
                'success' => false,
                'message' => 'Sales agent not found'
            ], 404);
        }

        // Validate request
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => 'sometimes|string|email|max:255|unique:users,email,' . $id,
            'password' => 'sometimes|string|min:8',
            'status' => 'sometimes|string|in:Active,Inactive',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Update the sales agent
        if ($request->has('name')) {
            $salesAgent->name = $request->name;
        }

        if ($request->has('email')) {
            $salesAgent->email = $request->email;
        }

        if ($request->has('password')) {
            $salesAgent->password = Hash::make($request->password);
        }

        if ($request->has('status')) {
            $salesAgent->is_active = $request->status === 'Active';
        }

        $salesAgent->save();

        // Get updated counts
        $referralsCount = Referral::where('referrer_id', $salesAgent->id)->count();
        $conversionsCount = Referral::where('referrer_id', $salesAgent->id)
            ->where('uses_count', '>', 0)
            ->count();

        // Format the response
        $formattedAgent = [
            'id' => $salesAgent->id,
            'name' => $salesAgent->name,
            'email' => $salesAgent->email,
            'status' => $salesAgent->is_active ? 'Active' : 'Inactive',
            'referrals_count' => $referralsCount,
            'conversions_count' => $conversionsCount,
            'created_at' => $salesAgent->created_at,
            'updated_at' => $salesAgent->updated_at,
        ];

        return response()->json([
            'success' => true,
            'message' => 'Sales agent updated successfully',
            'data' => $formattedAgent
        ]);
    }

    /**
     * Remove the specified sales agent.
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

        // Find the sales agent
        $salesAgent = User::where('id', $id)
            ->where('role', 'sales_agent')
            ->first();

        if (!$salesAgent) {
            return response()->json([
                'success' => false,
                'message' => 'Sales agent not found'
            ], 404);
        }

        // Check if the sales agent has referrals
        $referralsCount = Referral::where('referrer_id', $salesAgent->id)->count();
        if ($referralsCount > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete sales agent with existing referrals'
            ], 400);
        }

        // Delete the sales agent
        $salesAgent->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sales agent deleted successfully'
        ]);
    }

    /**
     * Get performance metrics for a sales agent.
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getPerformance($id, Request $request)
    {
        // Validate access (admin or the sales agent themselves)
        $user = $request->user();
        if (!$user || (!$user->isAdmin() && $user->id != $id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Find the sales agent
        $salesAgent = User::where('id', $id)
            ->where('role', 'sales_agent')
            ->first();

        if (!$salesAgent) {
            return response()->json([
                'success' => false,
                'message' => 'Sales agent not found'
            ], 404);
        }

        // Get referral statistics
        $totalReferrals = Referral::where('referrer_id', $id)->count();
        $activeReferrals = Referral::where('referrer_id', $id)
            ->where('is_active', true)
            ->count();
        $totalUses = Referral::where('referrer_id', $id)
            ->sum('uses_count');
        
        // Calculate revenue (simplified for demonstration)
        $totalRevenue = Referral::where('referrer_id', $id)
            ->where('uses_count', '>', 0)
            ->sum(DB::raw('uses_count * 100')); // Assuming $100 per conversion
        
        // Calculate conversion rate
        $conversionRate = $totalReferrals > 0 
            ? ($totalUses / $totalReferrals) * 100 
            : 0;
        
        // Get monthly performance data
        $monthlyData = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthName = $month->format('M');
            
            $referralsCount = Referral::where('referrer_id', $id)
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();
                
            $conversionsCount = Referral::where('referrer_id', $id)
                ->where('uses_count', '>', 0)
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();
                
            $monthlyData[] = [
                'month' => $monthName,
                'referrals' => $referralsCount,
                'conversions' => $conversionsCount,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'agent' => [
                    'id' => $salesAgent->id,
                    'name' => $salesAgent->name,
                    'email' => $salesAgent->email,
                    'status' => $salesAgent->is_active ? 'Active' : 'Inactive',
                ],
                'stats' => [
                    'total_referrals' => $totalReferrals,
                    'active_referrals' => $activeReferrals,
                    'total_uses' => $totalUses,
                    'conversion_rate' => round($conversionRate, 2),
                    'total_revenue' => $totalRevenue,
                ],
                'monthly_performance' => $monthlyData,
            ]
        ]);
    }

    /**
     * Get referrals for a specific sales agent.
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getReferrals($id, Request $request)
    {
        // Validate access (admin or the sales agent themselves)
        $user = $request->user();
        if (!$user || (!$user->isAdmin() && $user->id != $id)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Find the sales agent
        $salesAgent = User::where('id', $id)
            ->where('role', 'sales_agent')
            ->first();

        if (!$salesAgent) {
            return response()->json([
                'success' => false,
                'message' => 'Sales agent not found'
            ], 404);
        }

        // Get referrals for the sales agent
        $referrals = Referral::where('referrer_id', $id)
            ->orderBy('created_at', 'desc')
            ->get();

        // Format the response
        $formattedReferrals = $referrals->map(function ($referral) use ($salesAgent) {
            return [
                'id' => $referral->id,
                'code' => $referral->code,
                'agent_id' => $salesAgent->id,
                'agent_name' => $salesAgent->name,
                'discount_type' => $referral->discount_type,
                'discount_value' => $referral->discount_value,
                'uses_count' => $referral->uses_count,
                'max_uses' => $referral->max_uses,
                'status' => $referral->is_active ? 'Active' : 'Inactive',
                'created_at' => $referral->created_at,
                'expires_at' => $referral->expires_at,
                'updated_at' => $referral->updated_at,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $formattedReferrals
        ]);
    }
}
