<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnvironmentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class EnvironmentUserController extends Controller
{
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
