<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\EnvironmentResetPasswordMail;
use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class EnvironmentUserController extends Controller
{
    /**
     * Send a reset link for environment-specific credentials.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function forgotPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'environment_id' => 'required|exists:environments,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Get the environment details for branding in the email
        $environment = Environment::find($request->environment_id);
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }

        // First check if this is an environment owner
        $user = null;

        if ($environment->owner_id) {
            $ownerUser = User::find($environment->owner_id);
            if ($ownerUser && $ownerUser->email === $request->email) {
                $user = $ownerUser;
            }
        }

        // If not an owner, check the environment_user table
        if (!$user) {
            $environmentUser = DB::table('environment_user')
                ->where('environment_id', $request->environment_id)
                ->where('environment_email', $request->email)
                ->where('use_environment_credentials', true)
                ->first();

            if ($environmentUser) {
                $user = User::find($environmentUser->user_id);
            }
        }

        // If no user found in either case, return generic message for security
        if (!$user) {
            // Don't reveal that the user doesn't exist for security reasons
            return response()->json([
                'message' => 'If your email exists in our system, you will receive a password reset link shortly.'
            ]);
        }

        // Create a password reset token
        $token = Str::random(64);

        // Store the token in the password_reset_tokens table
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            [
                'email' => $user->email,
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        // Store additional metadata to identify this as an environment-specific reset
        DB::table('password_reset_metadata')->updateOrInsert(
            ['token' => $token],
            [
                'token' => $token,
                'metadata' => json_encode([
                    'environment_id' => $request->environment_id,
                    'environment_email' => $request->email,
                    'is_environment_reset' => true
                ]),
                'created_at' => now(),
            ]
        );

        // Send the password reset email using Laravel's Mail facade directly
        try {
            // Create a new instance of the mailable
            $mailable = new \App\Mail\EnvironmentResetPasswordMail(
                $token,
                $environment,
                $request->email,
                $user->email
            );

            // Send the email to the user's actual email address
            \Illuminate\Support\Facades\Mail::to($user->email)->send($mailable);

            // Send Telegram notification
            try {
                $telegramService = app(\App\Services\TelegramService::class);
                $notification = new \App\Notifications\EnvironmentPasswordReset(
                    $token,
                    $environment,
                    $request->email,
                    $user->email,
                    $telegramService
                );
                $notification->send();

                \Illuminate\Support\Facades\Log::info('Password reset Telegram notification sent', [
                    'user_id' => $user->id,
                    'environment_id' => $environment->id
                ]);
            } catch (\Exception $telegramEx) {
                // Log the error but continue execution
                \Illuminate\Support\Facades\Log::error('Failed to send password reset Telegram notification', [
                    'error' => $telegramEx->getMessage(),
                    'user_id' => $user->id,
                    'environment_id' => $environment->id
                ]);
            }

            // For development environments, include debug information
            if (config('app.env') === 'local' || config('app.env') === 'testing') {
                return response()->json([
                    'message' => 'Password reset link sent successfully',
                    'debug_token' => $token,
                    'debug_info' => [
                        'environment_id' => $environment->id,
                        'environment_name' => $environment->name,
                        'primary_domain' => $environment->primary_domain,
                        'user_email' => $user->email,
                        'environment_email' => $request->email,
                    ]
                ]);
            }
        } catch (\Exception $e) {
            // Log the error but don't expose it to the user
            \Illuminate\Support\Facades\Log::error('Failed to send password reset email', [
                'error' => $e->getMessage(),
                'user_id' => $user->id,
                'environment_id' => $environment->id
            ]);
        }

        return response()->json([
            'message' => 'If your email exists in our system, you will receive a password reset link shortly.'
        ]);
    }

    /**
     * Reset the environment user's password.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function resetPassword(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required|string',
            'email' => 'required|email',
            'environment_id' => 'required|exists:environments,id',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Get the metadata for this token
        $metadata = DB::table('password_reset_metadata')
            ->where('token', $request->token)
            ->first();

        if (!$metadata) {
            return response()->json(['message' => 'Invalid token'], 400);
        }

        $metadataArray = json_decode($metadata->metadata, true);

        // Verify this is an environment reset and matches the requested environment
        if (
            !isset($metadataArray['is_environment_reset']) ||
            !$metadataArray['is_environment_reset'] ||
            $metadataArray['environment_id'] != $request->environment_id
        ) {
            return response()->json(['message' => 'Invalid token for this environment'], 400);
        }

        // Find the user by email
        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        // Verify the token exists in the password_reset_tokens table
        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->first();

        if (!$resetRecord) {
            return response()->json(['message' => 'Invalid token'], 400);
        }

        // Get the environment to check if this is the owner
        $environment = Environment::find($request->environment_id);
        if (!$environment) {
            return response()->json(['message' => 'Environment not found'], 404);
        }

        $isOwner = ($environment->owner_id === $user->id);

        // If this is the environment owner, update their main password
        $updated = false;
        if ($isOwner && $user->email === $request->email) {
            $user->password = Hash::make($request->password);
            $updated = $user->save();
        } else {
            // Otherwise update the environment-specific password
            $updated = $user->setEnvironmentCredentials(
                $request->environment_id,
                $metadataArray['environment_email'],
                $request->password,
                true
            );
        }

        if (!$updated) {
            return response()->json(['message' => 'Failed to update password'], 500);
        }

        // Delete the token
        DB::table('password_reset_tokens')
            ->where('email', $user->email)
            ->delete();

        DB::table('password_reset_metadata')
            ->where('token', $request->token)
            ->delete();

        return response()->json(['message' => 'Password has been reset successfully']);
    }

    /**
     * Set up the user's account by changing password and marking account as set up.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function setupAccount(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'environment_id' => 'required|exists:environments,id',
            'password' => 'required|string|min:8',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Find the environment user record
        $environmentUser = EnvironmentUser::where('environment_id', $request->environment_id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$environmentUser) {
            return response()->json(['error' => 'Environment user record not found'], 404);
        }

        // Update the password and mark account as set up
        $environmentUser->environment_password = Hash::make($request->password);
        $environmentUser->is_account_setup = true;
        $environmentUser->save();

        return response()->json([
            'message' => 'Account setup completed successfully',
            'is_account_setup' => true
        ]);
    }
}
