<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PushSubscriptionController extends Controller
{
    public function vapidPublicKey(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => [
                'publicKey' => (string) env('VAPID_PUBLIC_KEY', ''),
            ],
        ]);
    }

    public function store(Request $request, int $environmentId): JsonResponse
    {
        $userId = (int) Auth::id();

        $validator = Validator::make($request->all(), [
            'endpoint' => ['required', 'string'],
            'keys' => ['required', 'array'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'expirationTime' => ['nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $endpoint = (string) $request->input('endpoint');
        $keys = (array) $request->input('keys');
        $endpointHash = hash('sha256', $endpoint);

        $sub = PushSubscription::updateOrCreate(
            [
                'environment_id' => $environmentId,
                'user_id' => $userId,
                'endpoint_hash' => $endpointHash,
            ],
            [
                'endpoint' => $endpoint,
                'public_key' => (string) ($keys['p256dh'] ?? ''),
                'auth_token' => (string) ($keys['auth'] ?? ''),
                'content_encoding' => 'aesgcm',
                'expiration_time' => null,
                'user_agent' => substr((string) $request->userAgent(), 0, 512),
            ]
        );

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $sub->id,
            ],
        ]);
    }

    public function destroy(Request $request, int $environmentId): JsonResponse
    {
        $userId = (int) Auth::id();

        $validator = Validator::make($request->all(), [
            'endpoint' => ['required', 'string'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors(),
            ], 422);
        }

        $endpoint = (string) $request->input('endpoint');

        PushSubscription::where('environment_id', $environmentId)
            ->where('user_id', $userId)
            ->where('endpoint', $endpoint)
            ->delete();

        return response()->json([
            'status' => 'success',
        ]);
    }
}
