<?php

namespace App\Http\Controllers\Api\Sales;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Referral;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SalesDashboardController extends Controller
{
    /**
     * Get admin dashboard statistics
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getAdminStats(Request $request)
    {
        // Validate admin access
        if (!$request->user() || !$request->user()->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Get total sales agents
        $totalAgents = User::where('role', 'sales_agent')->count();
        
        // Get active sales agents
        $activeAgents = User::where('role', 'sales_agent')
            ->where('is_active', true)
            ->count();
        
        // Get total referrals
        $totalReferrals = Referral::count();
        
        // Get active referrals
        $activeReferrals = Referral::where('is_active', true)
            ->where(function($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->count();
        
        // Get total conversions
        $totalConversions = Referral::where('uses_count', '>', 0)->count();
        
        // Calculate conversion rate
        $conversionRate = $totalReferrals > 0 
            ? ($totalConversions / $totalReferrals) * 100 
            : 0;
        
        // Get total revenue (simplified for demonstration)
        $totalRevenue = Referral::where('uses_count', '>', 0)
            ->sum(DB::raw('uses_count * 100')); // Assuming $100 per conversion
        
        // Get monthly performance data
        $monthlyData = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthName = $month->format('M');
            
            $referralsCount = Referral::whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();
                
            $conversionsCount = Referral::where('uses_count', '>', 0)
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();
                
            $monthlyData[] = [
                'month' => $monthName,
                'referrals' => $referralsCount,
                'conversions' => $conversionsCount,
            ];
        }
        
        // Get top performing sales agents
        $topAgents = User::where('role', 'sales_agent')
            ->withCount(['referrals', 'referrals as conversions_count' => function ($query) {
                $query->where('uses_count', '>', 0);
            }])
            ->orderBy('conversions_count', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($agent) {
                return [
                    'id' => $agent->id,
                    'name' => $agent->name,
                    'referrals' => $agent->referrals_count,
                    'conversions' => $agent->conversions_count,
                    'conversion_rate' => $agent->referrals_count > 0 
                        ? round(($agent->conversions_count / $agent->referrals_count) * 100, 2) 
                        : 0,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => [
                    'total_agents' => $totalAgents,
                    'active_agents' => $activeAgents,
                    'total_referrals' => $totalReferrals,
                    'active_referrals' => $activeReferrals,
                    'total_conversions' => $totalConversions,
                    'conversion_rate' => round($conversionRate, 2),
                    'total_revenue' => $totalRevenue,
                ],
                'monthly_performance' => $monthlyData,
                'top_agents' => $topAgents,
            ]
        ]);
    }

    /**
     * Get sales agent dashboard statistics
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getAgentStats(Request $request)
    {
        // Validate sales agent access
        $user = $request->user();
        if (!$user || $user->role !== 'sales_agent') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        $agentId = $user->id;

        // Get referral statistics
        $totalReferrals = Referral::where('referrer_id', $agentId)->count();
        $activeReferrals = Referral::where('referrer_id', $agentId)
            ->where('is_active', true)
            ->where(function($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->count();
        
        // Get conversion statistics
        $totalConversions = Referral::where('referrer_id', $agentId)
            ->where('uses_count', '>', 0)
            ->count();
        
        // Calculate conversion rate
        $conversionRate = $totalReferrals > 0 
            ? ($totalConversions / $totalReferrals) * 100 
            : 0;
        
        // Get total revenue (simplified for demonstration)
        $totalRevenue = Referral::where('referrer_id', $agentId)
            ->where('uses_count', '>', 0)
            ->sum(DB::raw('uses_count * 100')); // Assuming $100 per conversion
        
        // Get monthly performance data
        $monthlyData = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = now()->subMonths($i);
            $monthName = $month->format('M');
            
            $referralsCount = Referral::where('referrer_id', $agentId)
                ->whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->count();
                
            $conversionsCount = Referral::where('referrer_id', $agentId)
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
        
        // Get recent referrals
        $recentReferrals = Referral::where('referrer_id', $agentId)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get()
            ->map(function ($referral) {
                return [
                    'id' => $referral->id,
                    'code' => $referral->code,
                    'discount_type' => $referral->discount_type,
                    'discount_value' => $referral->discount_value,
                    'uses_count' => $referral->uses_count,
                    'status' => $referral->is_active ? 'Active' : 'Inactive',
                    'created_at' => $referral->created_at,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'agent' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'status' => $user->is_active ? 'Active' : 'Inactive',
                ],
                'stats' => [
                    'total_referrals' => $totalReferrals,
                    'active_referrals' => $activeReferrals,
                    'total_conversions' => $totalConversions,
                    'conversion_rate' => round($conversionRate, 2),
                    'total_revenue' => $totalRevenue,
                ],
                'monthly_performance' => $monthlyData,
                'recent_referrals' => $recentReferrals,
            ]
        ]);
    }

    /**
     * Get sales performance by time period
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getPerformanceByPeriod(Request $request)
    {
        // Validate user access
        $user = $request->user();
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Validate request
        $period = $request->input('period', 'monthly');
        $validPeriods = ['daily', 'weekly', 'monthly', 'yearly'];
        
        if (!in_array($period, $validPeriods)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid period. Valid options are: ' . implode(', ', $validPeriods)
            ], 400);
        }

        // Set up query based on user role
        $query = Referral::query();
        
        if ($user->role === 'sales_agent') {
            $query->where('referrer_id', $user->id);
        } elseif (!$user->isAdmin()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access'
            ], 403);
        }

        // Get performance data based on period
        $performanceData = [];
        
        switch ($period) {
            case 'daily':
                // Last 7 days
                for ($i = 6; $i >= 0; $i--) {
                    $date = Carbon::now()->subDays($i);
                    $label = $date->format('D');
                    
                    $referralsCount = (clone $query)
                        ->whereDate('created_at', $date)
                        ->count();
                        
                    $conversionsCount = (clone $query)
                        ->where('uses_count', '>', 0)
                        ->whereDate('created_at', $date)
                        ->count();
                        
                    $performanceData[] = [
                        'label' => $label,
                        'referrals' => $referralsCount,
                        'conversions' => $conversionsCount,
                    ];
                }
                break;
                
            case 'weekly':
                // Last 8 weeks
                for ($i = 7; $i >= 0; $i--) {
                    $startOfWeek = Carbon::now()->subWeeks($i)->startOfWeek();
                    $endOfWeek = Carbon::now()->subWeeks($i)->endOfWeek();
                    $label = 'W' . $startOfWeek->format('W');
                    
                    $referralsCount = (clone $query)
                        ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
                        ->count();
                        
                    $conversionsCount = (clone $query)
                        ->where('uses_count', '>', 0)
                        ->whereBetween('created_at', [$startOfWeek, $endOfWeek])
                        ->count();
                        
                    $performanceData[] = [
                        'label' => $label,
                        'referrals' => $referralsCount,
                        'conversions' => $conversionsCount,
                    ];
                }
                break;
                
            case 'monthly':
                // Last 6 months
                for ($i = 5; $i >= 0; $i--) {
                    $month = Carbon::now()->subMonths($i);
                    $label = $month->format('M');
                    
                    $referralsCount = (clone $query)
                        ->whereYear('created_at', $month->year)
                        ->whereMonth('created_at', $month->month)
                        ->count();
                        
                    $conversionsCount = (clone $query)
                        ->where('uses_count', '>', 0)
                        ->whereYear('created_at', $month->year)
                        ->whereMonth('created_at', $month->month)
                        ->count();
                        
                    $performanceData[] = [
                        'label' => $label,
                        'referrals' => $referralsCount,
                        'conversions' => $conversionsCount,
                    ];
                }
                break;
                
            case 'yearly':
                // Last 5 years
                for ($i = 4; $i >= 0; $i--) {
                    $year = Carbon::now()->subYears($i);
                    $label = $year->format('Y');
                    
                    $referralsCount = (clone $query)
                        ->whereYear('created_at', $year->year)
                        ->count();
                        
                    $conversionsCount = (clone $query)
                        ->where('uses_count', '>', 0)
                        ->whereYear('created_at', $year->year)
                        ->count();
                        
                    $performanceData[] = [
                        'label' => $label,
                        'referrals' => $referralsCount,
                        'conversions' => $conversionsCount,
                    ];
                }
                break;
        }

        return response()->json([
            'success' => true,
            'data' => [
                'period' => $period,
                'performance' => $performanceData,
            ]
        ]);
    }
}
