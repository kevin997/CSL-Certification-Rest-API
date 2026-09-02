<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Tenancy\AccountSetupMarker;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class ResetPasswordController extends Controller
{
    /**
     * Reset the given user's password.
     *
     * @return JsonResponse
     */
    public function reset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
            'environment_id' => 'nullable|exists:environments,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Here we will attempt to reset the user's password. If it is successful we
        // will update the password on an actual user model and persist it to the
        // database. Otherwise we will parse the error and return the response.
        //
        // IDENTITY UNIFICATION: Password resets now ONLY update users.password.
        // The environment_user.environment_password is deprecated.
        // Smart Login will handle users who still have old environment passwords.
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->setRememberToken(Str::random(60));

                $user->save();

                // IDENTITY UNIFICATION: No longer update environment_user.environment_password
                // The global users.password is now the single source of truth.
                // If user had use_environment_credentials=true, Smart Login will auto-heal
                // on their next login attempt with the old password.

                // The password is global, so a completed reset means every
                // membership of this user now has a usable password too.
                AccountSetupMarker::markAllMemberships($user);

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => __($status)]);
        }

        return response()->json(['email' => __($status)], 400);
    }
}
