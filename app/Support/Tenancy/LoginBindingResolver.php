<?php

namespace App\Support\Tenancy;

use App\Enums\UserRole;
use App\Models\Environment;
use App\Models\User;

/**
 * Which environment a successful login binds to. Returns null when neither the
 * host nor the shared-host rules apply, so the caller keeps its legacy branch
 * (teacher auto-resolve on session login; no ability on token login).
 */
final class LoginBindingResolver
{
    private const PLATFORM_ROLES = [
        UserRole::ADMIN->value,
        UserRole::SUPER_ADMIN->value,
        UserRole::SALES_AGENT->value,
    ];

    public function __construct(private readonly EnvironmentResolver $resolver) {}

    /**
     * @throws NoEnvironmentException
     */
    public function resolve(User $user, ?int $requestedEnvironmentId, string $host): ?LoginBinding
    {
        if ($requestedEnvironmentId !== null) {
            // The caller verifies membership exactly as before.
            return LoginBinding::to($requestedEnvironmentId);
        }

        $memberships = MembershipList::activeIdsFor($user);

        if (! $this->resolver->isSharedHost($host)) {
            $hostEnvironment = Environment::findActiveByDomain($host);

            if ($hostEnvironment && $memberships->contains($hostEnvironment->id)) {
                return LoginBinding::to($hostEnvironment->id);
            }

            return null;
        }

        if ($memberships->count() === 1) {
            return LoginBinding::to($memberships->first());
        }

        if ($memberships->count() > 1) {
            $environments = MembershipList::for($user)
                ->filter(fn (array $item) => (bool) $item['environment']->is_active)
                ->values()
                ->all();

            return LoginBinding::select($environments);
        }

        $role = $user->role instanceof UserRole ? $user->role->value : $user->role;

        if (is_string($role) && in_array($role, self::PLATFORM_ROLES, true)) {
            return LoginBinding::none();
        }

        throw NoEnvironmentException::forUser();
    }
}
