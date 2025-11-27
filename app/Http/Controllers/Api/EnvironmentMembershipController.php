<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\EnvironmentUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Environment Membership",
 *     description="API Endpoints for managing user environment memberships. Supports the Identity Unification model where users can join environments without creating new credentials."
 * )
 */
class EnvironmentMembershipController extends Controller
{
    /**
     * Join an environment.
     * 
     * Creates a membership record linking the authenticated user to the specified environment.
     * No credentials are required - the user uses their global password.
     *
     * @OA\Post(
     *     path="/api/environments/{id}/join",
     *     summary="Join an environment",
     *     tags={"Environment Membership"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Environment ID to join",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Already a member of this environment",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Already a member of this environment"),
     *             @OA\Property(property="environment_user", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Successfully joined the environment",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Successfully joined the environment"),
     *             @OA\Property(property="environment_user", type="object")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Environment not found"
     *     ),
     *     @OA\Response(
     *         response=401,
     *         description="Unauthenticated"
     *     )
     * )
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function join(Request $request, int $id)
    {
        $user = $request->user();

        // Check if environment exists
        $environment = Environment::find($id);
        if (!$environment) {
            return response()->json([
                'message' => 'Environment not found',
            ], 404);
        }

        // Check if user is already a member
        $existingMembership = EnvironmentUser::where('environment_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if ($existingMembership) {
            return response()->json([
                'message' => 'Already a member of this environment',
                'environment_user' => $existingMembership,
            ], 200);
        }

        // Create new membership (NO password needed - uses global password)
        $environmentUser = EnvironmentUser::create([
            'environment_id' => $id,
            'user_id' => $user->id,
            'role' => 'learner', // Default role
            'permissions' => json_encode([]),
            'joined_at' => now(),
            'use_environment_credentials' => false, // Uses global password
            'is_account_setup' => true, // Account is already set up (they have a global password)
        ]);

        Log::info('User joined environment via Join API', [
            'user_id' => $user->id,
            'environment_id' => $id,
        ]);

        return response()->json([
            'message' => 'Successfully joined the environment',
            'environment_user' => $environmentUser,
        ], 201);
    }

    /**
     * Leave an environment.
     *
     * @OA\Delete(
     *     path="/api/environments/{id}/leave",
     *     summary="Leave an environment",
     *     tags={"Environment Membership"},
     *     security={{"sanctum":{}}},
     *     @OA\Parameter(
     *         name="id",
     *         in="path",
     *         required=true,
     *         description="Environment ID to leave",
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Successfully left the environment",
     *         @OA\JsonContent(
     *             @OA\Property(property="message", type="string", example="Successfully left the environment")
     *         )
     *     ),
     *     @OA\Response(
     *         response=404,
     *         description="Not a member of this environment"
     *     ),
     *     @OA\Response(
     *         response=403,
     *         description="Cannot leave - you are the owner of this environment"
     *     )
     * )
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function leave(Request $request, int $id)
    {
        $user = $request->user();

        // Check if environment exists
        $environment = Environment::find($id);
        if (!$environment) {
            return response()->json([
                'message' => 'Environment not found',
            ], 404);
        }

        // Check if user is the owner
        if ($environment->owner_id === $user->id) {
            return response()->json([
                'message' => 'Cannot leave - you are the owner of this environment',
            ], 403);
        }

        // Find and delete membership
        $membership = EnvironmentUser::where('environment_id', $id)
            ->where('user_id', $user->id)
            ->first();

        if (!$membership) {
            return response()->json([
                'message' => 'Not a member of this environment',
            ], 404);
        }

        $membership->delete();

        Log::info('User left environment', [
            'user_id' => $user->id,
            'environment_id' => $id,
        ]);

        return response()->json([
            'message' => 'Successfully left the environment',
        ], 200);
    }

    /**
     * Get all environments the authenticated user is a member of.
     *
     * @OA\Get(
     *     path="/api/user/environments",
     *     summary="Get user's environments",
     *     tags={"Environment Membership"},
     *     security={{"sanctum":{}}},
     *     @OA\Response(
     *         response=200,
     *         description="List of environments",
     *         @OA\JsonContent(
     *             @OA\Property(property="environments", type="array", @OA\Items(type="object"))
     *         )
     *     )
     * )
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function myEnvironments(Request $request)
    {
        $user = $request->user();

        // Get environments where user is a member (with branding)
        $memberEnvironments = EnvironmentUser::where('user_id', $user->id)
            ->with(['environment.branding'])
            ->get()
            ->map(function ($membership) {
                $environment = $membership->environment;
                return [
                    'environment' => $environment,
                    'role' => $membership->role,
                    'joined_at' => $membership->joined_at,
                    'is_owner' => false,
                    'branding' => $environment->branding ? [
                        'logo_path' => $environment->branding->logo_path,
                        'favicon_path' => $environment->branding->favicon_path,
                        'primary_color' => $environment->branding->primary_color,
                    ] : null,
                ];
            });

        // Get environments where user is the owner (with branding)
        $ownedEnvironments = Environment::where('owner_id', $user->id)
            ->with('branding')
            ->get()
            ->map(function ($environment) {
                return [
                    'environment' => $environment,
                    'role' => 'owner',
                    'joined_at' => $environment->created_at,
                    'is_owner' => true,
                    'branding' => $environment->branding ? [
                        'logo_path' => $environment->branding->logo_path,
                        'favicon_path' => $environment->branding->favicon_path,
                        'primary_color' => $environment->branding->primary_color,
                    ] : null,
                ];
            });

        // Merge and deduplicate (in case owner is also in environment_user)
        $allEnvironments = $ownedEnvironments->merge($memberEnvironments)
            ->unique(function ($item) {
                return $item['environment']->id;
            })
            ->values();

        return response()->json([
            'environments' => $allEnvironments,
        ], 200);
    }
}
