<?php

namespace App\Support\Tenancy;

use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Every environment a user can enter: the ones they own and the ones they were
 * added to. Same item shape as GET /user/environments, which now uses this.
 */
final class MembershipList
{
    /**
     * @return Collection<int, array{environment: Environment, role: string, joined_at: mixed, is_owner: bool, branding: ?array}>
     */
    public static function for(User $user): Collection
    {
        $brandingOf = static fn (Environment $environment): ?array => $environment->branding ? [
            'logo_path' => $environment->branding->logo_path,
            'favicon_path' => $environment->branding->favicon_path,
            'primary_color' => $environment->branding->primary_color,
        ] : null;

        $owned = Environment::where('owner_id', $user->id)
            ->with('branding')
            ->get()
            ->map(fn (Environment $environment) => [
                'environment' => $environment,
                'role' => 'owner',
                'joined_at' => $environment->created_at,
                'is_owner' => true,
                'branding' => $brandingOf($environment),
            ]);

        $member = EnvironmentUser::where('user_id', $user->id)
            ->with(['environment.branding'])
            ->get()
            ->filter(fn (EnvironmentUser $membership) => $membership->environment !== null)
            ->map(fn (EnvironmentUser $membership) => [
                'environment' => $membership->environment,
                'role' => $membership->role,
                'joined_at' => $membership->joined_at,
                'is_owner' => false,
                'branding' => $brandingOf($membership->environment),
            ]);

        // Both sides are plain arrays; toBase() avoids Eloquent's merge() calling getKey() on them.
        return $owned->toBase()
            ->merge($member->toBase())
            ->unique(fn (array $item) => $item['environment']->id)
            ->values();
    }

    /**
     * @return Collection<int, int> active environment ids
     */
    public static function activeIdsFor(User $user): Collection
    {
        return self::for($user)
            ->filter(fn (array $item) => (bool) $item['environment']->is_active)
            ->map(fn (array $item) => (int) $item['environment']->id)
            ->values();
    }
}
