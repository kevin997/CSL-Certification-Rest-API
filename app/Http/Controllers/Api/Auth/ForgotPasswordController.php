<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Validator;

class ForgotPasswordController extends Controller
{
    /**
     * Send a reset link to the given user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function sendResetLinkEmail(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'environment_id' => 'nullable|exists:environments,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        // Check if we need to use environment-specific credentials
        if ($request->has('environment_id')) {
            $environmentId = $request->environment_id;
            
            // Check if the user has environment-specific credentials
            $pivot = DB::table('environment_user')
                ->where('environment_id', $environmentId)
                ->where('environment_email', $request->email)
                ->where('use_environment_credentials', true)
                ->first();
            
            if ($pivot) {
                // Get the user's actual email for password reset
                $user = User::find($pivot->user_id);
                if ($user) {
                    // Store environment ID in the token data for later use
                    $request->merge(['email' => $user->email, '_environment_id' => $environmentId]);
                }
            }
        }

        // We will send the password reset link to this user. Once we have attempted
        // to send the link, we will examine the response then see the message we
        // need to show to the user. Finally, we'll send out a proper response.
        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json(['message' => __($status)]);
        }

        return response()->json(['email' => __($status)], 400);
    }
}
