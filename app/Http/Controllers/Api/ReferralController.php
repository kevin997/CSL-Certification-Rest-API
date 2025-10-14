<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Referral;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Http\Response;

/**
 * @OA\Schema(
 *     schema="Referral",
 *     required={"referrer_id", "code", "discount_type", "discount_value", "is_active"},
 *     @OA\Property(property="id", type="integer", format="int64", example=1),
 *     @OA\Property(property="referrer_id", type="integer", format="int64", example=1),
 *     @OA\Property(property="code", type="string", example="WELCOME10"),
 *     @OA\Property(property="discount_type", type="string", enum={"percentage", "fixed"}, example="percentage"),
 *     @OA\Property(property="discount_value", type="number", format="float", example=10),
 *     @OA\Property(property="max_uses", type="integer", example=100, nullable=true),
 *     @OA\Property(property="uses_count", type="integer", example=5),
 *     @OA\Property(property="expires_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(
 *         property="referrer",
 *         ref="#/components/schemas/User"
 *     )
 * )
 */
class ReferralController extends Controller
{
    /**
     * Display a listing of referrals.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/referrals",
     *     summary="Get list of referrals",
     *     description="Returns paginated list of referrals with optional filtering",
     *     operationId="getReferralsList",
     *     tags={"Referrals"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="search",
     *         in="query",
     *         description="Search term for referral code",
     *         required=false,
     *         @OA\Schema(type="string")
     *     ),
     *     @OA\Parameter(
     *         name="is_active",
     *         in="query",
     *         description="Filter by active status",
     *         required=false,
     *         @OA\Schema(type="boolean")
     *     ),
     *     @OA\Parameter(
     *         name="referrer_id",
     *         in="query",
     *         description="Filter by referrer user ID",
     *         required=false,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Parameter(
     *         name="discount_type",
     *         in="query",
     *         description="Filter by discount type",
     *         required=false,
     *         @OA\Schema(type="string", enum={"percentage", "fixed"})
     *     ),
     *     @OA\Parameter(
     *         name="min_discount",
     *         in="query",
     *         description="Minimum discount value filter",
     *         required=false,
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Parameter(
     *         name="max_discount",
     *         in="query",
     *         description="Maximum discount value filter",
     *         required=false,
     *         @OA\Schema(type="number", format="float")
     *     ),
     *     @OA\Parameter(
     *         name="sort_field",
     *         in="query",
     *         description="Field to sort by",
     *         required=false,
     *         @OA\Schema(type="string", enum={"created_at", "code", "discount_value", "uses_count"}, default="created_at")
     *     ),
     *     @OA\Parameter(
     *         name="sort_direction",
     *         in="query",
     *         description="Sort direction",
     *         required=false,
     *         @OA\Schema(type="string", enum={"asc", "desc"}, default="desc")
     *     ),
     *     @OA\Parameter(
     *         name="per_page",
     *         in="query",
     *         description="Number of items per page",
     *         required=false,
     *         @OA\Schema(type="integer", default=15)
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="current_page", type="integer", example=1),
     *                 @OA\Property(
     *                     property="data",
     *                     type="array",
     *                     @OA\Items(ref="#/components/schemas/Referral")
     *                 ),
     *                 @OA\Property(property="total", type="integer", example=50),
     *                 @OA\Property(property="per_page", type="integer", example=15)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission"
     *     )
     * )
     */
    public function index(Request $request)
    {
        // Check if user is admin or viewing their own referrals
        if (!Auth::user()->isTeacher()) {
            return $this->myReferrals($request);
        }

        $query = Referral::with(['referrer', 'orders']);
        
        // Apply filters
        if ($request->has('search')) {
            $search = $request->search;
            $query->where('code', 'like', "%{$search}%");
        }
        
        if ($request->has('referrer_id')) {
            $query->where('referrer_id', $request->referrer_id);
        }
        
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }
        
        if ($request->has('discount_type')) {
            $query->where('discount_type', $request->discount_type);
        }
        
        // Apply sorting
        $sortField = $request->input('sort_field', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $allowedSortFields = ['code', 'discount_value', 'uses_count', 'expires_at', 'created_at'];
        
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }
        
        // Pagination
        $perPage = $request->input('per_page', 15);
        $referrals = $query->paginate($perPage);
        
