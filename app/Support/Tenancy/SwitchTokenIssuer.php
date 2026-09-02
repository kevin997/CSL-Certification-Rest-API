<?php

namespace App\Support\Tenancy;

use App\Models\Environment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * One-time tokens exchanged by POST /auth/validate-switch-token for a session
 * bound to the target environment. Used by academy switching (60 s) and by
 * onboarding auto sign-in (config tenancy.onboarding_switch_token_ttl_seconds).
 */
final class SwitchTokenIssuer
{
    public const CACHE_PREFIX = 'academy_switch_token:';

    public function issue(User $user, Environment $target, int $ttlSeconds, ?string $sourceEnvironmentId = null): string
    {
        $token = Str::random(64);

        Cache::put(self::CACHE_PREFIX.$token, [
            'user_id' => $user->id,
            'target_environment_id' => $target->id,
            'source_environment_id' => $sourceEnvironmentId,
            'created_at' => now()->toIso8601String(),
        ], now()->addSeconds($ttlSeconds));

        return $token;
    }

    public function redirectUrl(Environment $target, string $token): string
    {
        return TenantUrl::to($target, '/auth/switch', ['token' => $token]);
    }
}
