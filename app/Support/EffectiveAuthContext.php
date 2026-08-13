<?php

namespace App\Support;

use App\Enums\UserRole;
use App\Models\Environment;
use App\Models\EnvironmentUser;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class EffectiveAuthContext
{
    /**
     * @return array{role: string, user_role: string, environment_role: ?string, is_owner: bool}
     */
    public static function for(User $user, ?int $environmentId): array
    {
        $userRole = self::roleValue($user->role) ?? 'user';
        $environment = $environmentId
            ? Environment::query()->find($environmentId)
            : null;
        $isOwner = $environment?->owner_id === $user->id;
        $environmentUser = $environmentId
            ? EnvironmentUser::query()
                ->where('environment_id', $environmentId)
                ->where('user_id', $user->id)
                ->first()
            : null;

        if ($environmentId !== null && ! $isOwner && ! $environmentUser) {
            throw new AuthorizationException('User no longer has access to this environment.');
        }

        $environmentRole = $environmentUser
            ? self::roleValue($environmentUser->role)
            : null;

        $role = $isOwner
            ? ($userRole === UserRole::COMPANY_TEACHER->value
                ? UserRole::COMPANY_TEACHER->value
                : UserRole::INDIVIDUAL_TEACHER->value)
            : ($environmentRole ?? $userRole);

        return [
            'role' => $role,
            'user_role' => $userRole,
            'environment_role' => $environmentRole,
            'is_owner' => $isOwner,
        ];
    }

    private static function roleValue(mixed $role): ?string
    {
        if ($role instanceof UserRole) {
            return $role->value;
        }

        return is_string($role) && $role !== '' ? $role : null;
    }
}
