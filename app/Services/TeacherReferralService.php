<?php

namespace App\Services;

use App\Models\Environment;
use App\Models\LicenceCheckout;
use App\Models\TeacherReferral;
use App\Models\TeacherReferralCode;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;

class TeacherReferralService
{
    public function codeFor(User $teacher): TeacherReferralCode
    {
        return TeacherReferralCode::firstOrCreate(
            ['referrer_id' => $teacher->id],
            [
                'code' => 'KURSA'.strtoupper(Str::random(10)),
                'reward_type' => config('licensing.teacher_referrals.reward_type', 'percentage'),
                'reward_value' => config('licensing.teacher_referrals.reward_value', 10),
            ]
        );
    }

    public function validCode(?string $value): ?TeacherReferralCode
    {
        $code = strtoupper(trim((string) $value));

        return $code === '' ? null : TeacherReferralCode::where('code', $code)
            ->where('is_active', true)->first();
    }

    public function attribute(Environment $environment, User $referredUser, ?string $value): void
    {
        $code = TeacherReferralCode::where('code', strtoupper(trim((string) $value)))->first();
        if (! $code || $code->referrer_id === $referredUser->id) {
            return;
        }

        TeacherReferral::firstOrCreate(
            ['referred_environment_id' => $environment->id],
            [
                'teacher_referral_code_id' => $code->id,
                'referrer_id' => $code->referrer_id,
                'referred_user_id' => $referredUser->id,
                'status' => 'pending',
                'reward_type' => $code->reward_type,
                'reward_value' => $code->reward_value,
            ]
        );
    }

    public function qualify(Environment $environment, LicenceCheckout $checkout, Transaction $transaction): void
    {
        $referral = TeacherReferral::where('referred_environment_id', $environment->id)
            ->lockForUpdate()->first();

        if (! $referral || $referral->status !== 'pending') {
            return;
        }

        $alreadyPaid = LicenceCheckout::where('environment_id', $environment->id)
            ->where('status', LicenceCheckout::STATUS_PAID)
            ->whereKeyNot($checkout->id)->exists();
        if ($alreadyPaid) {
            return;
        }

        $baseAmount = max(0, (float) $checkout->quoted_amount);
        $rewardValue = max(0, (float) $referral->reward_value);
        $reward = $referral->reward_type === 'percentage'
            ? $baseAmount * min(100, $rewardValue) / 100
            : min($baseAmount, $rewardValue);

        $referral->update([
            'qualifying_checkout_id' => $checkout->id,
            'qualifying_transaction_id' => $transaction->id,
            'status' => 'earned',
            'reward_amount' => round($reward, 2),
            'currency' => $checkout->quoted_currency,
            'qualified_at' => now(),
        ]);
    }

    public function reverseForFullRefund(Transaction $transaction): void
    {
        TeacherReferral::where('qualifying_transaction_id', $transaction->id)
            ->where('status', 'earned')
            ->update(['status' => 'reversed', 'reversed_at' => now()]);
    }
}
