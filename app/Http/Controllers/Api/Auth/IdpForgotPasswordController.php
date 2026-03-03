<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use App\Models\User;
use App\Mail\IdpForgotPasswordOtp;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

class IdpForgotPasswordController extends Controller
{
    /**
     * Send a 4-digit OTP code to the requested email.
     */
    public function sendOtp(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        // Always return success to prevent email enumeration attacks,
        // but only actually send an email if the user exists.
        if ($user) {
            // Generate a random 4-digit code
            $otp = str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);

            // Store in cache for 15 minutes (using email as key)
            Cache::put('idp_password_reset_otp_' . $user->email, $otp, now()->addMinutes(15));
            
            // Generate token representing intention, store for reset phase
            $token = bin2hex(random_bytes(32));
            Cache::put('idp_password_reset_token_' . $user->email, $token, now()->addMinutes(15));

            // Send Email
            Mail::to($user->email)->send(new IdpForgotPasswordOtp($user, $otp));
        }

        return response()->json([
            'success' => true,
            'message' => 'If your email address exists in our database, you will receive an OTP code.'
        ]);
    }

    /**
     * Verify the 4-digit OTP code.
     */
    public function verifyOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string|size:4'
        ]);

        $cachedOtp = Cache::get('idp_password_reset_otp_' . $request->email);

        if (!$cachedOtp || $cachedOtp !== $request->code) {
            throw ValidationException::withMessages([
                'code' => ['The provided code is invalid or has expired.']
            ]);
        }

        $token = Cache::get('idp_password_reset_token_' . $request->email);

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully.',
            'token' => $token
        ]);
    }

    /**
     * Reset the user's password.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|string',
            'password' => 'required|string|min:8'
        ]);

        $cachedToken = Cache::get('idp_password_reset_token_' . $request->email);

        if (!$cachedToken || $cachedToken !== $request->token) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token.'
            ], 400);
        }

        $user = User::where('email', $request->email)->first();
        if ($user) {
            $user->password = Hash::make($request->password);
            $user->save();

            // Clear cache
            Cache::forget('idp_password_reset_otp_' . $request->email);
            Cache::forget('idp_password_reset_token_' . $request->email);
            
            return response()->json([
                'success' => true,
                'message' => 'Password has been successfully reset.'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'User not found.'
        ], 404);
    }
}
