<?php

namespace App\Http\Controllers\Api\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Environment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Operator override for domain liveness. The hourly probe only sets the flag;
 * this is the one place it can be cleared, for instance when a tenant's DNS is
 * taken down again and their links must fall back to the shared host.
 */
class DomainVerificationController extends Controller
{
    public function update(Request $request, int $environmentId): JsonResponse
    {
        $role = $request->user()?->role instanceof UserRole
            ? $request->user()->role->value
            : $request->user()?->role;

        if (! in_array($role, [UserRole::ADMIN->value, UserRole::SUPER_ADMIN->value], true)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate(['verified' => 'required|boolean']);

        $environment = Environment::find($environmentId);

        if (! $environment) {
            return response()->json(['status' => 'error', 'message' => 'Environment not found'], 404);
        }

        $environment->forceFill([
            'domain_verified_at' => $validated['verified'] ? now() : null,
        ])->save();

        Log::info('tenancy.domain_verification_overridden', [
            'environment_id' => $environment->id,
            'verified' => (bool) $validated['verified'],
            'admin_user_id' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $environment->id,
                'primary_domain' => $environment->primary_domain,
                'domain_verified_at' => $environment->domain_verified_at?->toIso8601String(),
            ],
        ]);
    }
}
