<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\User;
use App\Support\EffectiveAuthContext;
use App\Support\Tenancy\SwitchTokenIssuer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * @OA\Tag(
 *     name="Campus Switch",
 *     description="API Endpoints for cross-domain campus switching. Allows users to seamlessly switch between campuses on different domains."
 * )
 */
class AcademySwitchController extends Controller
{
    /**
     * Generate a short-lived token for switching to another campus/domain.
     *
     * This token is used for cross-domain authentication when a user wants to
     * switch from one campus domain to another.
     *
     * @OA\Post(
     *     path="/api/auth/academy-switch-token",
     *     summary="Generate campus switch token",
     *     tags={"Campus Switch"},
     *     security={{"sanctum":{}}},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"target_environment_id"},
     *
     *             @OA\Property(property="target_environment_id", type="integer", description="The environment ID to switch to")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Switch token generated successfully",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="token", type="string", description="Short-lived switch token"),
     *             @OA\Property(property="redirect_url", type="string", description="URL to redirect to"),
     *             @OA\Property(property="expires_in", type="integer", description="Token expiry in seconds")
     *         )
     *     ),
     *
     *     @OA\Response(response=404, description="Target environment not found"),
     *     @OA\Response(response=403, description="User is not a member of target environment"),
     *     @OA\Response(response=401, description="Unauthenticated")
     * )
     *
     * @return JsonResponse
     */
    public function generateSwitchToken(Request $request)
    {
        $request->validate([
            'target_environment_id' => 'required|integer',
        ]);

        $user = $request->user();
        $targetEnvironmentId = $request->target_environment_id;

        // Check if target environment exists
        $targetEnvironment = Environment::find($targetEnvironmentId);
        if (! $targetEnvironment) {
            return response()->json([
                'message' => 'Target environment not found',
            ], 404);
        }

        // Check if user has access to target environment (owner or member)
        $isOwner = $targetEnvironment->owner_id === $user->id;
        $isMember = EnvironmentUser::where('environment_id', $targetEnvironmentId)
            ->where('user_id', $user->id)
            ->exists();

        if (! $isOwner && ! $isMember) {
            return response()->json([
                'message' => 'You are not a member of this campus',
            ], 403);
        }

        $expiresIn = 60;
        $issuer = app(SwitchTokenIssuer::class);
        $switchToken = $issuer->issue(
            $user,
            $targetEnvironment,
            $expiresIn,
            $request->header('X-Environment-Id'),
        );

        // A pending domain lands on the shared host; a live one on the tenant domain.
        $redirectUrl = $issuer->redirectUrl($targetEnvironment, $switchToken);
        $targetDomain = parse_url($redirectUrl, PHP_URL_HOST);

        Log::info('Academy switch token generated', [
            'user_id' => $user->id,
            'target_environment_id' => $targetEnvironmentId,
            'target_domain' => $targetDomain,
        ]);

        return response()->json([
            'token' => $switchToken,
            'redirect_url' => $redirectUrl,
            'expires_in' => $expiresIn,
            'target_environment' => [
                'id' => $targetEnvironment->id,
                'name' => $targetEnvironment->name,
                'domain' => $targetEnvironment->primary_domain,
            ],
        ], 200);
    }

    /**
     * Validate a switch token and return authentication credentials.
     *
     * This endpoint is called by the target domain to validate the switch token
     * and get the user's authentication token for that domain.
     *
     * @OA\Post(
     *     path="/api/auth/validate-switch-token",
     *     summary="Validate campus switch token",
     *     tags={"Campus Switch"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"token"},
     *
     *             @OA\Property(property="token", type="string", description="The switch token to validate"),
     *             @OA\Property(property="device_name", type="string", description="Device name for the new token")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Token validated, user authenticated",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="token", type="string", description="New auth token for target domain"),
     *             @OA\Property(property="user", type="object"),
     *             @OA\Property(property="environment_id", type="integer"),
     *             @OA\Property(property="role", type="string")
     *         )
     *     ),
     *
     *     @OA\Response(response=401, description="Invalid or expired token"),
     *     @OA\Response(response=403, description="Token not valid for this environment")
     * )
     *
     * @return JsonResponse
     */
    public function validateSwitchToken(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'device_name' => 'nullable|string',
        ]);

        $switchToken = $request->token;
        $deviceName = $request->device_name ?? 'web-client';

        // Retrieve token data from cache
        $tokenData = Cache::get(SwitchTokenIssuer::CACHE_PREFIX.$switchToken);

        if (! $tokenData) {
            return response()->json([
                'message' => 'Invalid or expired switch token',
            ], 401);
        }

        // Immediately invalidate the token (one-time use)
        Cache::forget(SwitchTokenIssuer::CACHE_PREFIX.$switchToken);

        $userId = $tokenData['user_id'];
        $targetEnvironmentId = $tokenData['target_environment_id'];

        // Get the user
        $user = User::find($userId);
        if (! $user) {
            return response()->json([
                'message' => 'User not found',
            ], 401);
        }

        // Get the target environment
        $targetEnvironment = Environment::find($targetEnvironmentId);
        if (! $targetEnvironment) {
            return response()->json([
                'message' => 'Target environment not found',
            ], 404);
        }

        // Verify membership before minting a token for the target environment.
        $isOwner = $targetEnvironment->owner_id === $user->id;
        $environmentUser = EnvironmentUser::where('environment_id', $targetEnvironmentId)
            ->where('user_id', $user->id)
            ->first();

        if (! $isOwner && ! $environmentUser) {
            return response()->json([
                'message' => 'You are not a member of this campus',
            ], 403);
        }

        $authContext = EffectiveAuthContext::for($user, (int) $targetEnvironmentId);

        // Create abilities array for the token
        $abilities = ['environment_id:'.$targetEnvironmentId];
        if ($user->role) {
            $abilities[] = 'role:'.($user->role->value ?? $user->role);
        }
        if ($environmentUser && $environmentUser->role) {
            $envRole = $environmentUser->role->value ?? $environmentUser->role;
            $abilities[] = 'env_role:'.$envRole;
        }

        // Create new auth token for the target environment
        $authToken = $user->createToken($deviceName, $abilities)->plainTextToken;

        Log::info('Academy switch completed', [
            'user_id' => $user->id,
            'target_environment_id' => $targetEnvironmentId,
            'role' => $authContext['role'],
        ]);

        $responseUser = $user->toArray();
        $responseUser['role'] = $authContext['role'];

        return response()->json([
            'token' => $authToken,
            'user' => $responseUser,
            'environment_id' => $targetEnvironmentId,
            'is_account_setup' => $environmentUser ? (bool) $environmentUser->is_account_setup : null,
            ...$authContext,
            'environment' => [
                'id' => $targetEnvironment->id,
                'name' => $targetEnvironment->name,
                'primary_domain' => $targetEnvironment->primary_domain,
            ],
        ], 200);
    }
}
