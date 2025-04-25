<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    /**
     * Get the authenticated user's profile.
     *
     * @OA\Get(
     *     path="/api/profile",
     *     summary="Get user profile",
     *     description="Returns the authenticated user's profile information",
     *     operationId="getProfile",
     *     tags={"Profile"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="User profile retrieved successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object"),
     *                 @OA\Property(property="environment_user", type="object", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function getProfile(Request $request)
    {
        $user = $request->user();
        $environmentId = $request->environment->id ?? null;
        $environmentUser = null;

        // If we have an environment context, get the environment user data
        if ($environmentId) {
            $environmentUser = EnvironmentUser::where('environment_id', $environmentId)
                ->where('user_id', $user->id)
                ->first();
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
                'environment_user' => $environmentUser,
                'is_owner' => $environmentId ? ($request->environment->owner_id === $user->id) : false
            ]
        ]);
    }

    /**
     * Update the authenticated user's profile.
     *
     * @OA\Put(
     *     path="/api/profile",
     *     summary="Update user profile",
     *     description="Updates the authenticated user's profile information",
     *     operationId="updateProfile",
     *     tags={"Profile"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="name", type="string", example="John Doe"),
     *             @OA\Property(property="email", type="string", format="email", example="john@example.com"),
     *             @OA\Property(property="current_password", type="string", example="current-password"),
     *             @OA\Property(property="password", type="string", example="new-password"),
     *             @OA\Property(property="password_confirmation", type="string", example="new-password"),
     *             @OA\Property(property="environment_email", type="string", format="email", example="john@environment.com"),
     *             @OA\Property(property="environment_password", type="string", example="environment-password"),
     *             @OA\Property(property="environment_password_confirmation", type="string", example="environment-password"),
     *             @OA\Property(property="use_environment_credentials", type="boolean", example=true)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Profile updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object"),
     *                 @OA\Property(property="environment_user", type="object", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();
        $environmentId = $request->environment->id ?? null;
        $isOwner = $environmentId ? ($request->environment->owner_id === $user->id) : false;

        // Validate the request
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'current_password' => 'sometimes|required_with:password|string',
            'password' => 'sometimes|string|min:8|confirmed',
            'environment_email' => [
                'sometimes',
                'string',
                'email',
                'max:255',
            ],
            'environment_password' => 'sometimes|string|min:8|confirmed',
            'use_environment_credentials' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }

        // Verify current password if changing password
        if ($request->has('password') && $request->filled('current_password')) {
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Current password is incorrect',
                ], 400);
            }
        }

        // Update user data
        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->has('email')) {
            $user->email = $request->email;
        }

        if ($request->has('password') && $request->filled('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        // Update environment user data if in environment context
        $environmentUser = null;
        if ($environmentId) {
            $environmentUser = EnvironmentUser::where('environment_id', $environmentId)
                ->where('user_id', $user->id)
                ->first();

            if ($environmentUser) {
                if ($request->has('environment_email')) {
                    $environmentUser->environment_email = $request->environment_email;
                }

                if ($request->has('environment_password') && $request->filled('environment_password')) {
                    $environmentUser->environment_password = Hash::make($request->environment_password);
                }

                if ($request->has('use_environment_credentials')) {
                    $environmentUser->use_environment_credentials = $request->use_environment_credentials;
                }

                $environmentUser->save();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'user' => $user,
                'environment_user' => $environmentUser,
                'is_owner' => $isOwner
            ]
        ]);
    }
    
    /**
     * Update the authenticated user's profile photo.
     *
     * @OA\Put(
     *     path="/api/profile/photo",
     *     summary="Update user profile photo",
     *     description="Updates the authenticated user's profile photo URL",
     *     operationId="updateProfilePhoto",
     *     tags={"Profile"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             @OA\Property(property="profile_photo_url", type="string", example="https://res.cloudinary.com/example/image/upload/v1234567890/profile.jpg"),
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Profile photo updated successfully",
     *         @OA\JsonContent(
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Profile photo updated successfully"),
     *             @OA\Property(property="data", type="object",
     *                 @OA\Property(property="user", type="object"),
     *                 @OA\Property(property="environment_user", type="object", nullable=true)
     *             )
     *         )
     *     ),
     *     @OA\Response(
     *         response=400,
     *         description="Validation error"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     */
    public function updateProfilePhoto(Request $request)
    {
        $user = $request->user();
        $environmentId = $request->environment->id ?? null;
        $isOwner = $environmentId ? ($request->environment->owner_id === $user->id) : false;
        
        // Validate the request
        $validator = Validator::make($request->all(), [
            'profile_photo_url' => 'required|string|url',
        ]);
        
        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $validator->errors()
            ], 400);
        }
        
        try {
            // Update the user's profile photo path
            // Laravel Jetstream expects profile_photo_path, not profile_photo_url
            $user->profile_photo_path = $request->profile_photo_url;
            $user->save();
            
            // Get the environment user if in environment context
            $environmentUser = null;
            if ($environmentId) {
                $environmentUser = EnvironmentUser::where('environment_id', $environmentId)
                    ->where('user_id', $user->id)
                    ->first();
            }
            
            return response()->json([
                'success' => true,
                'message' => 'Profile photo updated successfully',
                'data' => [
                    'user' => $user,
                    'environment_user' => $environmentUser,
                    'is_owner' => $isOwner
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile photo',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
