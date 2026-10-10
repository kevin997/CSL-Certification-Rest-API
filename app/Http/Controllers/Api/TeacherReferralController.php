<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TeacherReferral;
use App\Services\TeacherReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeacherReferralController extends Controller
{
    public function __construct(private TeacherReferralService $referrals) {}

    public function validateCode(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:32']]);

        return response()->json(['valid' => $this->referrals->validCode($data['code']) !== null]);
    }

    public function mine(Request $request): JsonResponse
    {
        $teacher = $request->user();
        if (! $teacher->isTeacher()) {
            return response()->json(['message' => 'Only teachers can refer teachers.'], 403);
        }

        $code = $this->referrals->codeFor($teacher);
        $referrals = TeacherReferral::where('referrer_id', $teacher->id)
            ->with('referredUser:id,name')
            ->latest()->get();

        return response()->json([
            'data' => [
                'code' => $code->code,
                'reward_type' => $code->reward_type,
                'reward_value' => (float) $code->reward_value,
                'referrals' => $referrals->map(fn (TeacherReferral $referral): array => [
                    'id' => $referral->id,
                    'teacher_name' => $referral->referredUser?->name,
                    'status' => $referral->status,
                    'reward_amount' => (float) ($referral->reward_amount ?? 0),
                    'currency' => $referral->currency,
                    'created_at' => $referral->created_at?->toIso8601String(),
                    'qualified_at' => $referral->qualified_at?->toIso8601String(),
                ]),
            ],
        ]);
    }
}
