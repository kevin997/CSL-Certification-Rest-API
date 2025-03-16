<?php

namespace App\Services;

use App\Models\Referral;
use App\Models\User;
use App\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReferralService
{
    /**
     * Get all referrals
     *
     * @param array $filters
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getAllReferrals(array $filters = [])
    {
        $query = Referral::with(['referrer', 'referred']);
        
        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['referrer_id'])) {
            $query->where('referrer_id', $filters['referrer_id']);
        }
        
        if (isset($filters['referred_id'])) {
            $query->where('referred_id', $filters['referred_id']);
        }
        
        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }
        
        // Apply sorting
        $sortField = $filters['sort_field'] ?? 'created_at';
        $sortDirection = $filters['sort_direction'] ?? 'desc';
        $query->orderBy($sortField, $sortDirection);
        
        return $query->get();
    }
    
    /**
     * Get referral by ID
     *
     * @param int $id
     * @return Referral|null
     */
    public function getReferralById(int $id): ?Referral
    {
        return Referral::with(['referrer', 'referred'])->find($id);
    }
    
    /**
     * Create a new referral
     *
     * @param array $data
     * @return Referral
     */
    public function createReferral(array $data): Referral
    {
        // Generate code if not provided
        if (!isset($data['code'])) {
            $data['code'] = $this->generateReferralCode();
        }
        
        return Referral::create($data);
    }
    
    /**
     * Update a referral
     *
     * @param int $id
     * @param array $data
     * @return Referral|null
     */
    public function updateReferral(int $id, array $data): ?Referral
    {
        $referral = $this->getReferralById($id);
        
        if (!$referral) {
            return null;
        }
        
        $referral->update($data);
        
        return $referral;
    }
    
    /**
     * Delete a referral
     *
     * @param int $id
     * @return bool
     */
    public function deleteReferral(int $id): bool
    {
        $referral = $this->getReferralById($id);
        
        if (!$referral) {
            return false;
        }
        
        return $referral->delete();
    }
    
    /**
     * Generate a unique referral code
     *
     * @return string
     */
    public function generateReferralCode(): string
    {
        $code = strtoupper(Str::random(8));
        
        // Check if code already exists
        while (Referral::where('code', $code)->exists()) {
            $code = strtoupper(Str::random(8));
        }
        
        return $code;
    }
    
    /**
     * Get user's referral code
     *
     * @param int $userId
     * @return string|null
     */
    public function getUserReferralCode(int $userId): ?string
    {
        $referral = Referral::where('referrer_id', $userId)
            ->where('is_active', true)
            ->first();
        
        if (!$referral) {
            // Create a new referral code for the user
            $referral = $this->createReferral([
                'referrer_id' => $userId,
                'code' => $this->generateReferralCode(),
                'is_active' => true,
                'status' => 'active'
            ]);
        }
        
        return $referral->code;
    }
    
    /**
     * Get referral by code
     *
     * @param string $code
     * @return Referral|null
     */
    public function getReferralByCode(string $code): ?Referral
    {
        return Referral::with(['referrer'])
            ->where('code', $code)
            ->where('is_active', true)
            ->first();
    }
    
    /**
     * Apply referral
     *
     * @param string $code
     * @param int $userId
     * @return array
     */
    public function applyReferral(string $code, int $userId): array
    {
        $referral = $this->getReferralByCode($code);
        
        if (!$referral) {
            return [
                'success' => false,
                'message' => 'Invalid or inactive referral code'
            ];
        }
        
        // Check if user is trying to refer themselves
        if ($referral->referrer_id === $userId) {
            return [
                'success' => false,
                'message' => 'You cannot refer yourself'
            ];
        }
        
        // Check if user has already been referred
        $existingReferral = Referral::where('referred_id', $userId)->first();
        
        if ($existingReferral) {
            return [
                'success' => false,
                'message' => 'You have already been referred'
            ];
        }
        
        // Create a new referral record
        $newReferral = $this->createReferral([
            'referrer_id' => $referral->referrer_id,
            'referred_id' => $userId,
            'code' => $code,
            'is_active' => false,
            'status' => 'pending'
        ]);
        
        return [
            'success' => true,
            'message' => 'Referral applied successfully',
            'referral' => $newReferral
        ];
    }
    
    /**
     * Complete referral
     *
     * @param int $referralId
     * @param int $orderId
     * @return array
     */
    public function completeReferral(int $referralId, int $orderId): array
    {
        $referral = $this->getReferralById($referralId);
        
        if (!$referral) {
            return [
                'success' => false,
                'message' => 'Referral not found'
            ];
        }
        
        // Check if referral is already completed
        if ($referral->status === 'completed') {
            return [
                'success' => false,
                'message' => 'Referral is already completed'
            ];
        }
        
        // Verify order
        $order = Order::find($orderId);
        
        if (!$order) {
            return [
                'success' => false,
                'message' => 'Order not found'
            ];
        }
        
        // Check if order belongs to the referred user
        if ($order->user_id !== $referral->referred_id) {
            return [
                'success' => false,
                'message' => 'Order does not belong to the referred user'
            ];
        }
        
        // Update referral
        $referral->update([
            'status' => 'completed',
            'completed_at' => now(),
            'order_id' => $orderId
        ]);
        
        // Process rewards (in a real application, this would credit the referrer's account)
        // For this demo, we'll just return success
        
        return [
            'success' => true,
            'message' => 'Referral completed successfully',
            'referral' => $referral
        ];
    }
    
    /**
     * Get user's referrals
     *
     * @param int $userId
     * @return array
     */
    public function getUserReferrals(int $userId): array
    {
        $referrals = Referral::with(['referred'])
            ->where('referrer_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
        
        $stats = [
            'total' => $referrals->count(),
            'pending' => $referrals->where('status', 'pending')->count(),
            'completed' => $referrals->where('status', 'completed')->count(),
            'cancelled' => $referrals->where('status', 'cancelled')->count()
        ];
        
        return [
            'referrals' => $referrals,
            'stats' => $stats
        ];
    }
    
    /**
     * Calculate referral rewards
     *
     * @param int $userId
     * @return array
     */
    public function calculateReferralRewards(int $userId): array
    {
        $completedReferrals = Referral::with(['referred', 'order'])
            ->where('referrer_id', $userId)
            ->where('status', 'completed')
            ->get();
        
        $totalRewards = 0;
        $rewardsDetails = [];
        
        foreach ($completedReferrals as $referral) {
            // In a real application, this would calculate based on reward rules
            // For this demo, we'll use a simple 10% of the order total
            $orderTotal = $referral->order ? $referral->order->total : 0;
            $reward = $orderTotal * 0.1;
            
            $totalRewards += $reward;
            
            $rewardsDetails[] = [
                'referral_id' => $referral->id,
                'referred_user' => $referral->referred ? $referral->referred->name : 'Unknown',
                'order_number' => $referral->order ? $referral->order->order_number : 'N/A',
                'order_total' => $orderTotal,
                'reward_amount' => $reward,
                'completed_at' => $referral->completed_at
            ];
        }
        
        return [
            'total_rewards' => $totalRewards,
            'rewards_details' => $rewardsDetails,
            'total_completed_referrals' => count($completedReferrals)
        ];
    }
    
    /**
     * Get top referrers
     *
     * @param int $limit
     * @return array
     */
    public function getTopReferrers(int $limit = 10): array
    {
        $referrers = DB::table('referrals')
            ->select('referrer_id', DB::raw('count(*) as total_referrals'))
            ->where('status', 'completed')
            ->groupBy('referrer_id')
            ->orderBy('total_referrals', 'desc')
            ->limit($limit)
            ->get();
        
        $result = [];
        
        foreach ($referrers as $referrer) {
            $user = User::find($referrer->referrer_id);
            
            if ($user) {
                $result[] = [
                    'user_id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'total_referrals' => $referrer->total_referrals
                ];
            }
        }
        
        return $result;
    }
    
    /**
     * Get referral validation rules
     *
     * @return array
     */
    public function getValidationRules(): array
    {
        return [
            'referrer_id' => 'required|integer|exists:users,id',
            'referred_id' => 'nullable|integer|exists:users,id',
            'code' => 'nullable|string|max:20|unique:referrals,code',
            'is_active' => 'boolean',
            'status' => 'required|string|in:active,pending,completed,cancelled',
            'order_id' => 'nullable|integer|exists:orders,id',
            'completed_at' => 'nullable|date'
        ];
    }
}
