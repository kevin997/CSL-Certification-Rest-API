<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EnvironmentLiveSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @OA\Tag(
 *     name="Live Settings",
 *     description="API Endpoints for managing environment live session settings"
 * )
 */
class EnvironmentLiveSettingsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/live-settings",
     *     summary="Get live session settings for an environment",
     *     tags={"Live Settings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="environment_id",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Live settings retrieved successfully",
     *         @OA\JsonContent(ref="#/components/schemas/EnvironmentLiveSettings")
     *     ),
     *     @OA\Response(response=404, description="Settings not found")
     * )
     */
    public function show(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'environment_id' => 'required|integer|exists:environments,id',
        ]);

        $settings = EnvironmentLiveSettings::where('environment_id', $validated['environment_id'])->first();

        if (!$settings) {
            // Create default settings if they don't exist
            $settings = EnvironmentLiveSettings::create([
                'environment_id' => $validated['environment_id'],
                'live_sessions_enabled' => false,
                'monthly_minutes_limit' => 0, // Unlimited by default
                'monthly_minutes_used' => 0,
                'max_concurrent_sessions' => 1,
                'max_participants_per_session' => 100,
            ]);
        }

        return response()->json([
            'data' => $settings,
        ]);
    }

    /**
     * @OA\Put(
     *     path="/api/live-settings",
     *     summary="Update live session settings for an environment",
     *     tags={"Live Settings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(
     *             required={"environment_id"},
     *             @OA\Property(property="environment_id", type="integer", example=1),
     *             @OA\Property(property="live_sessions_enabled", type="boolean", example=true),
     *             @OA\Property(property="monthly_minutes_limit", type="integer", example=1000),
     *             @OA\Property(property="max_concurrent_sessions", type="integer", example=1),
     *             @OA\Property(property="max_participants_per_session", type="integer", example=100)
     *         )
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Settings updated successfully",
     *         @OA\JsonContent(ref="#/components/schemas/EnvironmentLiveSettings")
     *     ),
     *     @OA\Response(response=422, description="Validation error")
     * )
     */
    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'environment_id' => 'required|integer|exists:environments,id',
            'live_sessions_enabled' => 'sometimes|boolean',
            'monthly_minutes_limit' => 'sometimes|integer|min:0',
            'max_concurrent_sessions' => 'sometimes|integer|min:1|max:10',
            'max_participants_per_session' => 'sometimes|integer|min:1|max:1000',
        ]);

        $settings = EnvironmentLiveSettings::updateOrCreate(
            ['environment_id' => $validated['environment_id']],
            array_filter([
                'live_sessions_enabled' => $validated['live_sessions_enabled'] ?? null,
                'monthly_minutes_limit' => $validated['monthly_minutes_limit'] ?? null,
                'max_concurrent_sessions' => $validated['max_concurrent_sessions'] ?? null,
                'max_participants_per_session' => $validated['max_participants_per_session'] ?? null,
            ], fn($value) => $value !== null)
        );

        return response()->json([
            'message' => 'Live settings updated successfully.',
            'data' => $settings->fresh(),
        ]);
    }

    /**
     * @OA\Get(
     *     path="/api/live-settings/usage",
     *     summary="Get usage statistics for live sessions",
     *     tags={"Live Settings"},
     *     security={{"bearerAuth":{}}},
     *     @OA\Parameter(
     *         name="environment_id",
     *         in="query",
     *         required=true,
     *         @OA\Schema(type="integer")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Usage statistics retrieved successfully"
     *     )
     * )
     */
    public function usage(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'environment_id' => 'required|integer|exists:environments,id',
        ]);

        $settings = EnvironmentLiveSettings::where('environment_id', $validated['environment_id'])->first();

        if (!$settings) {
            return response()->json([
                'data' => [
                    'minutes_used' => 0,
                    'minutes_limit' => 0,
                    'minutes_remaining' => PHP_INT_MAX,
                    'is_unlimited' => true,
                    'has_exceeded_limit' => false,
                ],
            ]);
        }

        return response()->json([
            'data' => [
                'minutes_used' => $settings->monthly_minutes_used,
                'minutes_limit' => $settings->monthly_minutes_limit,
                'minutes_remaining' => $settings->getRemainingMinutes(),
                'is_unlimited' => $settings->monthly_minutes_limit === 0,
                'has_exceeded_limit' => $settings->hasExceededLimit(),
                'billing_cycle_resets_at' => $settings->billing_cycle_resets_at,
            ],
        ]);
    }
}
