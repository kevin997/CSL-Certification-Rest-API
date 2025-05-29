<?php

namespace App\Listeners;

use App\Events\OrderCompletedWithReferral;
use App\Models\EnvironmentReferral;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProcessReferralUsage implements ShouldQueue
{
    use InteractsWithQueue;
    
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        // Constructor
    }

    /**
     * Handle the event.
     *
     * @param \App\Events\OrderCompletedWithReferral $event
     * @return void
     */
    public function handle(OrderCompletedWithReferral $event): void
    {
        $order = $event->order;
        $referral = $event->referral;
        
        try {
            DB::beginTransaction();
            
            // Increment the uses count for the referral
            $referral->uses_count = $referral->uses_count + 1;
            
            // Check if the referral has reached its maximum uses
            if ($referral->max_uses > 0 && $referral->uses_count >= $referral->max_uses) {
                $referral->is_active = false;
            }
            
            $referral->save();
            
            // Log the referral usage
            Log::info('Referral code used', [
                'referral_id' => $referral->id,
                'referral_code' => $referral->code,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'user_id' => $order->user_id,
                'amount' => $order->total_amount,
                'discount_type' => $referral->discount_type,
                'discount_value' => $referral->discount_value
            ]);
            
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to process referral usage', [
                'referral_id' => $referral->id,
                'order_id' => $order->id,
                'error' => $e->getMessage()
            ]);
            
            // Release the job for retry if needed
            $this->release(30);
        }
    }
}
