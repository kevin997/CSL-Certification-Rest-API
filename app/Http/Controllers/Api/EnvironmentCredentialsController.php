<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class EnvironmentCredentialsController extends Controller
{
    /**
     * Get the current user's environment-specific credentials.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $environmentId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Get(
     *     path="/environment-credentials/{environmentId}",
     *     summary="Get environment-specific credentials",
     *     description="Returns whether the user has environment-specific credentials for the specified environment",
     *     operationId="getEnvironmentCredentials",
     *     tags={"Environment Credentials"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="environmentId",
     *         in="path",
     *         description="Environment ID",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
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
     *                 @OA\Property(property="has_environment_credentials", type="boolean", example=true),
     *                 @OA\Property(property="environment_email", type="string", example="user@company.com"),
     *                 @OA\Property(property="email_verified", type="boolean", example=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Environment not found or user not associated with environment"
     *     )
     * )
     */
    public function show(Request $request, $environmentId)
    {
        // Check if the user is associated with this environment
        $pivot = $request->user()->environments()
            ->where('environment_id', $environmentId)
            ->first()?->pivot;

        if (!$pivot) {
            return response()->json([
                'status' => 'error',
                'message' => 'Environment not found or user not associated with this environment'
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'has_environment_credentials' => (bool) $pivot->use_environment_credentials,
                'environment_email' => $pivot->environment_email,
                'email_verified' => $pivot->email_verified_at !== null
            ]
        ]);
    }

    /**
     * Update the environment-specific credentials for the current user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $environmentId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Put(
     *     path="/environment-credentials/{environmentId}",
     *     summary="Update environment-specific credentials",
     *     description="Updates the environment-specific credentials for the current user",
     *     operationId="updateEnvironmentCredentials",
     *     tags={"Environment Credentials"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="environmentId",
     *         in="path",
     *         description="Environment ID",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\RequestBody(
     *         required=true,
     *         description="Credential data",
     *         @OA\JsonContent(
     *             required={"environment_email", "password"},
     *             @OA\Property(property="environment_email", type="string", example="user@company.com"),
     *             @OA\Property(property="password", type="string", example="new-password"),
     *             @OA\Property(property="use_environment_credentials", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Credentials updated successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Environment credentials updated successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Environment not found or user not associated with environment"
     *     ),
     *     @OA\Response(
     *         response=422,
     *         description="Validation error"
     *     )
     * )
     */
    public function update(Request $request, $environmentId)
    {
        // Validate the request
        $validator = Validator::make($request->all(), [
            'environment_email' => 'required|email',
            'password' => 'required|min:8',
            'use_environment_credentials' => 'boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if the user is associated with this environment
        $environment = Environment::find($environmentId);
        if (!$environment || !$request->user()->environments()->where('environment_id', $environmentId)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Environment not found or user not associated with this environment'
            ], 404);
        }

        // Update the environment-specific credentials
        $useCredentials = $request->input('use_environment_credentials', true);
        $success = $request->user()->setEnvironmentCredentials(
            $environmentId,
            $request->input('environment_email'),
            $request->input('password'),
            $useCredentials
        );

        if (!$success) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to update environment credentials'
            ], 500);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Environment credentials updated successfully'
        ]);
    }

    /**
     * Disable environment-specific credentials for the current user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $environmentId
     * @return \Illuminate\Http\Response
     * 
     * @OA\Delete(
     *     path="/environment-credentials/{environmentId}",
     *     summary="Disable environment-specific credentials",
     *     description="Disables the environment-specific credentials for the current user",
     *     operationId="disableEnvironmentCredentials",
     *     tags={"Environment Credentials"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="environmentId",
     *         in="path",
     *         description="Environment ID",
     *         required=true,
     *         @OA\Schema(type="integer", format="int64")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Credentials disabled successfully",
     *         @OA\JsonContent(
     *             type="object",
     *             @OA\Property(property="status", type="string", example="success"),
     *             @OA\Property(property="message", type="string", example="Environment credentials disabled successfully")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Environment not found or user not associated with environment"
     *     )
     * )
     */
    public function destroy(Request $request, $environmentId)
    {
        // Check if the user is associated with this environment
        $environment = Environment::find($environmentId);
        if (!$environment || !$request->user()->environments()->where('environment_id', $environmentId)->exists()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Environment not found or user not associated with this environment'
            ], 404);
        }

        // Disable environment-specific credentials
        $request->user()->environments()->updateExistingPivot($environmentId, [
            'use_environment_credentials' => false
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Environment credentials disabled successfully'
        ]);
    }
}
