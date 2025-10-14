<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnvironmentReferral;
use App\Models\Environment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Http\Response;

class ReferralEnvironmentController extends Controller
{
    /**
     * Display a listing of referrals for the current environment.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        // Get the environment ID from the authenticated user
        $environmentId = session('current_environment_id');
        
        if (!$environmentId) {
            return response()->json([
                'success' => false,
                'message' => 'No environment selected'
            ], 400);
        }
        
        // Build query with filters
        $query = EnvironmentReferral::where('environment_id', $environmentId);
        
        // Apply search filter if provided
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%");
            });
        }
        
        // Apply status filter if provided
        if ($request->has('status')) {
            $status = $request->status === 'active';
            $query->where('is_active', $status);
        }
        
        // Apply referrer filter if provided
        if ($request->has('referrer_id')) {
            $query->where('referrer_id', $request->referrer_id);
        }
        
        // Apply discount type filter if provided
        if ($request->has('discount_type')) {
            $query->where('discount_type', $request->discount_type);
        }
        
        // Apply min discount filter if provided
        if ($request->has('min_discount')) {
            $query->where('discount_value', '>=', $request->min_discount);
        }
        
        // Apply max discount filter if provided
        if ($request->has('max_discount')) {
            $query->where('discount_value', '<=', $request->max_discount);
        }
        
        // Apply sorting
        $sortField = $request->input('sort_field', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $allowedSortFields = ['created_at', 'code', 'discount_value', 'uses_count'];
        
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }
        
        // Get paginated results
        $perPage = $request->input('per_page', 10);
        $referrals = $query->with('referrer')->paginate($perPage);
        
        return response()->json([
            'success' => true,
            'data' => $referrals
        ]);
    }

    /**
     * Display a listing of the current user's referrals.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function myReferrals(Request $request)
    {
        // Get the environment ID from the authenticated user
        $environmentId = session('current_environment_id');
        
        if (!$environmentId) {
            return response()->json([
                'success' => false,
                'message' => 'No environment selected'
            ], 400);
        }
        
        $referrals = EnvironmentReferral::where('environment_id', $environmentId)
            ->where('referrer_id', $request->user()->id)
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'success' => true,
            'data' => $referrals
        ]);
    }

    /**
     * Store a newly created referral.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        // Get the environment ID from the authenticated user
        $environmentId = session('current_environment_id');
        
        if (!$environmentId) {
            return response()->json([
                'success' => false,
                'message' => 'No environment selected'
            ], 400);
        }
        
        // Validate request
        $validator = Validator::make($request->all(), [
            'referrer_id' => 'sometimes|exists:users,id',
            'code' => 'sometimes|string|unique:environment_referrals,code',
            'discount_type' => 'required|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date|after:now',
            'is_active' => 'sometimes|boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Set referrer ID to current user if not provided
        $referrerId = $request->input('referrer_id', $request->user()->id);
        
        // Generate code if not provided
        $code = $request->input('code', $this->generateReferralCode());
        
        // Create the referral
        $referral = EnvironmentReferral::create([
            'environment_id' => $environmentId,
            'referrer_id' => $referrerId,
            'code' => $code,
            'discount_type' => $request->discount_type,
            'discount_value' => $request->discount_value,
            'max_uses' => $request->max_uses,
            'expires_at' => $request->expires_at,
            'is_active' => $request->input('is_active', true),
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Referral created successfully',
            'data' => $referral
        ], 201);
    }

    /**
     * Display the specified referral.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $referral = EnvironmentReferral::with('referrer')->find($id);
        
        if (!$referral) {
            return response()->json([
                'success' => false,
                'message' => 'Referral not found'
            ], 404);
        }
        
        // Check if user has access to this referral's environment
        $user = Auth::user();
        $environmentId = $user->current_environment_id;
        
        if ($referral->environment_id != $environmentId) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this referral'
            ], 403);
        }
        
        return response()->json([
            'success' => true,
            'data' => $referral
        ]);
    }

    /**
     * Update the specified referral.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $referral = EnvironmentReferral::find($id);
        
        if (!$referral) {
            return response()->json([
                'success' => false,
                'message' => 'Referral not found'
            ], 404);
        }
        
        // Check if user has access to this referral's environment
        $user = Auth::user();
        $environmentId = $user->current_environment_id;
        
        if ($referral->environment_id != $environmentId) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this referral'
            ], 403);
        }
        
        // Validate request
        $validator = Validator::make($request->all(), [
            'code' => 'sometimes|string|unique:environment_referrals,code,' . $id,
            'discount_type' => 'sometimes|in:percentage,fixed',
            'discount_value' => 'sometimes|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date|after:now',
            'is_active' => 'sometimes|boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        // Update the referral
        $referral->update($request->only([
            'code',
            'discount_type',
            'discount_value',
            'max_uses',
            'expires_at',
            'is_active',
        ]));
        
        return response()->json([
            'success' => true,
            'message' => 'Referral updated successfully',
            'data' => $referral
        ]);
    }

    /**
     * Remove the specified referral.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $referral = EnvironmentReferral::find($id);
        
        if (!$referral) {
            return response()->json([
                'success' => false,
                'message' => 'Referral not found'
            ], 404);
        }
        
        // Check if user has access to this referral's environment
        $user = Auth::user();
        $environmentId = $user->current_environment_id;
        
        if ($referral->environment_id != $environmentId) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have access to this referral'
            ], 403);
        }
        
        // Check if referral has been used
        if ($referral->uses_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a referral that has been used'
            ], 400);
        }
        
        $referral->delete();
        
        return response()->json([
            'success' => true,
            'message' => 'Referral deleted successfully'
        ]);
    }

    /**
     * Validate a referral code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function validate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'environment_id' => 'required|exists:environments,id',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 422);
        }
        
        $environmentId = $request->environment_id;
        $referral = EnvironmentReferral::where('code', $request->code)
            ->where('environment_id', $environmentId)
            ->first();
        
        if (!$referral) {
            return response()->json([
                'success' => false,
                'valid' => false,
                'message' => 'Invalid referral code for this environment'
            ]);
        }
        
        // Check if referral is valid
        $isValid = true;
        $message = 'Referral code is valid';
        
        // Check if referral is active
        if (!$referral->is_active) {
            $isValid = false;
            $message = 'Referral code is inactive';
        }
        
        // Check if referral has expired
        if ($referral->expires_at && $referral->expires_at < now()) {
            $isValid = false;
            $message = 'Referral code has expired';
        }
        
        // Check if referral has reached max uses
        if ($referral->max_uses && $referral->uses_count >= $referral->max_uses) {
            $isValid = false;
            $message = 'Referral code has reached maximum uses';
        }
        
        return response()->json([
            'success' => true,
            'valid' => $isValid,
            'message' => $message,
            'data' => $isValid ? [
                'referral_id' => $referral->id,
                'discount_type' => $referral->discount_type,
                'discount_value' => $referral->discount_value,
                'referrer' => [
                    'id' => $referral->referrer->id,
                    'name' => $referral->referrer->name,
                ]
            ] : null,
        ]);
    }

    /**
     * Get referral statistics.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function getStats(Request $request)
    {
        // Get the environment ID from the authenticated user
        $environmentId = session('current_environment_id');
        
        if (!$environmentId) {
            return response()->json([
                'success' => false,
                'message' => 'No environment selected'
            ], 400);
        }
        
        // Base query - filter by environment
        $query = EnvironmentReferral::where('environment_id', $environmentId);
        
        // Filter by referrer if not admin
        $user = $request->user();
        if (!$user->isTeacher()) {
            $query->where('referrer_id', $user->id);
        }
        
        // Get total referrals count
        $totalReferrals = $query->count();
        
        // Get active referrals count
        $activeReferrals = (clone $query)
            ->where('is_active', true)
            ->where(function($q) {
                $q->whereNull('expires_at')
                  ->orWhere('expires_at', '>', now());
            })
            ->count();
        
        // Get total uses count
        $totalUses = (clone $query)->sum('uses_count');
        
        // Get total conversions (referrals with at least one use)
        $totalConversions = (clone $query)
            ->where('uses_count', '>', 0)
            ->count();
        
        // Calculate conversion rate
        $conversionRate = $totalReferrals > 0 
            ? ($totalConversions / $totalReferrals) * 100 
            : 0;
        
        // Calculate total revenue (simplified for demonstration)
        // In a real application, you would calculate this from actual order data
        $totalRevenue = (clone $query)
            ->where('uses_count', '>', 0)
            ->sum('uses_count') * 100; // Assuming $100 per conversion
        
        // Calculate total discount amount (simplified for demonstration)
        // In a real application, you would calculate this from actual order data
        $totalDiscountAmount = (clone $query)
            ->where('uses_count', '>', 0)
            ->get()
            ->sum(function($referral) {
                if ($referral->discount_type === 'percentage') {
                    return ($referral->discount_value / 100) * $referral->uses_count * 100;
                } else {
                    return $referral->discount_value * $referral->uses_count;
                }
            });
        
        return response()->json([
            'success' => true,
            'data' => [
                'total_referrals' => $totalReferrals,
                'active_referrals' => $activeReferrals,
                'total_uses' => $totalUses,
                'total_conversions' => $totalConversions,
                'conversion_rate' => round($conversionRate, 2),
                'total_revenue' => $totalRevenue,
                'total_discount_amount' => $totalDiscountAmount,
            ]
        ]);
    }

    /**
     * Generate a unique referral code.
     *
     * @return string
     */
    private function generateReferralCode()
    {
        $user = Auth::user();
        $prefix = strtoupper(substr($user->name, 0, 3));
        $random = strtoupper(Str::random(5));
        $code = $prefix . '-' . $random;
        
        // Ensure code is unique
        while (EnvironmentReferral::where('code', $code)->exists()) {
            $random = strtoupper(Str::random(5));
            $code = $prefix . '-' . $random;
        }
        
        return $code;
    }
}