        return response()->json([
            'status' => 'success',
            'data' => $referrals,
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
        $query = Referral::with(['orders'])
            ->where('referrer_id', Auth::id());
        
        // Apply filters
        if ($request->has('is_active')) {
            $query->where('is_active', $request->is_active);
        }
        
        // Apply sorting
        $sortField = $request->input('sort_field', 'created_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $allowedSortFields = ['code', 'discount_value', 'uses_count', 'expires_at', 'created_at'];
        
        if (in_array($sortField, $allowedSortFields)) {
            $query->orderBy($sortField, $sortDirection === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }
        
        // Pagination
        $perPage = $request->input('per_page', 15);
        $referrals = $query->paginate($perPage);
        
        return response()->json([
            'status' => 'success',
            'data' => $referrals,
        ]);
    }

    /**
     * Store a newly created referral.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/referrals",
     *     summary="Create a new referral",
     *     description="Creates a new referral code with the provided data",
     *     operationId="storeReferral",
     *     tags={"Referrals"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Referral data",
     *         @OA\JsonContent(
     *             required={"discount_type", "discount_value"},
     *             @OA\Property(property="code", type="string", example="WELCOME10", description="Optional. If not provided, a random code will be generated"),
     *             @OA\Property(property="discount_type", type="string", enum={"percentage", "fixed"}, example="percentage"),
     *             @OA\Property(property="discount_value", type="number", format="float", example=10),
     *             @OA\Property(property="max_uses", type="integer", example=100, nullable=true),
     *             @OA\Property(property="expires_at", type="string", format="date-time", example="2025-12-31T23:59:59Z", nullable=true),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Referral created successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Referral created successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Referral")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid input data"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'discount_type' => 'required|string|in:percentage,fixed',
            'discount_value' => 'required|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date|after:now',
            'code' => 'nullable|string|unique:referrals,code',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Create referral
        $referral = new Referral();
        $referral->referrer_id = Auth::id();
        $referral->code = $request->code ?? $this->generateReferralCode();
        $referral->discount_type = $request->discount_type;
        $referral->discount_value = $request->discount_value;
        $referral->max_uses = $request->max_uses;
        $referral->uses_count = 0;
        $referral->expires_at = $request->expires_at;
        $referral->is_active = true;
        $referral->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Referral created successfully',
            'data' => $referral,
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified referral.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/referrals/{id}",
     *     summary="Get referral details",
     *     description="Returns details of a specific referral",
     *     operationId="getReferralById",
     *     tags={"Referrals"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Referral ID",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="data", ref="#/components/schemas/Referral")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Referral not found"
     *     )
     * )
     */
    public function show($id)
    {
        $referral = Referral::with(['referrer', 'orders'])->findOrFail($id);

        // Check if user has permission to view this referral
        if ($referral->referrer_id !== Auth::id() && !Auth::user()->isTeacher()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to view this referral',
            ], Response::HTTP_FORBIDDEN);
        }

        return response()->json([
            'status' => 'success',
            'data' => $referral,
        ]);
    }

    /**
     * Update the specified referral.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * 
     * @OA\Put(
     *     path="/referrals/{id}",
     *     summary="Update referral",
     *     description="Updates an existing referral with the provided data",
     *     operationId="updateReferral",
     *     tags={"Referrals"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Referral ID",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         description="Referral data",
     *         @OA\JsonContent(
     *             @OA\Property(property="code", type="string", example="SUMMER25"),
     *             @OA\Property(property="discount_type", type="string", enum={"percentage", "fixed"}, example="percentage"),
     *             @OA\Property(property="discount_value", type="number", format="float", example=25),
     *             @OA\Property(property="max_uses", type="integer", example=200, nullable=true),
     *             @OA\Property(property="expires_at", type="string", format="date-time", example="2025-12-31T23:59:59Z", nullable=true),
     *             @OA\Property(property="is_active", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Referral updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Referral updated successfully"),
     *             @OA\Property(property="data", ref="#/components/schemas/Referral")
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Referral not found"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update(Request $request, $id)
    {
        $referral = Referral::findOrFail($id);

        // Check if user has permission to update this referral
        if ($referral->referrer_id !== Auth::id() && !Auth::user()->isTeacher()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to update this referral',
            ], Response::HTTP_FORBIDDEN);
        }

        $validator = Validator::make($request->all(), [
            'discount_type' => 'sometimes|required|string|in:percentage,fixed',
            'discount_value' => 'sometimes|required|numeric|min:0',
            'max_uses' => 'nullable|integer|min:1',
            'expires_at' => 'nullable|date|after:now',
            'is_active' => 'sometimes|boolean',
            'code' => 'nullable|string|unique:referrals,code,' . $id,
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Update referral fields
        if ($request->has('discount_type')) {
            $referral->discount_type = $request->discount_type;
        }
        
        if ($request->has('discount_value')) {
            $referral->discount_value = $request->discount_value;
        }
        
        if ($request->has('max_uses')) {
            $referral->max_uses = $request->max_uses;
        }
        
        if ($request->has('expires_at')) {
            $referral->expires_at = $request->expires_at;
        }
        
        if ($request->has('is_active')) {
            $referral->is_active = $request->is_active;
        }
        
        if ($request->has('code')) {
            $referral->code = $request->code;
        }
        
        $referral->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Referral updated successfully',
            'data' => $referral,
        ]);
    }

    /**
     * Remove the specified referral.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     * 
     * @OA\Delete(
     *     path="/referrals/{id}",
     *     summary="Delete referral",
     *     description="Deletes a referral if it has no associated orders",
     *     operationId="deleteReferral",
     *     tags={"Referrals"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         description="Referral ID",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Referral deleted successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Referral deleted successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Cannot delete referral with existing orders"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission"
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Referral not found"
     *     )
     * )
     */
    public function destroy($id)
    {
        $referral = Referral::findOrFail($id);

        // Check if user has permission to delete this referral
        if ($referral->referrer_id !== Auth::id() && !Auth::user()->isTeacher()) {
            return response()->json([
                'status' => 'error',
                'message' => 'You do not have permission to delete this referral',
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if referral has been used
        if ($referral->uses_count > 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'Cannot delete a referral that has been used',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Delete referral
        $referral->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Referral deleted successfully',
        ]);
    }

    /**
     * Validate a referral code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * 
     * @OA\Post(
     *     path="/referrals/validate",
     *     summary="Validate referral code",
     *     description="Validates a referral code and returns discount information if valid",
     *     operationId="validateReferralCode",
     *     tags={"Referrals"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         description="Referral code to validate",
     *         @OA\JsonContent(
     *             required={"code"},
     *             @OA\Property(property="code", type="string", example="WELCOME10")
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Referral code validation result",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="valid", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="discount_type", type="string", example="percentage"),
     *                 @OA\Property(property="discount_value", type="number", format="float", example=10)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Invalid referral code"
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
    public function validate(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|exists:referrals,code',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid referral code',
                'valid' => false,
            ], Response::HTTP_OK); // Return 200 even for invalid codes
        }

        $referral = Referral::where('code', $request->code)->first();

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
            'status' => 'success',
            'message' => $message,
            'valid' => $isValid,
            'data' => $isValid ? [
                'discount_type' => $referral->discount_type,
                'discount_value' => $referral->discount_value,
            ] : null,
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
        while (Referral::where('code', $code)->exists()) {
            $random = strtoupper(Str::random(5));
            $code = $prefix . '-' . $random;
        }
        
        return $code;
    }
    
    /**
     * Get referral statistics.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/referrals/stats",
     *     summary="Get referral statistics",
     *     description="Returns statistics about referrals including counts, conversion rates, and revenue",
     *     operationId="getReferralStats",
     *     tags={"Referrals"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="total_referrals", type="integer", example=100),
     *                 @OA\Property(property="active_referrals", type="integer", example=75),
     *                 @OA\Property(property="total_uses", type="integer", example=250),
     *                 @OA\Property(property="total_conversions", type="integer", example=50),
     *                 @OA\Property(property="conversion_rate", type="number", format="float", example=20.5),
     *                 @OA\Property(property="total_revenue", type="number", format="float", example=5000),
     *                 @OA\Property(property="total_discount_amount", type="number", format="float", example=750)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Forbidden - User does not have permission"
     *     )
     * )
     */
    public function getStats(Request $request)
    {
        $user = $request->user();
        
        // Base query - filter by user if not admin
        $query = Referral::query();
        
        if (!$user->isTeacher() && $user->isSalesAgent()) {
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
}
